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

        // Mail dispatch (grouped config under 'mail', with backward compatibility)
        $mailCfg = (array) ($form['mail'] ?? []);
        $mailTo = (string) ($mailCfg['to'] ?? ($form['mail_to'] ?? ''));
        if ($mailTo !== '') {
            $subject = (string) ($mailCfg['subject'] ?? ($form['subject'] ?? (config('app.name') . ' Form Submission')));
            $view = (string) ($mailCfg['view'] ?? ($form['view'] ?? ''));
            $type = (string) ($mailCfg['type'] ?? ($form['type'] ?? 'view'));
            $textView = $mailCfg['text_view'] ?? ($form['text_view'] ?? null);

            if ($view !== '') {
                $mail_form = new MailForm($form_data, $subject, $view, $type, $textView ?: null);
                Mail::to($mailTo)->send($mail_form);
            }
        }

        // Fire webhook(s) if configured (prefer 'webhooks' array)
        if (!empty($form['webhooks']) && is_array($form['webhooks'])) {
            foreach ((array) $form['webhooks'] as $webhook) {
                $this->notifyWebhook($form_name, (string) $submission_id, $user_fields, (array) $webhook);
            }
        } elseif (!empty($form['webhook_url'])) { // backward compatibility
            $this->notifyWebhook($form_name, (string) $submission_id, $user_fields, [
                'url' => (string) $form['webhook_url'],
            ]);
        }

        $successPage = $form['success_page'] ?? null;
        if (empty($successPage)) {
            return back()->with('success', true);
        }

        [$target, $fragment] = $this->resolveSuccessTarget((string) $successPage);
        $resp = redirect($target);
        if (!empty($fragment)) {
            $resp = $resp->withFragment($fragment);
        }
        return $resp->with('success', true);
    }

    private function resolveSuccessTarget(string $success): array
    {
        // Absolute HTTP(S) URL (preserve any embedded fragment)
        if (preg_match('~^https?://~i', $success) === 1) {
            $url = $success;
            $frag = parse_url($success, PHP_URL_FRAGMENT) ?: '';
            if ($frag !== '') {
                $url = str_replace('#' . $frag, '', $success);
            }
            return [$url, $frag ?: null];
        }

        // Absolute path within site, may include fragment
        if (str_starts_with($success, '/')) {
            $parts = explode('#', $success, 2);
            $url = $parts[0];
            $frag = $parts[1] ?? null;
            return [$url, $frag];
        }

        // Fragment or relative: append to originating page path
        $previous = url()->previous();
        $path = parse_url($previous, PHP_URL_PATH) ?: '/';
        if ($path === '') {
            $path = '/';
        }

        $frag = ltrim($success, '#');
        return [$path, $frag !== '' ? $frag : null];
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

    private function looksAutomated(array $data, Agent $agent, string $form_name, string $ip_address): ?string
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

    private function notifyWebhook(string $form_name, string $submission_id, array $fields, array $webhook): void
    {
        try {
            $url = (string) ($webhook['url'] ?? '');
            if ($url === '') {
                return;
            }

            $context = [
                'app' => config('app.name'),
                'version' => now()->setTimezone('UTC')->toDateString(),
                'created_at' => now()->setTimezone('UTC')->toIso8601String(),
                'event_id' => Str::ulid()->toBase32(),
                'options' => (array) ($webhook['options'] ?? []),
                'form_name' => $form_name,
                'submission_id' => $submission_id,
                'fields' => $fields,
            ];

            $adapterClass = (string) ($webhook['adapter'] ?? \App\FormAdapters\DefaultEnvelopeAdapter::class);

            /** @var \App\FormAdapters\Contracts\FormAdapter $adapter */
            $adapter = app($adapterClass);

            $payload = $adapter->transform($form_name, $submission_id !== '' ? $submission_id : Str::ulid()->toBase32(), $fields, $context);

            $timeout = (int) ($webhook['timeout'] ?? 3);
            $request = Http::timeout($timeout)->asJson();

            $adapterOpts = (array) $adapter->requestOptions($context);
            if (!empty($adapterOpts['url'])) {
                $url = (string) $adapterOpts['url'];
            }
            $headers = array_merge((array) ($webhook['headers'] ?? []), (array) ($adapterOpts['headers'] ?? []));
            if (!empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            $method = strtoupper((string) ($webhook['method'] ?? ($adapterOpts['method'] ?? 'POST')));

            // Laravel HTTP client supports send with JSON body
            $request->send($method, $url, ['json' => $payload]);

            // Optional follow-up requests supplied by adapter (e.g., Mailchimp tags)
            if (!empty($adapterOpts['followups']) && is_array($adapterOpts['followups'])) {
                foreach ((array) $adapterOpts['followups'] as $follow) {
                    try {
                        $fuMethod = strtoupper((string) ($follow['method'] ?? 'POST'));
                        $fuUrl = (string) ($follow['url'] ?? '');
                        if ($fuUrl === '') {
                            continue;
                        }

                        $fuHeaders = array_merge($headers, (array) ($follow['headers'] ?? []));
                        $fuReq = Http::timeout($timeout)->asJson();
                        if (!empty($fuHeaders)) {
                            $fuReq = $fuReq->withHeaders($fuHeaders);
                        }
                        $fuPayload = (array) ($follow['json'] ?? []);
                        $fuReq->send($fuMethod, $fuUrl, ['json' => $fuPayload]);
                    } catch (\Throwable $e) {
                        if (is_dev()) {
                            _l('Webhook follow-up error', $e->getMessage());
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            if (is_dev()) {
                _l('Webhook error', $e->getMessage());
            }
        }
    }
}
