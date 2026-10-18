<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Core\I18n;
use App\Core\Logger;
use App\Services\AdminAccess;

/**
 * «🆘 Yordam»: mijoz xabari admin shaxsiy akkauntiga boradi. Admin «✉️ Javob» orqali javob beradi.
 */
final class SupportHandler
{
    private const MAX_LENGTH = 1000;

    public static function ask(Context $ctx): void
    {
        $ctx->setState('help_message');
        $ctx->reply($ctx->t('help_ask'), Keyboards::cancelOnly($ctx));
    }

    public static function message(Context $ctx): void
    {
        $text = $ctx->text();
        if ($text === '') {
            $ctx->reply($ctx->t('help_ask'), Keyboards::cancelOnly($ctx));
            return;
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $ctx->reply($ctx->t('help_too_long'), Keyboards::cancelOnly($ctx));
            return;
        }
        $adminId = AdminAccess::id();
        if ($adminId === null) {
            Logger::error('Admin ID sozlanmagan (.env ADMIN_TG_ID yoki settings.admin_tg_id)');
            $ctx->reply($ctx->t('error.generic'), Keyboards::mainMenu($ctx));
            return;
        }

        $ctx->tg->sendMessage(
            $adminId,
            I18n::t('uz', 'admin.help_request', [
                'name' => Views::e($ctx->user['first_name'] ?? ''),
                'phone' => Views::e($ctx->user['phone'] ?? '—'),
                'tg' => $ctx->telegramId(),
                'text' => Views::e($text),
            ]),
            Keyboards::inline([[Keyboards::button(I18n::t('uz', 'admin.btn.reply'), 'rp:' . $ctx->telegramId())]])
        );
        $ctx->setState('main');
        $ctx->reply($ctx->t('help_sent'), Keyboards::mainMenu($ctx));
    }
}
