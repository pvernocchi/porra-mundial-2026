<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Captcha verifier supporting:
 *   - none
 *   - recaptcha_v2 (Google reCAPTCHA v2)
 *   - recaptcha_v3 (Google reCAPTCHA v3 — score threshold)
 *   - turnstile    (Cloudflare Turnstile)
 *
 * Configuration is stored in the `settings` table under the key
 * `security.captcha`:
 *   { provider, site_key, secret_key, threshold }
 */
final class Captcha
{
    public function __construct(private Settings $settings)
    {
    }

    /** @return array{provider:string, site_key:string, secret_key:string, threshold:float} */
    public function config(): array
    {
        $cfg = (array)$this->settings->get('security.captcha', []);
        return [
            'provider'   => (string)($cfg['provider']   ?? 'none'),
            'site_key'   => (string)($cfg['site_key']   ?? ''),
            'secret_key' => (string)($cfg['secret_key'] ?? ''),
            'threshold'  => (float) ($cfg['threshold']  ?? 0.5),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->config()['provider'] !== 'none';
    }

    public function siteKey(): string
    {
        return $this->config()['site_key'];
    }

    public function provider(): string
    {
        return $this->config()['provider'];
    }

    /**
     * Renders the script tag and the placeholder needed for the chosen
     * provider. For v3, the page must call captchaExecute(actionName)
     * before submitting; the helper below shims that.
     */
    public function widgetHtml(string $action = 'submit'): string
    {
        $cfg = $this->config();
        $key = htmlspecialchars($cfg['site_key'], ENT_QUOTES);
        return match ($cfg['provider']) {
            'recaptcha_v2' =>
                '<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
                . '<div class="g-recaptcha mt-2" data-sitekey="' . $key . '"></div>',
            'recaptcha_v3' =>
                '<script src="https://www.google.com/recaptcha/api.js?render=' . $key . '"></script>'
                . '<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">'
                . '<script>document.addEventListener("submit",function(e){var f=e.target;if(f.dataset.captchaDone)return;e.preventDefault();grecaptcha.ready(function(){grecaptcha.execute("' . $key . '",{action:' . json_encode($action) . '}).then(function(t){f.querySelector("#g-recaptcha-response").value=t;f.dataset.captchaDone="1";f.submit();});});},true);</script>',
            'turnstile' =>
                '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
                . '<div class="cf-turnstile mt-2" data-sitekey="' . $key . '"></div>',
            default => '',
        };
    }

    /**
     * Verify the captcha submitted with a request.
     */
    public function verify(string $token, ?string $remoteIp = null): bool
    {
        $cfg = $this->config();
        if ($cfg['provider'] === 'none') {
            return true;
        }
        if ($token === '' || $cfg['secret_key'] === '') {
            return false;
        }
        $endpoint = match ($cfg['provider']) {
            'recaptcha_v2', 'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
            'turnstile'                    => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            default                        => null,
        };
        if ($endpoint === null) {
            return false;
        }
        $resp = $this->httpPost($endpoint, [
            'secret'   => $cfg['secret_key'],
            'response' => $token,
            'remoteip' => $remoteIp ?? '',
        ]);
        if (!is_array($resp) || empty($resp['success'])) {
            return false;
        }
        if ($cfg['provider'] === 'recaptcha_v3') {
            $score = (float)($resp['score'] ?? 0);
            return $score >= $cfg['threshold'];
        }
        return true;
    }

    /**
     * @param array<string,string> $data
     * @return array<string,mixed>|null
     */
    private function httpPost(string $url, array $data): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $err  = curl_errno($ch);
        curl_close($ch);
        if ($err !== 0 || !is_string($body)) {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
