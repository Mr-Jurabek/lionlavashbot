<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class OrderItem
{
    public static function create(int $orderId, ?int $productId, string $name, int $price, int $quantity): void
    {
        Db::run(
            'INSERT INTO order_items (order_id, product_id, name_snapshot, price_snapshot, quantity)
             VALUES (?, ?, ?, ?, ?)',
            [$orderId, $productId, mb_substr($name, 0, 150), $price, $quantity]
        );
    }

    /** @return list<array<string, mixed>> */
    public static function byOrder(int $orderId): array
    {
        return Db::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }
}
