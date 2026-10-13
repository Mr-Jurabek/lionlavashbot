<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Services\OrderService;

final class OrdersHandler
{
    private const LIMIT = 10;

    public static function list(Context $ctx): void
    {
        $orders = OrderService::recentForUser($ctx->userId(), self::LIMIT);
        if ($orders === []) {
            $ctx->reply($ctx->t('orders_empty'), Keyboards::mainMenu($ctx));
            return;
        }
        $lines = [$ctx->t('orders_title')];
        $buttons = [];
        foreach ($orders as $order) {
            $lines[] = $ctx->t('order_line', [
                'id' => $order['id'],
                'date' => date('d.m.Y H:i', strtotime((string) $order['created_at'])),
                'total' => $ctx->money((int) $order['total']),
                'status' => $ctx->t('status.' . $order['status']),
            ]);
            if ($order['status'] === 'awaiting_payment') {
                $buttons[] = [Keyboards::button($ctx->t('btn.send_receipt', ['id' => $order['id']]), 'rcpt:' . $order['id'])];
            }
        }
        $ctx->reply(implode("\n\n", $lines), $buttons ? Keyboards::inline($buttons) : Keyboards::mainMenu($ctx));
    }
}
