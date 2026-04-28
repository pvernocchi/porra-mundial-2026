<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status = 200;
    private string $body = '';
    /** @var array<string, string> */
    private array $headers = [];

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function json(mixed $data, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        return $this;
    }

    public function html(string $html, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        $this->body = $html;
        return $this;
    }

    public function redirect(string $url, int $status = 302): self
    {
        $this->status = $status;
        $this->headers['Location'] = $url;
        $this->body = '';
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            // Default security headers (idempotent)
            $defaults = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options'        => 'SAMEORIGIN',
                'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            ];
            foreach ($defaults as $h => $v) {
                if (!isset($this->headers[$h])) {
                    header($h . ': ' . $v);
                }
            }
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
