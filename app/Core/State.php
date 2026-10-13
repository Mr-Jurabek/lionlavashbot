<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Foydalanuvchi suhbat holati (state + JSON ma'lumot).
 * Jadval nomi parametr — keyingi sikllarda kuryer/admin jadvallari uchun ham ishlatiladi.
 */
final class State
{
    private const TABLES = ['users'];

    public function __construct(private readonly string $table = 'users')
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException('Ruxsat etilmagan jadval: ' . $table);
        }
    }

    public function set(int $id, string $state, array $data = []): void
    {
        Db::run(
            "UPDATE `{$this->table}` SET state = ?, state_data = ? WHERE id = ?",
            [$state, $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null, $id]
        );
    }

    /** Mavjud ma'lumotga qo'shib, holatni o'zgartiradi. */
    public function merge(int $id, string $state, array $data): void
    {
        $this->set($id, $state, array_merge($this->data($id), $data));
    }

    public function data(int $id): array
    {
        $row = Db::one("SELECT state_data FROM `{$this->table}` WHERE id = ?", [$id]);
        $decoded = $row && $row['state_data'] !== null ? json_decode((string) $row['state_data'], true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public static function decode(?string $json): array
    {
        $decoded = $json !== null ? json_decode($json, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
