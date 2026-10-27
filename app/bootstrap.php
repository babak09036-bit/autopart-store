<?php
declare(strict_types=1);

/*
 * Application bootstrap: autoloading, environment, configuration, error handling.
 * Every entry point (index.php, api/index.php, admin/index.php, bin/console.php) requires this file.
 */

define('BASE_DIR', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = BASE_DIR . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_DIR . '/app/Helpers/helpers.php';

App\Core\Env::load(BASE_DIR . '/.env');
App\Core\Config::load([
    'app' => require BASE_DIR . '/config/app.php',
    'database' => require BASE_DIR . '/config/database.php',
]);

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
