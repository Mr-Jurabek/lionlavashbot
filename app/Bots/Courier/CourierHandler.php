<?php

declare(strict_types=1);

namespace App\Bots\Courier;

use App\Bots\Customer\Context;
use App\Bots\Customer\Keyboards;
use App\Bots\Customer\Views;
use App\Core\I18n;
use App\Core\Telegram;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\CourierService;
use App\Services\PaymentService;
use App\Services\ServiceException;

/**
 * Kuryer menyusi (bitta bot ichida). Faqat faol kuryer ko'radi; faqat o'ziga tayinlangan buyurtmalar.
 * Matnlar o'zbek tilida.
 */
final class CourierHandler
{
    private const ACTIONS = ['pickup', 'deliver', 'cash'];

    public static function home(Context $ctx): void
    {
        self::requireCourier($ctx);
        $ctx->setState('main');
        self::list($ctx);
    }

    /** Tayinlangan, yakunlanmagan buyurtmalar ro'yxati. */
    public static function list(Context $ctx): void
    {
        $courier = self::requireCourier($ctx);
        $orders = CourierService::ownedOrders((int) $courier['id']);
        if ($orders === []) {
            $ctx->screen(I18n::t("uz", "courier.empty"), null);
            return;
        }
        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [Keyboards::button(
                '#' . $order['id'] . ' · ' . I18n::t('uz', 'status.' . $order['status']) . ' · ' . I18n::money('uz', (int) $order['total']),
                'cord:' . $order['id']
            )];
        }
        $ctx->screen(I18n::t('uz', 'courier.title'), Keyboards::inline($rows));
    }

    public static function menu(Context $ctx): void
    {
        self::list($ctx);
    }

    public static function show(Context $ctx, string $id = '0'): void
    {
        $courier = self::requireCourier($ctx);
        $order = Order::find((int) $id);
        if ($order === null || (int) $order['courier_id'] !== (int) $courier['id']) {
            throw new ServiceException('not_your_order');
        }
        $customer = User::find((int) $order['user_id']) ?? [];
        $items = OrderItem::byOrder((int) $order['id']);
        $ctx->screen(self::card($order, $customer, $items), self::actions($order));
    }

    /** Holat tugmalari: faqat joriy holatga mos keladigan amal ko'rsatiladi. */
    public static function actions(array $order): array
    {
        $id = $order['id'];
        $rows = [];
        if ($order['status'] === 'ready') {
            $rows[] = [Keyboards::button(I18n::t('uz', 'courier.btn.pickup'), 'cgo:' . $id)];
        }
        if ($order['status'] === 'on_the_way') {
            $rows[] = [Keyboards::button(I18n::t('uz', 'courier.btn.deliver'), 'cdone:' . $id)];
        }
        if ($order['status'] === 'delivered' && $order['payment_method'] === PaymentService::CASH && $order['payment_status'] === 'unpaid') {
            $rows[] = [Keyboards::button(I18n::t('uz', 'courier.btn.cash'), 'ccash:' . $id)];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'courier.btn.back'), 'cmenu')];
        return Keyboards::inline($rows);
    }

    /** cgo / cdone / ccash — action 'pickup' / 'deliver' / 'cash'. */
    public static function act(Context $ctx, string $id = '0', string $action = ''): void
    {
        self::requireCourier($ctx);
        if (!in_array($action, self::ACTIONS, true)) {
            $ctx->answer(I18n::t('uz', 'error.invalid_transition'), true);
            return;
        }
        try {
            CourierService::act($ctx->telegramId(), (int) $id, $action);
        } catch (ServiceException $e) {
            $ctx->answer(I18n::t('uz', 'error.' . $e->reason), true);
            return;
        }
        $ctx->answer(I18n::t('uz', 'courier.done'));
        self::show($ctx, $id);
    }

    /** Admin buyurtmani tayinlaganda kuryerga xabar. */
    public static function notifyAssigned(Telegram $tg, array $order, int $courierTelegramId): void
    {
        $customer = User::find((int) $order['user_id']) ?? [];
        $items = OrderItem::byOrder((int) $order['id']);
        $tg->sendMessage(
            $courierTelegramId,
            I18n::t('uz', 'courier.assigned') . "\n\n" . self::card($order, $customer, $items),
            Keyboards::inline([[Keyboards::button(I18n::t('uz', 'courier.btn.open'), 'cord:' . $order['id'])]])
        );
    }

    /** Buyurtma kartochkasi (kuryer uchun). */
    public static function card(array $order, array $customer, array $items): string
    {
        $lines = array_map(static fn(array $i) => sprintf(
            '• %s × %d',
            Views::e($i['name_snapshot']),
            $i['quantity']
        ), $items);
        $map = $order['latitude'] !== null && $order['longitude'] !== null
            ? '<a href="' . Views::e(sprintf('https://maps.google.com/?q=%F,%F', (float) $order['latitude'], (float) $order['longitude'])) . '">' . I18n::t('uz', 'admin.map') . '</a>'
            : '—';
        return I18n::t('uz', 'courier.card', [
            'id' => $order['id'],
            'status' => I18n::t('uz', 'status.' . $order['status']),
            'name' => Views::e($customer['first_name'] ?? ''),
            'phone' => Views::e($order['phone']),
            'payment' => I18n::t('uz', 'payment.' . $order['payment_method']),
            'payment_status' => (string) $order['payment_status'],
            'address' => Views::e($order['address_text'] ?? '—'),
            'map' => $map,
            'comment' => Views::e($order['comment'] ?? '—'),
            'items' => implode("\n", $lines),
            'total' => I18n::money('uz', (int) $order['total']),
        ]);
    }

    /** @return array<string, mixed> faol kuryer qatori */
    private static function requireCourier(Context $ctx): array
    {
        $courier = CourierService::findActiveByTelegram($ctx->telegramId());
        if ($courier === null) {
            throw new ServiceException('forbidden');
        }
        return $courier;
    }
}
