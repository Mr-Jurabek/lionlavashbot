<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ServiceException;

/**
 * Karta orqali to'lov: chek rasmini qabul qilish.
 */
final class PaymentHandler
{
    private const DOCUMENT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

    /** «📎 Chek yuborish» tugmasi (Buyurtmalarim yoki rad etish xabaridan). */
    public static function startReceipt(Context $ctx, string $orderId = '0'): void
    {
        $order = Order::find((int) $orderId);
        if ($order === null || (int) $order['user_id'] !== $ctx->userId()) {
            throw new ServiceException('order_not_found');
        }
        if ($order['status'] !== 'awaiting_payment') {
            throw new ServiceException('receipt_not_expected');
        }
        $ctx->answer();
        $ctx->setState('await_receipt', ['order_id' => (int) $order['id']]);
        $ctx->reply(Views::receiptRequest($ctx, $order, PaymentService::cardDetails()), Keyboards::mainMenu($ctx));
    }

    public static function receipt(Context $ctx): void
    {
        $message = $ctx->message() ?? [];
        $kind = null;
        $fileId = null;
        if (!empty($message['photo'])) {
            $kind = 'photo';
            $fileId = (string) end($message['photo'])['file_id']; // eng katta o'lcham
        } elseif (isset($message['document']) && in_array($message['document']['mime_type'] ?? '', self::DOCUMENT_TYPES, true)) {
            $kind = 'document';
            $fileId = (string) $message['document']['file_id'];
        }
        if ($fileId === null) {
            $ctx->reply($ctx->t('receipt_expected'), Keyboards::mainMenu($ctx));
            return;
        }

        $orderId = (int) ($ctx->stateData()['order_id'] ?? 0);
        $order = OrderService::attachReceipt($orderId, $ctx->userId(), $fileId, 'customer:' . $ctx->user['telegram_id']);
        $ctx->setState('main');
        $ctx->reply($ctx->t('receipt_received'), Keyboards::mainMenu($ctx));
        (new CustomerNotifier($ctx->tg))->receipt($order, $ctx->user, $fileId, $kind);
    }
}
