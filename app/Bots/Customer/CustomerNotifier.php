<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Core\Env;
use App\Core\I18n;
use App\Core\Telegram;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;

/**
 * Mijozga holat o'zgarishi haqida xabar va ADMIN_CHAT_ID ga yangi buyurtma/chek haqida xabar.
 * Xabarlar mijoz boti tokeni orqali yuboriladi.
 */
final class CustomerNotifier
{
    public function __construct(private readonly Telegram $tg)
    {
    }

    /** OrderService o'tishlariga obuna bo'ladi (kirish nuqtasida bir marta chaqiriladi). */
    public function register(): void
    {
        OrderService::onTransition(function (array $order, string $from, string $to, string $by): void {
            $this->statusChanged($order, $from, $to);
        });
    }

    public function statusChanged(array $order, string $from, string $to): void
    {
        // Bu o'tishlarda mijoz botdan to'g'ridan-to'g'ri javob oladi
        if ($to === 'payment_review' || ($from === 'new' && $to === 'awaiting_payment')) {
            return;
        }
        $user = User::find((int) $order['user_id']);
        if ($user === null) {
            return;
        }
        $lang = I18n::normalize($user['lang']);

        if ($from === 'payment_review' && $to === 'awaiting_payment') {
            $this->tg->sendMessage(
                (int) $user['telegram_id'],
                I18n::t($lang, 'notify.payment_rejected', [
                    'id' => $order['id'],
                    'phone' => Views::e(Setting::get('restaurant_phone')),
                ]),
                Keyboards::inline([[Keyboards::button(
                    I18n::t($lang, 'btn.send_receipt', ['id' => $order['id']]),
                    'rcpt:' . $order['id']
                )]])
            );
            return;
        }
        $this->tg->sendMessage((int) $user['telegram_id'], I18n::t($lang, 'notify.status', [
            'id' => $order['id'],
            'status' => I18n::t($lang, 'status.' . $to),
        ]));
    }

    /** @param array{order: array, items: list<array>} $details */
    public function newOrder(array $details, array $user): void
    {
        $chatId = Env::get('ADMIN_CHAT_ID');
        if ($chatId === null) {
            return;
        }
        $this->tg->sendMessage($chatId, Views::adminOrder($details, $user));
    }

    public function receipt(array $order, array $user, string $fileId, string $kind): void
    {
        $chatId = Env::get('ADMIN_CHAT_ID');
        if ($chatId === null) {
            return;
        }
        $caption = I18n::t('uz', 'admin.receipt', [
            'id' => $order['id'],
            'total' => I18n::money('uz', (int) $order['total']),
            'name' => Views::e($user['first_name'] ?? ''),
            'phone' => Views::e($order['phone']),
        ]);
        $method = $kind === 'photo' ? 'sendPhoto' : 'sendDocument';
        $this->tg->call($method, [
            'chat_id' => $chatId,
            $kind => $fileId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ]);
    }
}
