<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tarjimalar: lang/{uz,ru}.php fayllaridan. Kalit topilmasa — kalitning o'zi qaytadi.
 */
final class I18n
{
    public const LANGS = ['uz', 'ru'];
    public const DEFAULT = 'uz';

    private static string $dir = '';
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    public static function init(string $dir): void
    {
        self::$dir = rtrim($dir, '/\\');
    }

    public static function t(string $lang, string $key, array $params = []): string
    {
        $text = self::dict($lang)[$key] ?? self::dict(self::DEFAULT)[$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    /** Matn shu kalitning istalgan tildagi qiymatiga tengmi (reply-tugmalarni aniqlash uchun). */
    public static function matches(string $key, string $text): bool
    {
        foreach (self::LANGS as $lang) {
            if ((self::dict($lang)[$key] ?? null) === $text) {
                return true;
            }
        }
        return false;
    }

    public static function money(string $lang, int $amount): string
    {
        return number_format($amount, 0, '', ' ') . ' ' . self::t($lang, 'currency');
    }

    public static function normalize(?string $lang): string
    {
        return in_array($lang, self::LANGS, true) ? $lang : self::DEFAULT;
    }

    /** @return array<string, string> */
    private static function dict(string $lang): array
    {
        if (!isset(self::$cache[$lang])) {
            $file = self::$dir . '/' . $lang . '.php';
            self::$cache[$lang] = is_file($file) ? require $file : [];
        }
        return self::$cache[$lang];
    }
}
