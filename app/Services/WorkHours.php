<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\Setting;
use DateTimeImmutable;

/**
 * settings.work_hours formati: "HH:MM-HH:MM". Tugash boshlanishdan kichik bo'lsa —
 * yarim tundan o'tadi (masalan 10:00-02:00). Boshlanish = tugash — 24 soat ochiq.
 */
final class WorkHours
{
    public static function text(): string
    {
        return Setting::get('work_hours', '');
    }

    public static function isOpen(?DateTimeImmutable $now = null): bool
    {
        $value = str_replace(' ', '', self::text());
        if (!preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', $value, $m)) {
            if ($value !== '') {
                Logger::warning("work_hours noto'g'ri formatda, cheklov qo'llanmadi", ['value' => $value]);
            }
            return true;
        }
        $start = (int) $m[1] * 60 + (int) $m[2];
        $end = (int) $m[3] * 60 + (int) $m[4];
        $now ??= new DateTimeImmutable();
        $current = (int) $now->format('G') * 60 + (int) $now->format('i');

        if ($start === $end) {
            return true;
        }
        if ($start < $end) {
            return $current >= $start && $current < $end;
        }
        return $current >= $start || $current < $end;
    }
}
