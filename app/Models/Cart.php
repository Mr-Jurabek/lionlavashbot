<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * cart_items jadvali. Har foydalanuvchida bitta savat: (user_id, product_id) unikal.
 */
final class Cart
{
    /**
     * Faqat faol mahsulotlar, joriy narx bilan.
     * @return list<array<string, mixed>>
     */
    public static function items(int $userId): array
    {
        return Db::all(
            'SELECT ci.product_id, ci.quantity, p.name_uz, p.name_ru, p.price
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id AND p.is_active = 1
             JOIN categories c ON c.id = p.category_id AND c.is_active = 1
             WHERE ci.user_id = ?
             ORDER BY ci.id',
            [$userId]
        );
    }

    public static function quantity(int $userId, int $productId): int
    {
        $row = Db::one('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
        return $row ? (int) $row['quantity'] : 0;
    }

    public static function setQuantity(int $userId, int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            self::remove($userId, $productId);
            return;
        }
        Db::run(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
            [$userId, $productId, $quantity]
        );
    }

    public static function remove(int $userId, int $productId): void
    {
        Db::run('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
    }

    public static function clear(int $userId): void
    {
        Db::run('DELETE FROM cart_items WHERE user_id = ?', [$userId]);
    }
}
