<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PHP-template renderer with layouts and sections.
 *
 * In a template:
 *   <?php $this->layout('layouts/app', ['title' => 'Bookings']) ?>
 *   <?php $this->start('scripts') ?> ... <?php $this->end() ?>
 * In a layout:
 *   <?= $this->section('content') ?>
 */
final class View
{
    private const VIEW_PATH = BASE_PATH . '/app/Views/';

    /** @var array<string,string> */
    private array $sections = [];
    /** @var list<string> */
    private array $sectionStack = [];
    private ?string $layoutName = null;
    /** @var array<string,mixed> */
    private array $layoutData = [];
    /** @var array<string,mixed> */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public static function make(string $template, array $data = []): string
    {
        return (new self())->render($template, $data);
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $data = array_merge(self::$shared, $data);
        $content = $this->renderFile($template, $data);

        if ($this->layoutName !== null) {
            $layout = $this->layoutName;
            $this->layoutName = null;
            $this->sections['content'] = $content;
            return $this->render($layout, array_merge($data, $this->layoutData));
        }
        return $content;
    }

    /** @param array<string,mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $template = str_replace(['..', '\\'], ['', '/'], $template);
        $file = self::VIEW_PATH . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View [{$template}] not found.");
        }
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public function layout(string $name, array $data = []): void
    {
        $this->layoutName = $name;
        $this->layoutData = $data;
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \LogicException('end() called without start().');
        }
        $this->sections[$name] = ($this->sections[$name] ?? '') . (string) ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $partial, array $data = []): string
    {
        return (new self())->renderFile($partial, array_merge(self::$shared, $data));
    }
}
