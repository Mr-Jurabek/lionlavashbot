<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class Courier
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM couriers WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public static function findActiveByTelegram(int $telegramId): ?array
    {
        return Db::one('SELECT * FROM couriers WHERE telegram_id = ? AND is_active = 1', [$telegramId]);
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT * FROM couriers ORDER BY is_active DESC, name, id');
    }

    /** @return list<array<string, mixed>> */
    public static function allActive(): array
    {
        return Db::all('SELECT * FROM couriers WHERE is_active = 1 ORDER BY name, id');
    }

    /** Yangi kuryer yaratadi yoki mavjudini faollashtiradi. Qaytaradi: kuryer ID si. */
    public static function upsert(int $telegramId, string $name): int
    {
        Db::run(
            'INSERT INTO couriers (telegram_id, name, is_active) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1',
            [$telegramId, $name]
        );
        return (int) Db::one('SELECT id FROM couriers WHERE telegram_id = ?', [$telegramId])['id'];
    }

    public static function setActive(int $id, bool $active): void
    {
        Db::run('UPDATE couriers SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }
}
