<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\RedirectService;
use Throwable;

/** HTTP kernel shared by the web and API front controllers. */
final class App
{
    public static function run(string $routesFile): void
    {
        $request = Request::capture();
        try {
            Session::start();
            if (!$request->isApi() && in_array($request->method, ['GET', 'HEAD'], true)) {
                $redirect = RedirectService::match($request->path);
                if ($redirect) {
                    $redirect->send();
                    return;
                }
            }
            $router = new Router();
            (require BASE_DIR . '/routes/' . $routesFile)($router);
            $response = $router->dispatch($request);
        } catch (Throwable $e) {
            $response = ErrorHandler::toResponse($e, $request);
        }
        $response->send();
    }
}
