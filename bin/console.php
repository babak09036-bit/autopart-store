<?php
declare(strict_types=1);

/*
 * AutoPart CLI
 *   php bin/console.php db:install          Import database/schema.sql and database/seed.sql (empty database only)
 *   php bin/console.php admin:create        Create an administrator (prompts for a password; nothing is hard-coded)
 *   php bin/console.php blog:publish-scheduled   Publish scheduled posts whose time has come (run from cron)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\BlogService;

$cmd = $argv[1] ?? '';

function ask(string $q, bool $hidden = false): string
{
    echo $q;
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        echo PHP_EOL;
        return $v;
    }
    return trim((string) fgets(STDIN));
}

switch ($cmd) {
    case 'db:install':
        if ((int) Database::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()') > 0) {
            fwrite(STDERR, "Database is not empty; refusing to overwrite.\n");
            exit(1);
        }
        foreach (['schema.sql', 'seed.sql'] as $file) {
            Database::pdo()->exec((string) file_get_contents(BASE_DIR . '/database/' . $file));
            echo "Imported {$file}\n";
        }
        break;

    case 'admin:create':
        $name = ask('Full name: ');
        $username = strtolower(ask('Username: '));
        $email = strtolower(ask('Email: '));
        $password = ask('Password (min 12 chars, letters and digits): ', true);
        if (!preg_match('/^[a-z0-9_.-]{3,60}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            fwrite(STDERR, "Invalid username, email or weak password.\n");
            exit(1);
        }
        $id = Database::insert('admin_users', ['full_name' => $name ?: $username, 'username' => $username, 'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        Database::query("INSERT INTO admin_user_roles (admin_user_id, role_id) SELECT :a, id FROM roles WHERE slug = 'super_admin'", ['a' => $id]);
        echo "Super admin #{$id} created.\n";
        break;

    case 'blog:publish-scheduled':
        echo 'Published: ' . BlogService::publishScheduled() . "\n";
        break;

    default:
        echo "Commands: db:install | admin:create | blog:publish-scheduled\n";
}
