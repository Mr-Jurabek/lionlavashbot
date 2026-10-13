<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class User
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public static function findByTelegramId(int $telegramId): ?array
    {
        return Db::one('SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
    }

    /** Topadi yoki yaratadi; ismni yangilab turadi. */
    public static function touch(int $telegramId, string $firstName): array
    {
        $firstName = mb_substr($firstName, 0, 255);
        Db::run(
            'INSERT INTO users (telegram_id, first_name) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE first_name = VALUES(first_name)',
            [$telegramId, $firstName]
        );
        return self::findByTelegramId($telegramId);
    }

    public static function setLang(int $id, string $lang): void
    {
        Db::run('UPDATE users SET lang = ? WHERE id = ?', [$lang, $id]);
    }

    public static function setPhone(int $id, string $phone): void
    {
        Db::run('UPDATE users SET phone = ? WHERE id = ?', [$phone, $id]);
    }

    /** Tranzaksiya ichida qatorni qulflaydi (parallel so'rovlarni ketma-ket qilish uchun). */
    public static function lock(int $id): void
    {
        Db::one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$id]);
    }
}
