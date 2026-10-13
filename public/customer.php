<?php

declare(strict_types=1);

use App\Bots\Customer\CustomerBot;
use App\Bots\Customer\CustomerNotifier;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Telegram;
use App\Core\Webhook;

require dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json');

try {
    $update = Webhook::receive(Env::require('CUSTOMER_WEBHOOK_SECRET'));
    if ($update === null) {
        exit;
    }
    // Takroriy update (Telegram qayta yuborgan) — qayta ishlanmaydi
    if (!Webhook::markProcessed(CustomerBot::NAME, (int) $update['update_id'])) {
        echo '{"ok":true}';
        exit;
    }

    $tg = new Telegram(Env::require('CUSTOMER_BOT_TOKEN'));
    (new CustomerNotifier($tg))->register();
    (new CustomerBot($tg))->handle($update);
} catch (Throwable $e) {
    // Ichki xato tafsiloti tashqariga chiqmaydi; Telegram qayta yubormasligi uchun 200
    Logger::exception($e);
}

echo '{"ok":true}';
