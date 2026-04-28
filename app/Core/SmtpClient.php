<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal SMTP client used as a fallback when PHPMailer is not bundled.
 *
 * Supports:
 *   - Plain text + HTML body (multipart/alternative)
 *   - SSL (smtps), STARTTLS, or no encryption
 *   - LOGIN authentication
 *
 * It is intentionally compact; for advanced use (attachments, OAuth2,
 * embedded images, DKIM signing, etc.) install phpmailer/phpmailer.
 */
final class SmtpClient
{
    /** @var resource|false */
    private $socket = false;

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption = 'tls', // none|ssl|tls
        private ?string $username = null,
        private ?string $password = null,
        private int $timeout = 15,
    ) {
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $replyTo = null,
    ): void {
        $this->connect();
        try {
            $this->ehlo();

            if ($this->encryption === 'tls') {
                $this->cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto(
                    $this->socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                )) {
                    throw new \RuntimeException('Could not enable STARTTLS encryption');
                }
                $this->ehlo();
            }

            if ($this->username !== null && $this->password !== null) {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($this->username), 334);
                $this->cmd(base64_encode($this->password), 235);
            }

            $this->cmd('MAIL FROM:<' . $fromEmail . '>', 250);
            $this->cmd('RCPT TO:<' . $to . '>', 250);
            $this->cmd('DATA', 354);

            $boundary = 'b' . bin2hex(random_bytes(8));
            $headers  = [
                'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>',
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Date: ' . gmdate('r'),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($this->host ?: 'localhost') . '>',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ];
            if ($replyTo !== null && $replyTo !== '') {
                $headers[] = 'Reply-To: <' . $replyTo . '>';
            }
            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . self::dotStuff($textBody) . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . self::dotStuff($htmlBody) . "\r\n"
                . "--{$boundary}--\r\n";

            $this->writeRaw(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
            $this->expect(250);

            $this->cmd('QUIT', 221);
        } finally {
            if (is_resource($this->socket)) {
                @fclose($this->socket);
            }
        }
    }

    private function connect(): void
    {
        $hostUri = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create();
        $errno   = 0;
        $errstr  = '';
        $socket  = @stream_socket_client($hostUri, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed to {$hostUri}: {$errstr}");
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
        $this->expect(220);
    }

    private function ehlo(): void
    {
        $local = gethostname() ?: 'localhost';
        $this->cmd('EHLO ' . $local, 250);
    }

    private function cmd(string $line, int $expected): void
    {
        $this->writeRaw($line);
        $this->expect($expected);
    }

    private function writeRaw(string $line): void
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('SMTP socket is not open');
        }
        $written = @fwrite($this->socket, $line . "\r\n");
        if ($written === false) {
            throw new \RuntimeException('SMTP write failed');
        }
    }

    private function expect(int $code): string
    {
        $all = '';
        while (is_resource($this->socket)) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                throw new \RuntimeException('SMTP read failed');
            }
            $all .= $line;
            // Multi-line: 250-foo\r\n250 bar
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $actual = (int)substr(ltrim($all), 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException(sprintf('SMTP expected %d but got: %s', $code, trim($all)));
        }
        return $all;
    }

    private function encodeHeader(string $text): string
    {
        if (preg_match('/[^\x20-\x7E]/', $text) === 1) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }
        return $text;
    }

    private static function dotStuff(string $body): string
    {
        $body = preg_replace("/\r\n|\r|\n/", "\r\n", $body) ?? $body;
        return preg_replace("/^\\./m", "..", $body) ?? $body;
    }
}
