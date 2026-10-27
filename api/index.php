<?php
declare(strict_types=1);

// REST API front controller (JSON only).
require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\App::run('api.php');
