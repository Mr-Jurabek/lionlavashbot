<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Admin paneldan o'zgartiriladigan sozlamalar va ularning tekshiruv qoidalari.
 * admin_tg_id bu yerda emas — u AdminAccess::change() orqali, tasdiqlash bilan o'zgaradi.
 */
final class SettingsService
{
    /**
     * type: int | float | text. Qoidalar: min/max (son), max/pattern (matn).
     * strip_at / strip_spaces — kiritilgandan keyin belgilarni olib tashlaydi.
     */
    public const RULES = [
        'delivery_fee' => ['label' => "Yetkazish narxi (so'm)", 'type' => 'int', 'min' => 0, 'max' => 1000000],
        'free_delivery_from' => ['label' => "Bepul yetkazish chegarasi (so'm, 0 = yo'q)", 'type' => 'int', 'min' => 0, 'max' => 100000000],
        'delivery_radius_km' => ['label' => 'Yetkazish radiusi (km, 3–5)', 'type' => 'float', 'min' => 3, 'max' => 5],
        'restaurant_lat' => ['label' => 'Oshxona kengligi (lat)', 'type' => 'float', 'min' => -90, 'max' => 90],
        'restaurant_lng' => ['label' => 'Oshxona uzunligi (lng)', 'type' => 'float', 'min' => -180, 'max' => 180],
        'card_number' => ['label' => 'Karta raqami', 'type' => 'text', 'max' => 24, 'pattern' => '/^[0-9 ]{12,24}$/'],
        'card_owner' => ['label' => 'Karta egasi', 'type' => 'text', 'max' => 100],
        'restaurant_phone' => ['label' => 'Restoran telefoni', 'type' => 'text', 'max' => 20, 'pattern' => '/^\+?[0-9 ()\-]{7,20}$/'],
        'work_hours' => ['label' => 'Ish vaqti (HH:MM-HH:MM)', 'type' => 'text', 'max' => 11, 'pattern' => '/^\d{1,2}:\d{2}-\d{1,2}:\d{2}$/', 'strip_spaces' => true],
        'admin_username' => ['label' => 'Admin username (masalan, @lion_admin)', 'type' => 'text', 'max' => 32, 'pattern' => '/^[A-Za-z0-9_]{5,32}$/', 'strip_at' => true],
    ];

    public static function rule(string $key): ?array
    {
        return self::RULES[$key] ?? null;
    }

    /**
     * Kiritilgan matnni tekshiradi va saqlash uchun normalizatsiya qiladi.
     * @throws ServiceException('invalid_setting') qoida buzilsa
     */
    public static function normalize(string $key, string $input): string
    {
        $rule = self::rule($key);
        if ($rule === null) {
            throw new ServiceException('invalid_setting');
        }
        $value = trim($input);
        if (!empty($rule['strip_at'])) {
            $value = ltrim($value, '@');
        }
        if (!empty($rule['strip_spaces'])) {
            $value = str_replace(' ', '', $value);
        }

        switch ($rule['type']) {
            case 'int':
                if (!preg_match('/^\d+$/', $value)) {
                    throw new ServiceException('invalid_setting');
                }
                $number = (int) $value;
                self::inRange($number, $rule);
                return (string) $number;
            case 'float':
                $value = str_replace(',', '.', $value);
                if (!is_numeric($value)) {
                    throw new ServiceException('invalid_setting');
                }
                $number = (float) $value;
                self::inRange($number, $rule);
                return (string) $number;
            default:
                if ($value === '' || mb_strlen($value) > ($rule['max'] ?? 255)) {
                    throw new ServiceException('invalid_setting');
                }
                if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                    throw new ServiceException('invalid_setting');
                }
                return $value;
        }
    }

    public static function update(string $key, string $input): void
    {
        Setting::set($key, self::normalize($key, $input));
    }

    /** Ro'yxat uchun: kalit → [yorliq, qiymat]. */
    public static function listing(): array
    {
        $out = [];
        foreach (self::RULES as $key => $rule) {
            $out[$key] = [$rule['label'], Setting::get($key)];
        }
        return $out;
    }

    private static function inRange(int|float $number, array $rule): void
    {
        if (($rule['min'] ?? null) !== null && $number < $rule['min']) {
            throw new ServiceException('invalid_setting');
        }
        if (($rule['max'] ?? null) !== null && $number > $rule['max']) {
            throw new ServiceException('invalid_setting');
        }
    }
}
