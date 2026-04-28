<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mail abstraction.
 *
 * Driver selection (in order):
 *   1. SMTP via PHPMailer if `phpmailer/phpmailer` is autoloadable AND
 *      SMTP settings are configured.
 *   2. Built-in lightweight SMTP client (SmtpClient) as a fallback when
 *      PHPMailer is not bundled.
 *   3. File "spool" driver — writes the message as a .eml under
 *      storage/mail/ for development / when SMTP isn't configured yet.
 *
 * Settings live in the `settings` table under `mail.smtp`:
 *   { host, port, encryption (none|ssl|tls), auth (bool),
 *     username, password_enc, from_email, from_name, reply_to }
 *
 * Passwords are stored encrypted with the application APP_KEY.
 */
final class Mail
{
    public function __construct(private Application $app)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function smtpConfig(): array
    {
        $cfg = (array)$this->app->settings()->get('mail.smtp', []);
        return [
            'host'       => (string)($cfg['host']       ?? ''),
            'port'       => (int)   ($cfg['port']       ?? 587),
            'encryption' => (string)($cfg['encryption'] ?? 'tls'),
            'auth'       => (bool)  ($cfg['auth']       ?? true),
            'username'   => (string)($cfg['username']   ?? ''),
            'password'   => $this->decryptPassword((string)($cfg['password_enc'] ?? '')),
            'from_email' => (string)($cfg['from_email'] ?? ''),
            'from_name'  => (string)($cfg['from_name']  ?? ''),
            'reply_to'   => (string)($cfg['reply_to']   ?? ''),
        ];
    }

    public function isConfigured(): bool
    {
        $c = $this->smtpConfig();
        return $c['host'] !== '' && $c['from_email'] !== '';
    }

    /**
     * Sends an email. Returns true on success, false otherwise.
     * On failure, error is available via lastError().
     */
    private string $lastError = '';

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $this->lastError = '';

        if (!$this->isConfigured()) {
            return $this->spool($to, $subject, $htmlBody, $textBody);
        }

        $cfg = $this->smtpConfig();

        // Prefer PHPMailer when available (bundled by composer in releases).
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return $this->sendViaPhpMailer($cfg, $to, $subject, $htmlBody, $textBody);
        }

        // Fallback: built-in SMTP client.
        try {
            $client = new SmtpClient(
                host: $cfg['host'],
                port: $cfg['port'],
                encryption: $cfg['encryption'],
                username: $cfg['auth'] ? $cfg['username'] : null,
                password: $cfg['auth'] ? $cfg['password'] : null,
            );
            $client->send(
                fromEmail: $cfg['from_email'],
                fromName:  $cfg['from_name'] ?: $cfg['from_email'],
                to:        $to,
                subject:   $subject,
                htmlBody:  $htmlBody,
                textBody:  $textBody ?? strip_tags($htmlBody),
                replyTo:   $cfg['reply_to'] ?: null,
            );
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->logError($to, $subject, $e);
            return false;
        }
    }

    /**
     * Encrypts a SMTP password for storage. If the application has no
     * key (e.g. during install), stores plaintext as a base64 string
     * with a marker so we can decrypt it later.
     */
    public function encryptPassword(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $key = $this->app->appKey();
        if ($key === '') {
            return 'plain:' . base64_encode($plain);
        }
        return 'enc:' . (new Crypto($key))->encrypt($plain);
    }

    public function decryptPassword(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (str_starts_with($stored, 'plain:')) {
            $bin = base64_decode(substr($stored, 6), true);
            return $bin === false ? '' : $bin;
        }
        if (str_starts_with($stored, 'enc:')) {
            $key = $this->app->appKey();
            if ($key === '') {
                return '';
            }
            return (string)(new Crypto($key))->decrypt(substr($stored, 4));
        }
        return $stored; // legacy plaintext
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function sendViaPhpMailer(array $cfg, string $to, string $subject, string $html, ?string $text): bool
    {
        try {
            /** @psalm-suppress UndefinedClass */
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = (string)$cfg['host'];
            $mailer->Port       = (int)$cfg['port'];
            $mailer->SMTPAuth   = (bool)$cfg['auth'];
            $mailer->Username   = (string)$cfg['username'];
            $mailer->Password   = (string)$cfg['password'];
            $mailer->CharSet    = 'UTF-8';
            $mailer->SMTPSecure = match ((string)$cfg['encryption']) {
                'ssl'   => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
                'tls'   => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };
            $mailer->setFrom((string)$cfg['from_email'], (string)$cfg['from_name']);
            if (!empty($cfg['reply_to'])) {
                $mailer->addReplyTo((string)$cfg['reply_to']);
            }
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body    = $html;
            $mailer->AltBody = $text ?? strip_tags($html);
            $mailer->send();
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->logError($to, $subject, $e);
            return false;
        }
    }

    private function spool(string $to, string $subject, string $htmlBody, ?string $textBody): bool
    {
        $dir = $this->app->path('storage/mail');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = $dir . '/' . gmdate('Ymd_His') . '_' . substr(sha1($to . $subject . microtime(true)), 0, 8) . '.eml';
        $message = "To: {$to}\r\nSubject: {$subject}\r\nDate: " . gmdate('r') . "\r\n"
            . "Content-Type: text/html; charset=utf-8\r\n\r\n"
            . $htmlBody
            . "\r\n\r\n----\r\n" . ($textBody ?? strip_tags($htmlBody));
        $written = @file_put_contents($filename, $message);
        if ($written === false) {
            $this->lastError = 'Could not spool email to ' . $filename;
            return false;
        }
        return true;
    }

    private function logError(string $to, string $subject, \Throwable $e): void
    {
        $line = sprintf(
            "[%s] MAIL_FAIL to=%s subject=%s error=%s\n",
            gmdate('c'), $to, $subject, $e->getMessage()
        );
        @file_put_contents($this->app->path('storage/logs/mail.log'), $line, FILE_APPEND);
    }
}
