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

    /** Faol-nofaolligidan qat'i nazar (admin uchun). */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM products WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public static function allForAdmin(): array
    {
        return Db::all('SELECT * FROM products ORDER BY category_id, sort_order, id');
    }

    public static function setActive(int $id, bool $active): void
    {
        Db::run('UPDATE products SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function setPrice(int $id, int $price): void
    {
        Db::run('UPDATE products SET price = ? WHERE id = ?', [$price, $id]);
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
