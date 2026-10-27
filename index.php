<?php
declare(strict_types=1);

// Storefront front controller: every public page is rendered here so SEO tags come out in real HTML.
require __DIR__ . '/app/bootstrap.php';

App\Core\App::run('web.php');
