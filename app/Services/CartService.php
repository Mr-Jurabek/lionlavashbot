<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;

final class CartService
{
    public const MAX_QUANTITY = 50;

    /** Savatga qo'shadi; yangi miqdorni qaytaradi. */
    public static function add(int $userId, int $productId, int $quantity): int
    {
        if (Product::findActive($productId) === null) {
            throw new ServiceException('product_unavailable');
        }
        $new = self::clamp(Cart::quantity($userId, $productId) + max(1, $quantity));
        Cart::setQuantity($userId, $productId, $new);
        return $new;
    }

    /** Miqdorni delta'ga o'zgartiradi (0 bo'lsa — o'chiradi). */
    public static function change(int $userId, int $productId, int $delta): int
    {
        $current = Cart::quantity($userId, $productId);
        if ($current === 0) {
            return 0;
        }
        $new = self::clamp($current + $delta);
        Cart::setQuantity($userId, $productId, $new);
        return $new;
    }

    public static function remove(int $userId, int $productId): void
    {
        Cart::remove($userId, $productId);
    }

    public static function clear(int $userId): void
    {
        Cart::clear($userId);
    }

    /**
     * Savat tarkibi va summalar (joriy narxlar bo'yicha).
     * @return array{items: list<array<string, mixed>>, items_total: int, delivery_fee: int, total: int}
     */
    public static function summary(int $userId): array
    {
        $items = [];
        $itemsTotal = 0;
        foreach (Cart::items($userId) as $row) {
            $row['line_total'] = (int) $row['price'] * (int) $row['quantity'];
            $itemsTotal += $row['line_total'];
            $items[] = $row;
        }
        $fee = $items ? DeliveryFee::forItemsTotal($itemsTotal) : 0;
        return [
            'items' => $items,
            'items_total' => $itemsTotal,
            'delivery_fee' => $fee,
            'total' => $itemsTotal + $fee,
        ];
    }

    public static function clamp(int $quantity): int
    {
        return max(0, min(self::MAX_QUANTITY, $quantity));
    }
}
