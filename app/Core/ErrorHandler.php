<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/** Converts uncaught errors to safe responses; technical details go to the log, never to clients in production. */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', Config::get('app.debug') && PHP_SAPI === 'cli' ? '1' : '0');
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
    }

    public static function toResponse(Throwable $e, Request $request): Response
    {
        if ($e instanceof HttpException) {
            if ($request->isApi()) {
                return Response::error($e->getMessage(), $e->status, $e->errors);
            }
            return View::errorPage($e->status, $e->getMessage());
        }

        Logger::error(get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'path' => $request->path,
            'method' => $request->method,
        ]);
        $debug = (bool) Config::get('app.debug') && Config::get('app.env') !== 'production';
        $message = $debug ? 'خطای داخلی: ' . $e->getMessage() : 'خطایی رخ داده است؛ لطفاً بعداً دوباره تلاش کنید.';
        if ($request->isApi()) {
            return Response::error($message, 500);
        }
        return View::errorPage(500, $message);
    }
}
