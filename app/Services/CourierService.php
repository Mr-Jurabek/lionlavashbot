<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Models\Courier;
use App\Models\Order;

/**
 * Kuryerlar: qo'shish/faollik (admin), buyurtmani tayinlash (admin), kuryer amallari.
 * Kuryer faqat o'ziga tayinlangan buyurtmani o'zgartira oladi.
 */
final class CourierService
{
    /** Tayinlash mumkin bo'lgan holatlar (yakunlanmagan, lekin kuryer olguncha). */
    public const ASSIGNABLE = ['accepted', 'preparing', 'ready', 'on_the_way'];

    public static function isActive(int $telegramId): bool
    {
        return Courier::findActiveByTelegram($telegramId) !== null;
    }

    /** @return array<string, mixed>|null */
    public static function findActiveByTelegram(int $telegramId): ?array
    {
        return Courier::findActiveByTelegram($telegramId);
    }

    /** Kuryerni qo'shadi yoki qayta faollashtiradi. */
    public static function add(int $telegramId, string $name): int
    {
        if ($telegramId <= 0 || strlen((string) $telegramId) < 5) {
            throw new ServiceException('invalid_setting');
        }
        return Courier::upsert($telegramId, mb_substr(trim($name) !== '' ? trim($name) : (string) $telegramId, 0, 100));
    }

    public static function toggle(int $courierId): void
    {
        $courier = Courier::find($courierId);
        if ($courier === null) {
            throw new ServiceException('courier_not_found');
        }
        Courier::setActive($courierId, (int) $courier['is_active'] !== 1);
    }

    /** Buyurtmani kuryerga tayinlaydi. @return array<string, mixed> yangilangan buyurtma */
    public static function assign(int $orderId, int $courierId): array
    {
        return Db::transaction(static function () use ($orderId, $courierId): array {
            $order = Order::findForUpdate($orderId);
            if ($order === null) {
                throw new ServiceException('order_not_found');
            }
            if (!in_array($order['status'], self::ASSIGNABLE, true)) {
                // yakunlangan yoki bekor qilingan buyurtmaga tayinlash mumkin emas
                throw new ServiceException('not_assignable');
            }
            $courier = Courier::find($courierId);
            if ($courier === null || (int) $courier['is_active'] !== 1) {
                throw new ServiceException('courier_not_found');
            }
            Order::setCourier($orderId, $courierId);
            return Order::find($orderId);
        });
    }

    /** @return list<array<string, mixed>> */
    public static function ownedOrders(int $courierId): array
    {
        return Order::activeForCourier($courierId);
    }

    /**
     * Kuryer amali: pickup (ready → on_the_way), deliver (on_the_way → delivered), cash (naqd oldim).
     * Buyurtma shu kuryerga tayinlangan bo'lishi shart.
     * @return array<string, mixed> yangilangan buyurtma
     */
    public static function act(int $telegramId, int $orderId, string $action): array
    {
        $courier = Courier::findActiveByTelegram($telegramId);
        if ($courier === null) {
            throw new ServiceException('forbidden');
        }
        $order = Order::find($orderId);
        if ($order === null) {
            throw new ServiceException('order_not_found');
        }
        if ((int) $order['courier_id'] !== (int) $courier['id']) {
            throw new ServiceException('not_your_order');
        }
        $by = 'courier:' . $telegramId;
        return match ($action) {
            'pickup' => OrderService::transition($orderId, 'on_the_way', $by),
            'deliver' => OrderService::transition($orderId, 'delivered', $by),
            'cash' => OrderService::markCashPaid($orderId, $by),
            default => throw new ServiceException('invalid_transition'),
        };
    }
}
