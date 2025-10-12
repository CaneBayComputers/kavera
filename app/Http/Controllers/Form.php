<?php

namespace App\Http\Controllers;

use App\Mail\Form as MailForm;
use ElFactory\IpApi\IpApi;
use Exception;
use Faker\Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class Form extends Controller
{
    public function process(Request $request, Agent $agent, Generator $faker, string $form_name): RedirectResponse
    {
        $form = _c('form.forms.' . $form_name);
        if (!$form) {
            abort(404);
        }

        $form_data = $this->validateInput($request, $form['rules']);
        $user_fields = $form_data; // preserve raw user fields for webhook

        // Dev: generate a non-private/non-reserved fake IP; Prod: real client IP
        if (is_dev()) {
            $filter_flag = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            do {
                $ip_address = $faker->ipv4();
            } while (!filter_var($ip_address, FILTER_VALIDATE_IP, $filter_flag));
        } else {
            $ip_address = $request->ip();
        }

        // Consolidated bot/abuse checks (UA, links, rate limit, recaptcha)
        $response = $this->looksAutomated($form_data, $agent, $form_name, $ip_address);

        if ($response !== null) {
            return back()->withErrors([$response])->withInput();
        }

        // Always lookup via IpApi (even in dev)
        $form_data = $this->enrichClientMeta($form_data, $agent, $ip_address);

        // No DB persistence for now; just generate an ID for downstream usage
        $submission_id = $this->generateSubmissionId();

        if (is_dev()) {
            _l($form_data);
        }

        unset($form_data['token'], $form_data['recaptcha']);
        unset($user_fields['token'], $user_fields['_token'], $user_fields['recaptcha'], $user_fields['g-recaptcha-response']);

        if (!empty($form['mail_to'])) {
            $mail_form = new MailForm($form_data, $form['subject'], $form['view'], $form['type'], $form['text_view'] ?? null);
            Mail::to($form['mail_to'])->send($mail_form);
        }

        // Fire webhook if configured
        if (!empty($form['webhook_url'])) {
            $this->notifyWebhook($form_name, (string)$submission_id, $user_fields, (string)$form['webhook_url']);
        }

        $successPage = $form['success_page'] ?? null;
        if (empty($successPage)) {
            return back()->with('success', true);
        }

        $target = $this->resolveSuccessTarget($request, (string) $successPage);
        return redirect($target)->with('success', true);
    }

    private function resolveSuccessTarget(Request $request, string $success): string
    {
        // Absolute HTTP(S) URL
        if (preg_match('~^https?://~i', $success) === 1) {
            return $success;
        }

        // Absolute path within site
        if (str_starts_with($success, '/')) {
            return $success;
        }

        // Relative/fragment: append to originating page path
        $previous = url()->previous();
        $path = parse_url($previous, PHP_URL_PATH) ?: '/';
        if ($path === '') {
            $path = '/';
        }

        return $path . $success; // e.g., '/about' + '#footer'
    }

    private function validateInput(Request $request, array $rules): array
    {
        $data = $request->all();
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray())
                ->redirectTo(back()->getTargetUrl());
        }

        return $data;
    }

    private function rateLimitMessage(string $form_name, string $ip_address): ?string
    {
        $key = "form-{$form_name}-attempt-{$ip_address}";
        if (!Cache::has($key)) {
            Cache::add($key, 0, _c('form.ip_attempt_timeframe_seconds'));
        }

        $count = (int) Cache::get($key);
        if (!is_dev() && $count >= _c('form.ip_max_attempts_per_timeframe')) {
            return 'Too many attempts';
        }

        Cache::increment($key);
        return null;
    }

    private function looksAutomated(array $data, Agent $agent, string $form_name, string $ip_address): ?RedirectResponse
    {
        // 1) User-Agent heuristic
        if (($agent->deviceType() ?? '') === 'Robot') {
            return 'Device type is robot';
        }

        // 2) Message contains links
        if (!empty($data['message']) && preg_match('~https?://~i', $data['message'])) {
            return 'Please remove links';
        }

        // 3) IP rate limiting
        $message = $this->rateLimitMessage($form_name, $ip_address);
        if ($message !== null) {
            return $message;
        }

        // 4) reCAPTCHA (skip in dev)
        if (!is_dev()) {
            $token = $data['recaptcha'] ?? null;
            $message = $this->recaptchaMessage($token, $ip_address);
            if ($message !== null) {
                return $message;
            }
        }

        return null; // looks fine
    }

    private function recaptchaMessage(?string $token, string $ip_address): ?string
    {
        try {
            $response = Http::timeout(2)
                ->asForm()
                ->post(_c('form.recaptcha.url'), [
                    'secret'   => _c('form.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip_address,
                ])
                ->json();

            if (!isset($response['score'])) {
                throw new Exception('Recaptcha score returned blank');
            }

            if ($response['score'] < _c('form.recaptcha.threshold')) {
                throw new Exception('Request appears automated');
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return null;
    }

    private function enrichClientMeta(array $data, Agent $agent, string $ip_address): array
    {
        $data['timestamp']   = now()->setTimezone('UTC')->toRfc7231String();
        $data['device']      = $agent->device();
        $data['device_type'] = ucwords((string) $agent->deviceType());
        $data['platform']    = $agent->platform();
        $data['browser']     = $agent->browser();
        $languages           = $agent->languages() ?? [];
        $data['languages']   = implode(', ', $languages);

        try {
            // IpApi::lookup() returns an array per library docs
            $data['ip'] = IpApi::default($ip_address)->lookup();
        } catch (Exception $e) {
            $data['ip'] = $ip_address;
        }

        return $data;
    }

    private function generateSubmissionId(): string
    {
        return Str::ulid()->toBase32();
    }

    private function notifyWebhook(string $form_name, string $submission_id, array $fields, string $url): void
    {
        try {
            $payload = [
                'event' => 'form.submitted',
                'id' => 'evt_' . Str::ulid()->toBase32(),
                'created_at' => now()->setTimezone('UTC')->toIso8601String(),
                'data' => [
                    'form_id' => $form_name,
                    'submission_id' => 'sub_' . ($submission_id !== '' ? $submission_id : Str::ulid()->toBase32()),
                    'fields' => $this->flattenFields($fields),
                ],
                'meta' => [
                    'app' => config('app.name'),
                    'version' => now()->setTimezone('UTC')->toDateString(),
                ],
            ];

            Http::timeout(3)
                ->asJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            if (is_dev()) {
                _l('Webhook error', $e->getMessage());
            }
        }
    }

    private function flattenFields(array $fields): array
    {
        try {
            return \Illuminate\Support\Arr::dot($fields);
        } catch (\Throwable $e) {
            return $fields; // fallback
        }
    }
}
