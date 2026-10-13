<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * To'lov usullari va to'lov holati qoidalari.
 * Online to'lov (Click/Payme) qo'shilganda: yangi usul konstantasi, METHODS ga qo'shish,
 * statusAfterCreate() va paymentStatusOnTransition() ga uning qoidasini yozish kifoya.
 */
final class PaymentService
{
    public const CASH = 'cash';
    public const CARD = 'card';
    public const METHODS = [self::CASH, self::CARD];

    public static function isValid(string $method): bool
    {
        return in_array($method, self::METHODS, true);
    }

    /** Buyurtma yaratilgach avtomatik o'tiladigan holat (yo'q bo'lsa — `new` da qoladi). */
    public static function statusAfterCreate(string $method): ?string
    {
        return $method === self::CARD ? 'awaiting_payment' : null;
    }

    public static function requiresReceipt(string $method): bool
    {
        return $method === self::CARD;
    }

    /** Usulga ko'ra taqiqlangan holatlar (OrderService o'tish jadvaliga qo'shimcha). */
    public static function isTransitionAllowed(string $method, string $from, string $to): bool
    {
        $cardOnly = ['awaiting_payment', 'payment_review'];
        if ($method !== self::CARD && in_array($to, $cardOnly, true)) {
            return false;
        }
        // Karta buyurtmasi to'lov tekshirilmasdan qabul qilinmaydi
        if ($method === self::CARD && $from === 'new' && $to === 'accepted') {
            return false;
        }
        return true;
    }

    /** O'tishdan keyingi payment_status. */
    public static function paymentStatusOnTransition(array $order, string $from, string $to): string
    {
        $current = (string) $order['payment_status'];
        return match (true) {
            $to === 'payment_review' => 'pending_review',
            $from === 'payment_review' && $to === 'accepted' => 'paid',
            $from === 'payment_review' && $to === 'awaiting_payment' => 'rejected',
            $order['payment_method'] === self::CASH && $to === 'delivered' => 'paid',
            default => $current,
        };
    }

    /** @return array{number: string, owner: string} */
    public static function cardDetails(): array
    {
        return [
            'number' => Setting::get('card_number'),
            'owner' => Setting::get('card_owner'),
        ];
    }
}
