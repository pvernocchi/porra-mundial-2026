<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Native PHP template renderer. Templates are plain .php files with two
 * helpers in scope:
 *   $e($value)   -> escape for HTML output
 *   $view        -> the View instance (for `extend` / `section`)
 *
 * Supports a single layout via $view->extend('layout', ['title' => ...])
 * and named sections via $view->section('name') / $view->endSection().
 */
final class View
{
    /** @var array<int, string> */
    private array $paths;
    private Application $app;

    /** @var array<string, string> */
    private array $sections = [];
    /** @var array<int, string> */
    private array $sectionStack = [];
    private ?string $extend = null;
    /** @var array<string, mixed> */
    private array $extendData = [];

    /**
     * @param array<int, string> $paths
     */
    public function __construct(array $paths, Application $app)
    {
        $this->paths = $paths;
        $this->app   = $app;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->extend = null;
        $this->extendData = [];

        $body = $this->renderFile($template, $data);

        if ($this->extend !== null) {
            $layout = $this->extend;
            $this->extend = null;
            $this->sections['content'] = $body;
            return $this->renderFile($layout, $this->extendData);
        }
        return $body;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function extend(string $template, array $data = []): void
    {
        $this->extend = $template;
        $this->extendData = $data;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->sectionStack === []) {
            return;
        }
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = (string)ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function app(): Application
    {
        return $this->app;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->resolve($template);
        if ($file === null) {
            throw new \RuntimeException('Template not found: ' . $template);
        }

        $view = $this;
        $app  = $this->app;
        $e    = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = $this->app->csrf();

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string)ob_get_clean();
    }

    private function resolve(string $template): ?string
    {
        $rel = str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';
        foreach ($this->paths as $p) {
            $f = $p . DIRECTORY_SEPARATOR . $rel;
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
    }
}
