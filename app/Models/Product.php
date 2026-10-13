<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class Product
{
    /** @return list<array<string, mixed>> */
    public static function activeByCategory(int $categoryId): array
    {
        return Db::all(
            'SELECT * FROM products WHERE category_id = ? AND is_active = 1 ORDER BY sort_order, id',
            [$categoryId]
        );
    }

    /** Mahsulot va uning kategoriyasi faol bo'lsa qaytaradi. */
    public static function findActive(int $id): ?array
    {
        return Db::one(
            'SELECT p.* FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.is_active = 1 AND c.is_active = 1',
            [$id]
        );
    }
}
