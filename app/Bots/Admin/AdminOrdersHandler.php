<?php

declare(strict_types=1);

namespace App\Bots\Admin;

use App\Bots\Customer\Context;
use App\Bots\Customer\Keyboards;
use App\Bots\Customer\Views;
use App\Core\I18n;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\AdminAccess;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ServiceException;

/**
 * Ochiq buyurtmalar: ro'yxat, kartochka, holatni o'zgartirish, chekni ko'rish.
 * Holat o'zgarishi OrderService::transition() orqali (qoidalar va log bilan).
 */
final class AdminOrdersHandler
{
    public static function list(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $orders = Order::openOrders(15);
        if ($orders === []) {
            $ctx->screen(I18n::t('uz', 'admin.no_orders'), self::backMarkup());
            return;
        }
        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [Keyboards::button(I18n::t('uz', 'admin.order_btn', [
                'id' => $order['id'],
                'status' => self::statusLabel((string) $order['status']),
                'total' => I18n::money('uz', (int) $order['total']),
            ]), 'aord:' . $order['id'])];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:home')];
        $ctx->screen(I18n::t('uz', 'admin.orders_title'), Keyboards::inline($rows));
    }

    public static function show(Context $ctx, string $id = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $order = Order::find((int) $id);
        if ($order === null) {
            $ctx->answer(I18n::t('uz', 'admin.order_not_found'), true);
            return;
        }
        $customer = User::find((int) $order['user_id']) ?? [];
        $items = array_map(static fn(array $i) => sprintf(
            '• %s × %d = %s',
            Views::e($i['name_snapshot']),
            $i['quantity'],
            I18n::money('uz', (int) $i['price_snapshot'] * (int) $i['quantity'])
        ), OrderItem::byOrder((int) $order['id']));

        $text = I18n::t('uz', 'admin.order_card', [
            'id' => $order['id'],
            'status' => self::statusLabel((string) $order['status']),
            'name' => Views::e($customer['first_name'] ?? ''),
            'phone' => Views::e($order['phone']),
            'payment' => I18n::t('uz', 'payment.' . $order['payment_method']),
            'payment_status' => (string) $order['payment_status'],
            'address' => Views::e($order['address_text'] ?? '—'),
            'map' => self::mapLink($order),
            'courier' => self::courierName($order),
            'comment' => Views::e($order['comment'] ?? '—'),
            'items' => implode("\n", $items),
            'items_total' => I18n::money('uz', (int) $order['items_total']),
            'fee' => I18n::money('uz', (int) $order['delivery_fee']),
            'total' => I18n::money('uz', (int) $order['total']),
        ]);

        $rows = [];
        foreach (OrderService::TRANSITIONS[$order['status']] ?? [] as $to) {
            if (OrderService::canTransition($order, $to)) {
                $rows[] = [Keyboards::button('➡️ ' . self::statusLabel($to), 'ast:' . $order['id'] . ':' . $to)];
            }
        }
        if (!empty($order['receipt_file_id'])) {
            $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.receipt'), 'arc:' . $order['id'])];
        }
        if (in_array($order['status'], ['accepted', 'preparing', 'ready', 'on_the_way'], true)) {
            $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.courier_assign'), 'acpick:' . $order['id'])];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:orders')];
        $ctx->screen($text, Keyboards::inline($rows));
    }

    public static function setStatus(Context $ctx, string $id = '0', string $to = ''): void
    {
        AdminAccess::require($ctx->telegramId());
        try {
            OrderService::transition((int) $id, $to, 'admin:' . $ctx->telegramId());
        } catch (ServiceException $e) {
            $ctx->answer(I18n::t('uz', 'error.' . $e->reason), true);
            return;
        }
        $ctx->answer(I18n::t('uz', 'admin.status_changed'));
        self::show($ctx, $id);
    }

    public static function receipt(Context $ctx, string $id = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $order = Order::find((int) $id);
        if ($order === null || empty($order['receipt_file_id'])) {
            $ctx->answer(I18n::t('uz', 'admin.receipt_missing'), true);
            return;
        }
        $fileId = (string) $order['receipt_file_id'];
        $caption = ['chat_id' => $ctx->chatId(), 'caption' => '#' . $order['id']];
        // Chek rasm yoki PDF bo'lishi mumkin: avval rasm sifatida, bo'lmasa hujjat sifatida
        if ($ctx->tg->call('sendPhoto', $caption + ['photo' => $fileId]) === null) {
            $ctx->tg->call('sendDocument', $caption + ['document' => $fileId]);
        }
        $ctx->answer();
    }

    public static function statusLabel(string $status): string
    {
        return I18n::t('uz', 'status.' . $status);
    }

    private static function courierName(array $order): string
    {
        if (empty($order['courier_id'])) {
            return '—';
        }
        $courier = Courier::find((int) $order['courier_id']);
        return $courier !== null ? Views::e($courier['name']) : '—';
    }

    private static function mapLink(array $order): string
    {
        if ($order['latitude'] === null || $order['longitude'] === null) {
            return '—';
        }
        $url = sprintf('https://maps.google.com/?q=%F,%F', (float) $order['latitude'], (float) $order['longitude']);
        return '<a href="' . Views::e($url) . '">' . I18n::t('uz', 'admin.map') . '</a>';
    }

    private static function backMarkup(): array
    {
        return Keyboards::inline([[Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:home')]]);
    }
}
