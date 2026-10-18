<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\Setting;

/**
 * Admin aniqlash. Admin ID birinchi navbatda settings.admin_tg_id dan olinadi,
 * u bo'sh bo'lsa — .env dagi ADMIN_TG_ID dan. Admin almashtirilganda .env qiymati zaxira bo'lib qoladi.
 */
final class AdminAccess
{
    public static function id(): ?int
    {
        $fromSettings = trim(Setting::get('admin_tg_id'));
        if (ctype_digit($fromSettings) && $fromSettings !== '0') {
            return (int) $fromSettings;
        }
        $fromEnv = (string) Env::get('ADMIN_TG_ID', '');
        return ctype_digit($fromEnv) && $fromEnv !== '0' ? (int) $fromEnv : null;
    }

    public static function isAdmin(int $telegramId): bool
    {
        $id = self::id();
        return $id !== null && $id === $telegramId;
    }

    /** Admin bo'lmasa ServiceException('forbidden'). Har bir admin amalida chaqiriladi. */
    public static function require(int $telegramId): void
    {
        if (!self::isAdmin($telegramId)) {
            throw new ServiceException('forbidden');
        }
    }

    /** Yangi admin ID sini saqlaydi. Chaqiruvchi tasdiqlashni o'zi ta'minlaydi. */
    public static function change(string $value): void
    {
        if (!ctype_digit($value) || strlen($value) < 5 || strlen($value) > 15) {
            throw new ServiceException('invalid_setting');
        }
        Setting::set('admin_tg_id', $value);
    }
}
