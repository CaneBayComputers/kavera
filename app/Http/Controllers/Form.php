<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
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
    public function process(
        Request $request,
        Agent $agent,
        Generator $faker,
        string $form_name
    ): RedirectResponse {
        $form = _c('form.forms.' . $form_name);
        if (!$form) {
            abort(404);
        }

        $form_data = $this->validateInput($request, $form['rules']);

        // Dev: generate a non-private/non-reserved fake IP; Prod: real client IP
        if (is_dev()) {
            do {
                $ip_address = $faker->ipv4();
            } while (
                !filter_var(
                    $ip_address,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                )
            );
        } else {
            $ip_address = $request->ip();
        }

        // Consolidated bot/abuse checks (UA, links, rate limit, recaptcha)
        $response = $this->looksAutomated(
            $form_data,
            $agent,
            $form_name,
            $ip_address
        );

        if ($response !== null) {
            return back()->withErrors([$response])->withInput();
        }

        // Always lookup via IpApi (even in dev)
        $form_data = $this->enrichClientMeta($form_data, $agent, $ip_address);

        unset(
            $form_data['g-recaptcha-response'],
            $form_data['recaptcha'],
            $form_data['_token']
        );

        $this->persistSubmission($form_data);

        if (is_dev()) {
            _l($form_data);
        }

        if (!empty($form['mail_to'])) {
            Mail::to($form['mail_to'])->send(new MailForm($form_data, $form['subject'], $form['view'], $form['type']));
        }

        $redirect = $form['success_page'] ? redirect($form['success_page']) : back();
        return $redirect->with('success', true);
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

    private function looksAutomated(
        array $data,
        Agent $agent,
        string $form_name,
        string $ip_address
    ): ?RedirectResponse {
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
            $data['ip'] = IpApi::default($ip_address)->lookup();
        } catch (Exception $e) {
            $data['ip'] = $ip_address;
        }

        return $data;
    }

    private function persistSubmission(array $data): void
    {
        $submission = new FormSubmission();
        $submission->data = $data;
        $submission->save();
    }
}
