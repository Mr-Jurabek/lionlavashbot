<?php

declare(strict_types=1);

namespace App\Core;

/**
 * storage/logs/app-YYYY-MM-DD.log ga yozadi.
 */
final class Logger
{
    private static string $dir = '';

    public static function init(string $dir): void
    {
        self::$dir = rtrim($dir, '/\\');
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function exception(\Throwable $e, array $context = []): void
    {
        self::error(get_class($e) . ': ' . $e->getMessage(), $context + [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (self::$dir === '') {
            error_log("[{$level}] {$message}");
            return;
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents(self::$dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
