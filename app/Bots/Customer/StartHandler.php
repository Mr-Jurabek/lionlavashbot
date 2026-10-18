<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Core\I18n;
use App\Models\Setting;
use App\Models\User;
use App\Services\WorkHours;

/**
 * /start, til tanlash, telefon raqam, asosiy menyu, aloqa.
 */
final class StartHandler
{
    public static function start(Context $ctx): void
    {
        if (empty($ctx->user['lang'])) {
            self::askLang($ctx);
            return;
        }
        if (empty($ctx->user['phone'])) {
            self::askPhone($ctx);
            return;
        }
        self::mainMenu($ctx, true);
    }

    public static function askLang(Context $ctx): void
    {
        $ctx->reply($ctx->t('choose_lang'), Keyboards::languages($ctx));
    }

    public static function chooseLang(Context $ctx, string $lang = ''): void
    {
        if (!in_array($lang, I18n::LANGS, true)) {
            $ctx->answer();
            return;
        }
        User::setLang($ctx->userId(), $lang);
        $ctx->user['lang'] = $lang;
        $ctx->answer($ctx->t('lang_saved'));
        $ctx->screen($ctx->t('lang_saved'), null);

        if (empty($ctx->user['phone'])) {
            self::askPhone($ctx);
            return;
        }
        self::mainMenu($ctx);
    }

    public static function askPhone(Context $ctx): void
    {
        $ctx->reply($ctx->t('ask_phone'), Keyboards::phoneRequest($ctx));
    }

    /** request_contact orqali kelgan kontakt. Faqat foydalanuvchining o'z raqami qabul qilinadi. */
    public static function contact(Context $ctx): void
    {
        $contact = $ctx->message()['contact'] ?? null;
        $fromId = (int) ($ctx->message()['from']['id'] ?? 0);
        if ($contact === null || (int) ($contact['user_id'] ?? 0) !== $fromId) {
            $ctx->reply($ctx->t('phone_own_only'), Keyboards::phoneRequest($ctx));
            return;
        }
        $digits = preg_replace('/\D+/', '', (string) $contact['phone_number']);
        if ($digits === '' || strlen($digits) > 15) {
            $ctx->reply($ctx->t('phone_own_only'), Keyboards::phoneRequest($ctx));
            return;
        }
        User::setPhone($ctx->userId(), '+' . $digits);
        $ctx->user['phone'] = '+' . $digits;
        self::mainMenu($ctx, true);
    }

    public static function mainMenu(Context $ctx, bool $welcome = false): void
    {
        $ctx->setState('main');
        $text = $ctx->t('main_menu');
        if ($welcome) {
            $text = $ctx->t('welcome', ['name' => Views::e($ctx->user['first_name'] ?? '')]) . "\n\n" . $text;
        }
        $ctx->reply($text, Keyboards::mainMenu($ctx));
    }

    public static function languageMenu(Context $ctx): void
    {
        $ctx->reply($ctx->t('choose_lang'), Keyboards::languages($ctx));
    }

    public static function contactInfo(Context $ctx): void
    {
        $text = $ctx->t('contact_info', [
            'phone' => Views::e(Setting::get('restaurant_phone')),
            'hours' => Views::e(WorkHours::text()),
        ]);
        $admin = ltrim(Setting::get('admin_username'), '@');
        if ($admin !== '') {
            $text .= "\n" . $ctx->t('contact_admin', [
                'admin' => '<a href="https://t.me/' . Views::e($admin) . '">@' . Views::e($admin) . '</a>',
            ]);
        }
        $ctx->reply($text, Keyboards::mainMenu($ctx));
    }

    /** Tushunarsiz xabar. */
    public static function fallback(Context $ctx): void
    {
        $ctx->reply($ctx->t('use_buttons'), Keyboards::mainMenu($ctx));
    }
}
