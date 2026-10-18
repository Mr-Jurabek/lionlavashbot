<?php

declare(strict_types=1);

namespace App\Bots\Admin;

use App\Bots\Customer\Context;
use App\Bots\Customer\Keyboards;
use App\Bots\Customer\Views;
use App\Core\I18n;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminAccess;

/**
 * Admin paneli kirish nuqtasi, bo'limlar va mijozga javob. Admin matnlari faqat o'zbek tilida.
 * Har bir amal AdminAccess::require() orqali tekshiriladi.
 */
final class AdminHandler
{
    private const REPLY_MAX = 1000;

    public static function home(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->setState('main');
        $ctx->reply(I18n::t('uz', 'admin.home'), self::menuMarkup());
    }

    /** Inline «adm:<bo'lim>» tugmalari. */
    public static function section(Context $ctx, string $section = 'home'): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        match ($section) {
            'orders' => AdminOrdersHandler::list($ctx),
            'products' => AdminCatalogHandler::productsList($ctx),
            'settings' => AdminCatalogHandler::settingsList($ctx),
            'msg' => self::askOrderForMessage($ctx),
            default => self::home($ctx),
        };
    }

    public static function askOrderForMessage(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->setState('admin_msg_order');
        $ctx->reply(I18n::t('uz', 'admin.ask_order_id'), Keyboards::cancelOnly($ctx));
    }

    public static function orderForMessage(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $text = $ctx->text();
        $order = ctype_digit($text) ? Order::find((int) $text) : null;
        $customer = $order !== null ? User::find((int) $order['user_id']) : null;
        if ($customer === null) {
            $ctx->reply(I18n::t('uz', 'admin.order_not_found'), Keyboards::cancelOnly($ctx));
            return;
        }
        $ctx->setState('admin_reply', ['target' => (int) $customer['telegram_id']]);
        $ctx->reply(I18n::t('uz', 'admin.ask_reply_text'), Keyboards::cancelOnly($ctx));
    }

    /** Yordam xabaridagi «✉️ Javob» tugmasi. */
    public static function replyStart(Context $ctx, string $telegramId = ''): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        if (!ctype_digit($telegramId) || User::findByTelegramId((int) $telegramId) === null) {
            $ctx->reply(I18n::t('uz', 'admin.user_not_found'), Keyboards::mainMenu($ctx));
            return;
        }
        $ctx->setState('admin_reply', ['target' => (int) $telegramId]);
        $ctx->reply(I18n::t('uz', 'admin.ask_reply_text'), Keyboards::cancelOnly($ctx));
    }

    public static function replyText(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $text = $ctx->text();
        $target = (int) ($ctx->stateData()['target'] ?? 0);
        if ($text === '' || mb_strlen($text) > self::REPLY_MAX) {
            $ctx->reply(I18n::t('uz', 'admin.ask_reply_text'), Keyboards::cancelOnly($ctx));
            return;
        }
        $customer = $target > 0 ? User::findByTelegramId($target) : null;
        if ($customer === null) {
            $ctx->setState('main');
            $ctx->reply(I18n::t('uz', 'admin.user_not_found'), self::menuMarkup());
            return;
        }
        $ctx->tg->sendMessage(
            $target,
            I18n::t(I18n::normalize($customer['lang']), 'support_reply', ['text' => Views::e($text)])
        );
        $ctx->setState('main');
        $ctx->reply(I18n::t('uz', 'admin.reply_sent'), self::menuMarkup());
    }

    /** Admin paneli inline tugmalari. */
    public static function menuMarkup(): array
    {
        return Keyboards::inline([
            [Keyboards::button(I18n::t('uz', 'admin.btn.orders'), 'adm:orders'), Keyboards::button(I18n::t('uz', 'admin.btn.msg'), 'adm:msg')],
            [Keyboards::button(I18n::t('uz', 'admin.btn.products'), 'adm:products'), Keyboards::button(I18n::t('uz', 'admin.btn.settings'), 'adm:settings')],
        ]);
    }
}
