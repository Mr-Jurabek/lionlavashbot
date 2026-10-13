<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Yetkazib berish narxi: settings.delivery_fee, lekin summa settings.free_delivery_from
 * ga teng yoki undan ko'p bo'lsa — bepul. free_delivery_from = 0 bo'lsa, bepul yetkazish yo'q.
 */
final class DeliveryFee
{
    public static function forItemsTotal(int $itemsTotal): int
    {
        $freeFrom = Setting::int('free_delivery_from');
        if ($freeFrom > 0 && $itemsTotal >= $freeFrom) {
            return 0;
        }
        return max(0, Setting::int('delivery_fee'));
    }

    public static function freeFrom(): int
    {
        return Setting::int('free_delivery_from');
    }
}
