<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static array $shared = [];

    /** Let a view pass data (e.g. page scripts) up to its layout */
    public static function share(string $key, $value): void
    {
        self::$shared[$key] = ($key === 'scripts' && isset(self::$shared[$key])) ? self::$shared[$key] . $value : $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $file = APP_PATH . '/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }
        $content = self::capture($file, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture(APP_PATH . '/Views/layouts/' . $layout . '.php', self::$shared + $data + ['content' => $content]);
        clear_old();
    }

    public static function partial(string $view, array $data = []): string
    {
        return self::capture(APP_PATH . '/Views/' . $view . '.php', $data);
    }

    private static function capture(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
