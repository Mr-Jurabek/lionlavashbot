<?php

declare(strict_types=1);

/**
 * Webhook o'rnatish (bazaga ulanmaydi — o'z kompyuteringizdan ham ishga tushirish mumkin).
 *
 *   php bin/set-webhook.php            — o'rnatish
 *   php bin/set-webhook.php --info     — joriy holat
 *   php bin/set-webhook.php --delete   — o'chirish
 */

use App\Core\Env;
use App\Core\Telegram;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$tg = new Telegram(Env::require('CUSTOMER_BOT_TOKEN'));
$option = $argv[1] ?? '';

if ($option === '--info') {
    $result = $tg->call('getWebhookInfo');
} elseif ($option === '--delete') {
    $result = $tg->call('deleteWebhook', ['drop_pending_updates' => false]);
} else {
    $url = rtrim(Env::require('APP_URL'), '/') . '/customer.php';
    if (!str_starts_with($url, 'https://')) {
        fwrite(STDERR, "APP_URL https:// bilan boshlanishi kerak\n");
        exit(1);
    }
    $result = $tg->call('setWebhook', [
        'url' => $url,
        'secret_token' => Env::require('CUSTOMER_WEBHOOK_SECRET'),
        'allowed_updates' => ['message', 'callback_query'],
        'max_connections' => 20,
    ]);
    echo "URL: {$url}\n";
}

if ($result === null) {
    fwrite(STDERR, "Xato — storage/logs/ ga qarang\n");
    exit(1);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
