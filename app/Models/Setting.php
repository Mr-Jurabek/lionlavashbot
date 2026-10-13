<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class Setting
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function get(string $name, string $default = ''): string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT name, value FROM settings') as $row) {
                self::$cache[$row['name']] = (string) $row['value'];
            }
        }
        return self::$cache[$name] ?? $default;
    }

    public static function int(string $name, int $default = 0): int
    {
        $value = self::get($name);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $name, string $value): void
    {
        Db::run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
