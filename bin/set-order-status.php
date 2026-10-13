<?php

declare(strict_types=1);

/**
 * Sinov uchun buyurtma holatini o'zgartirish (admin bot tayyor bo'lguncha).
 * Mijozga botda xabar boradi.
 *
 *   php bin/set-order-status.php <order_id> <status>
 *   php bin/set-order-status.php 12 accepted
 */

use App\Bots\Customer\CustomerNotifier;
use App\Core\Env;
use App\Core\Telegram;
use App\Services\OrderService;
use App\Services\ServiceException;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$orderId = (int) ($argv[1] ?? 0);
$status = (string) ($argv[2] ?? '');
if ($orderId <= 0 || !array_key_exists($status, OrderService::TRANSITIONS)) {
    fwrite(STDERR, "Foydalanish: php bin/set-order-status.php <order_id> <status>\n");
    fwrite(STDERR, 'Holatlar: ' . implode(', ', array_keys(OrderService::TRANSITIONS)) . "\n");
    exit(1);
}

(new CustomerNotifier(new Telegram(Env::require('CUSTOMER_BOT_TOKEN'))))->register();

try {
    $order = OrderService::transition($orderId, $status, 'cli');
    echo "#{$order['id']}: {$order['status']} (to'lov: {$order['payment_status']})\n";
} catch (ServiceException $e) {
    fwrite(STDERR, "Rad etildi: {$e->reason} — {$e->getMessage()}\n");
    exit(1);
}
