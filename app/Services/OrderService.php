<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

/**
 * Buyurtma yaratish va holat o'tishlari — yagona markaz.
 * Mijoz boti, keyingi sikllarda admin/kuryer botlari va veb-panel faqat shu sinf orqali ishlaydi.
 */
final class OrderService
{
    public const TRANSITIONS = [
        'new' => ['awaiting_payment', 'accepted', 'cancelled'],
        'awaiting_payment' => ['payment_review', 'cancelled'],
        'payment_review' => ['accepted', 'awaiting_payment', 'cancelled'],
        'accepted' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['on_the_way', 'cancelled'],
        'on_the_way' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public const ADDRESS_MIN = 3;
    public const ADDRESS_MAX = 255;
    public const COMMENT_MAX = 500;

    /** @var list<callable(array, string, string, string): void> */
    private static array $listeners = [];

    /**
     * O'tish tinglovchisi: fn(array $order, string $from, string $to, string $changedBy).
     * Tranzaksiya commit bo'lgandan keyin chaqiriladi.
     */
    public static function onTransition(callable $listener): void
    {
        self::$listeners[] = $listener;
    }

    public static function resetListeners(): void
    {
        self::$listeners = [];
    }

    public static function canTransition(array $order, string $to): bool
    {
        $from = (string) $order['status'];
        return in_array($to, self::TRANSITIONS[$from] ?? [], true)
            && PaymentService::isTransitionAllowed((string) $order['payment_method'], $from, $to);
    }

    public static function isActive(string $status): bool
    {
        return (self::TRANSITIONS[$status] ?? []) !== [];
    }

    /**
     * Savatdan buyurtma yaratadi va savatni tozalaydi.
     * Foydalanuvchi qatori qulflanadi: «Tasdiqlash» ikki marta bosilsa, ikkinchi so'rov bo'sh savatga duch keladi.
     *
     * @param array{address_text?: ?string, latitude?: ?float, longitude?: ?float, comment?: ?string, payment_method: string} $checkout
     * @return int buyurtma ID
     */
    public static function createFromCart(int $userId, array $checkout, string $changedBy): int
    {
        $method = (string) ($checkout['payment_method'] ?? '');
        if (!PaymentService::isValid($method)) {
            throw new ServiceException('invalid_payment_method');
        }
        $address = self::cleanText($checkout['address_text'] ?? null, self::ADDRESS_MAX);
        $lat = isset($checkout['latitude']) ? (float) $checkout['latitude'] : null;
        $lng = isset($checkout['longitude']) ? (float) $checkout['longitude'] : null;
        if ($address === null && ($lat === null || $lng === null)) {
            throw new ServiceException('address_required');
        }
        $comment = self::cleanText($checkout['comment'] ?? null, self::COMMENT_MAX);
        if (!WorkHours::isOpen()) {
            throw new ServiceException('closed');
        }

        return Db::transaction(static function () use ($userId, $method, $address, $lat, $lng, $comment, $changedBy): int {
            User::lock($userId);
            $user = User::find($userId);
            if ($user === null || empty($user['phone'])) {
                throw new ServiceException('phone_required');
            }
            $summary = CartService::summary($userId);
            if ($summary['items'] === []) {
                throw new ServiceException('cart_empty');
            }

            $orderId = Order::create([
                'user_id' => $userId,
                'status' => 'new',
                'payment_method' => $method,
                'items_total' => $summary['items_total'],
                'delivery_fee' => $summary['delivery_fee'],
                'total' => $summary['total'],
                'address_text' => $address,
                'latitude' => $lat,
                'longitude' => $lng,
                'phone' => $user['phone'],
                'comment' => $comment,
            ]);
            foreach ($summary['items'] as $item) {
                OrderItem::create(
                    $orderId,
                    (int) $item['product_id'],
                    (string) $item['name_uz'],
                    (int) $item['price'],
                    (int) $item['quantity']
                );
            }
            Order::logStatus($orderId, null, 'new', $changedBy);
            Cart::clear($userId);

            $next = PaymentService::statusAfterCreate($method);
            if ($next !== null) {
                self::transition($orderId, $next, 'system');
            }
            return $orderId;
        });
    }

    /**
     * Holatni o'zgartiradi. Ruxsat etilmagan o'tishda ServiceException('invalid_transition').
     * @return array<string, mixed> yangilangan buyurtma
     */
    public static function transition(int $orderId, string $to, string $changedBy): array
    {
        $result = Db::transaction(static function () use ($orderId, $to, $changedBy): array {
            $order = Order::findForUpdate($orderId);
            if ($order === null) {
                throw new ServiceException('order_not_found');
            }
            $from = (string) $order['status'];
            if (!self::canTransition($order, $to)) {
                throw new ServiceException('invalid_transition', "{$from} → {$to} ruxsat etilmagan");
            }
            $paymentStatus = PaymentService::paymentStatusOnTransition($order, $from, $to);
            Order::updateStatus($orderId, $to, $paymentStatus);
            Order::logStatus($orderId, $from, $to, $changedBy);

            $updated = Order::find($orderId);
            Db::afterCommit(static function () use ($updated, $from, $to, $changedBy): void {
                foreach (self::$listeners as $listener) {
                    try {
                        $listener($updated, $from, $to, $changedBy);
                    } catch (\Throwable $e) {
                        Logger::exception($e, ['order_id' => $updated['id']]);
                    }
                }
            });
            return $updated;
        });
        return $result;
    }

    /**
     * Karta to'lovi cheki: buyurtma shu foydalanuvchiniki va `awaiting_payment` holatida bo'lishi shart.
     * @return array<string, mixed>
     */
    public static function attachReceipt(int $orderId, int $userId, string $fileId, string $changedBy): array
    {
        return Db::transaction(static function () use ($orderId, $userId, $fileId, $changedBy): array {
            $order = Order::findForUpdate($orderId);
            if ($order === null || (int) $order['user_id'] !== $userId) {
                throw new ServiceException('order_not_found');
            }
            if ($order['status'] !== 'awaiting_payment' || !PaymentService::requiresReceipt((string) $order['payment_method'])) {
                throw new ServiceException('receipt_not_expected');
            }
            Order::setReceipt($orderId, $fileId);
            return self::transition($orderId, 'payment_review', $changedBy);
        });
    }

    /** @return array{order: array<string, mixed>, items: list<array<string, mixed>>}|null */
    public static function details(int $orderId): ?array
    {
        $order = Order::find($orderId);
        return $order === null ? null : ['order' => $order, 'items' => OrderItem::byOrder($orderId)];
    }

    /** @return list<array<string, mixed>> */
    public static function recentForUser(int $userId, int $limit = 10): array
    {
        return Order::recentByUser($userId, $limit);
    }

    private static function cleanText(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
