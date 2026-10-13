<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class Category
{
    /** @return list<array<string, mixed>> */
    public static function allActive(): array
    {
        return Db::all('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, id');
    }

    /** @return array<string, mixed>|null */
    public static function findActive(int $id): ?array
    {
        return Db::one('SELECT * FROM categories WHERE id = ? AND is_active = 1', [$id]);
    }
}
