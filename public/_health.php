<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

header('Content-Type: text/plain; charset=UTF-8');

$projectDir = dirname(__DIR__);

require $projectDir.'/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    (new Dotenv())->bootEnv($projectDir.'/.env');
}

$env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$debug = filter_var($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? ('prod' !== $env), FILTER_VALIDATE_BOOL);

echo "Pirate Card Blog health check\n";
echo "=============================\n";
echo "PHP_VERSION: ".PHP_VERSION."\n";
echo "APP_ENV: ".$env."\n";
echo "APP_DEBUG: ".($debug ? '1' : '0')."\n";
echo "PROJECT_DIR: ".$projectDir."\n";
echo "vendor/autoload.php: ".(is_file($projectDir.'/vendor/autoload.php') ? 'yes' : 'no')."\n";
echo "var/cache writable: ".(is_writable($projectDir.'/var/cache') ? 'yes' : 'no')."\n";
echo "var/log writable: ".(is_writable($projectDir.'/var/log') ? 'yes' : 'no')."\n";
echo "public/assets/app-KS9PLn4.js: ".(is_file(__DIR__.'/assets/app-KS9PLn4.js') ? 'yes' : 'no')."\n";
echo "public/assets/js/structure.js: ".(is_file(__DIR__.'/assets/js/structure.js') ? 'yes' : 'no')."\n";

$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
echo "DATABASE_URL set: ".('' !== $databaseUrl ? 'yes' : 'no')."\n";

if ('' !== $databaseUrl) {
    $safeDatabaseUrl = preg_replace('#//([^:/@]+):([^@]+)@#', '//\1:***@', $databaseUrl);
    echo "DATABASE_URL masked: ".$safeDatabaseUrl."\n";
}

echo "\nKernel boot\n";
echo "-----------\n";

try {
    $kernel = new Kernel($env, $debug);
    $kernel->boot();
    echo "Kernel boot: OK\n";
} catch (Throwable $throwable) {
    echo "Kernel boot: ERROR\n";
    echo get_class($throwable).": ".$throwable->getMessage()."\n";
    echo $throwable->getFile().':'.$throwable->getLine()."\n";
    echo "\nTrace:\n".$throwable->getTraceAsString()."\n";
}
