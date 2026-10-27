<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Plain-PHP templates in app/Views. Templates must escape output with e(). */
final class View
{
    public static function render(string $template, array $data = []): string
    {
        $file = BASE_DIR . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function errorPage(int $status, string $message): Response
    {
        try {
            $controller = new \App\Controllers\Web\PageController();
            return $controller->error($status, $message);
        } catch (Throwable $e) {
            Logger::error('Error page failed: ' . $e->getMessage());
            return Response::html('<!doctype html><meta charset="utf-8"><title>خطا</title><p dir="rtl">' . e($message) . '</p>', $status);
        }
    }
}
