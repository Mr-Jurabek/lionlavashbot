<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Env;
use App\Core\I18n;
use App\Core\Logger;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';
Autoloader::register(BASE_PATH . '/app');

Logger::init(BASE_PATH . '/storage/logs');
I18n::init(BASE_PATH . '/lang');

if (!defined('LIONLAVASH_SKIP_ENV')) {
    Env::load(BASE_PATH . '/.env');
}

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Tashkent'));

$debug = Env::bool('APP_DEBUG');
error_reporting(E_ALL);
ini_set('display_errors', $debug && PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
