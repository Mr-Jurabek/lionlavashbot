<?php

declare(strict_types=1);

namespace App\Bots\Admin;

use App\Bots\Courier\CourierHandler;
use App\Bots\Customer\Context;
use App\Bots\Customer\Keyboards;
use App\Core\I18n;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminAccess;
use App\Services\CourierService;
use App\Services\ServiceException;

/**
 * Kuryerlarni qo'shish (forward yoki ID), tasdiqlash, faollik va buyurtmaga tayinlash.
 */
final class AdminCouriersHandler
{
    public static function list(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $rows = [];
        foreach (Courier::all() as $courier) {
            $mark = (int) $courier['is_active'] === 1 ? '✅' : '⛔';
            $rows[] = [Keyboards::button($mark . ' ' . $courier['name'], 'actog:' . $courier['id'])];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.courier_add'), 'acadd')];
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:home')];
        $ctx->screen(I18n::t('uz', 'admin.couriers_title'), Keyboards::inline($rows));
    }

    public static function askAdd(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $ctx->setState('admin_courier_add');
        $ctx->reply(I18n::t('uz', 'admin.ask_courier'), Keyboards::cancelOnly($ctx));
    }

    /** Forward xabar (ID avtomatik) yoki qo'lda kiritilgan raqam. */
    public static function addInput(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $message = $ctx->message() ?? [];
        $telegramId = 0;
        $name = '';

        if (isset($message['forward_from']['id'])) {
            $telegramId = (int) $message['forward_from']['id'];
            $name = (string) ($message['forward_from']['first_name'] ?? '');
        } elseif (isset($message['forward_sender_name'])) {
            $ctx->reply(I18n::t('uz', 'admin.courier_forward_private'), Keyboards::cancelOnly($ctx));
            return;
        } elseif (ctype_digit($ctx->text())) {
            $telegramId = (int) $ctx->text();
        } else {
            $ctx->reply(I18n::t('uz', 'admin.courier_invalid'), Keyboards::cancelOnly($ctx));
            return;
        }

        $known = User::findByTelegramId($telegramId);
        if ($known === null) {
            $ctx->reply(I18n::t('uz', 'admin.courier_not_started'), Keyboards::cancelOnly($ctx));
            return;
        }
        if ($name === '') {
            $name = (string) $known['first_name'];
        }
        $ctx->setState('admin_courier_confirm', ['tg' => $telegramId, 'name' => $name]);
        $ctx->reply(
            I18n::t('uz', 'admin.confirm_courier', ['name' => htmlspecialchars($name, ENT_QUOTES), 'tg' => $telegramId]),
            Keyboards::inline([[Keyboards::button(I18n::t('uz', 'admin.btn.confirm_admin'), 'acok')]])
        );
    }

    public static function confirm(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $data = $ctx->stateData();
        if ($ctx->state() !== 'admin_courier_confirm' || empty($data['tg'])) {
            $ctx->answer(I18n::t('uz', 'admin.invalid_setting'), true);
            return;
        }
        CourierService::add((int) $data['tg'], (string) ($data['name'] ?? ''));
        $ctx->setState('main');
        $ctx->reply(I18n::t('uz', 'admin.courier_added'), AdminHandler::menuMarkup());
    }

    public static function toggle(Context $ctx, string $id = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        CourierService::toggle((int) $id);
        self::list($ctx);
    }

    /** Buyurtma kartochkasidan: faol kuryerlar ro'yxati. */
    public static function pick(Context $ctx, string $orderId = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $order = Order::find((int) $orderId);
        if ($order === null || !in_array($order['status'], CourierService::ASSIGNABLE, true)) {
            $ctx->answer(I18n::t('uz', 'error.not_assignable'), true);
            return;
        }
        $couriers = Courier::allActive();
        if ($couriers === []) {
            $ctx->answer(I18n::t('uz', 'admin.no_couriers'), true);
            return;
        }
        $ctx->answer();
        $rows = [];
        foreach ($couriers as $courier) {
            $rows[] = [Keyboards::button($courier['name'], 'asgn:' . $orderId . ':' . $courier['id'])];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'aord:' . $orderId)];
        $ctx->screen(I18n::t('uz', 'admin.pick_courier'), Keyboards::inline($rows));
    }

    /** Tayinlash va kuryerga xabar. */
    public static function assign(Context $ctx, string $orderId = '0', string $courierId = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        try {
            $order = CourierService::assign((int) $orderId, (int) $courierId);
        } catch (ServiceException $e) {
            $ctx->answer(I18n::t('uz', 'error.' . $e->reason), true);
            return;
        }
        $courier = Courier::find((int) $courierId);
        if ($courier !== null) {
            CourierHandler::notifyAssigned($ctx->tg, $order, (int) $courier['telegram_id']);
        }
        $ctx->answer(I18n::t('uz', 'admin.courier_assigned'));
        AdminOrdersHandler::show($ctx, $orderId);
    }
}
