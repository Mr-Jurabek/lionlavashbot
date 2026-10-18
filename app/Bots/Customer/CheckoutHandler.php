<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Core\I18n;
use App\Services\CartService;
use App\Services\DeliveryZone;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ServiceException;
use App\Services\WorkHours;

/**
 * Lokatsiya (majburiy, radius ichida) → podyezd/xonadon (ixtiyoriy) → izoh → to'lov usuli → tasdiqlash.
 * Oraliq ma'lumotlar users.state_data da.
 */
final class CheckoutHandler
{
    public static function start(Context $ctx): void
    {
        $ctx->answer();
        if (CartService::summary($ctx->userId())['items'] === []) {
            CartHandler::show($ctx);
            return;
        }
        if (!WorkHours::isOpen()) {
            self::closed($ctx);
            return;
        }
        $ctx->setState('checkout_address');
        $ctx->reply($ctx->t('ask_address'), Keyboards::addressStep($ctx));
    }

    public static function address(Context $ctx): void
    {
        $location = $ctx->message()['location'] ?? null;
        if ($location === null) {
            $ctx->reply($ctx->t('location_required'), Keyboards::addressStep($ctx));
            return;
        }
        $lat = (float) $location['latitude'];
        $lng = (float) $location['longitude'];
        try {
            DeliveryZone::check($lat, $lng);
        } catch (ServiceException $e) {
            $key = $e->reason === 'out_of_radius' ? 'error.out_of_radius' : 'error.restaurant_not_configured';
            $ctx->reply($ctx->t($key, ['radius' => DeliveryZone::radiusKm()]), Keyboards::addressStep($ctx));
            return;
        }
        $ctx->mergeState('checkout_details', ['latitude' => $lat, 'longitude' => $lng, 'address_text' => null]);
        $ctx->reply($ctx->t('ask_details'), Keyboards::detailsStep($ctx));
    }

    /** Podyezd, qavat, xonadon. «O'tkazib yuborish» mumkin. */
    public static function details(Context $ctx): void
    {
        $text = $ctx->text();
        if ($text === '') {
            $ctx->reply($ctx->t('ask_details'), Keyboards::detailsStep($ctx));
            return;
        }
        if (I18n::matches('btn.skip', $text)) {
            $details = null;
        } elseif (mb_strlen($text) > OrderService::ADDRESS_MAX) {
            $ctx->reply($ctx->t('address_invalid'), Keyboards::detailsStep($ctx));
            return;
        } else {
            $details = $text;
        }
        $ctx->mergeState('checkout_comment', ['address_text' => $details]);
        $ctx->reply($ctx->t('ask_comment'), Keyboards::commentStep($ctx));
    }

    public static function comment(Context $ctx): void
    {
        $text = $ctx->text();
        if ($text === '') {
            $ctx->reply($ctx->t('ask_comment'), Keyboards::commentStep($ctx));
            return;
        }
        if (I18n::matches('btn.skip', $text)) {
            $comment = null;
        } elseif (mb_strlen($text) > OrderService::COMMENT_MAX) {
            $ctx->reply($ctx->t('comment_invalid'), Keyboards::commentStep($ctx));
            return;
        } else {
            $comment = $text;
        }
        $ctx->mergeState('checkout_payment', ['comment' => $comment]);
        $ctx->reply($ctx->t('ask_payment'), Keyboards::paymentStep($ctx));
    }

    public static function payment(Context $ctx): void
    {
        $text = $ctx->text();
        $method = match (true) {
            I18n::matches('btn.cash', $text) => PaymentService::CASH,
            I18n::matches('btn.card', $text) => PaymentService::CARD,
            default => null,
        };
        if ($method === null) {
            $ctx->reply($ctx->t('ask_payment'), Keyboards::paymentStep($ctx));
            return;
        }
        $ctx->mergeState('checkout_confirm', ['payment_method' => $method]);
        self::showConfirm($ctx);
    }

    public static function confirm(Context $ctx): void
    {
        if (!I18n::matches('btn.confirm', $ctx->text())) {
            self::showConfirm($ctx);
            return;
        }
        $checkout = $ctx->stateData();
        try {
            $orderId = OrderService::createFromCart($ctx->userId(), $checkout, 'customer:' . $ctx->telegramId());
        } catch (ServiceException $e) {
            if ($e->reason === 'cart_empty') {
                // «Tasdiqlash» ikki marta bosilgan yoki savat bo'shatilgan — yangi buyurtma yaratilmaydi
                StartHandler::mainMenu($ctx);
                return;
            }
            throw $e;
        }

        $details = OrderService::details($orderId);
        $order = $details['order'];
        (new CustomerNotifier($ctx->tg))->newOrder($details, $ctx->user);

        if (PaymentService::requiresReceipt((string) $order['payment_method'])) {
            $ctx->setState('await_receipt', ['order_id' => $orderId]);
            $ctx->reply(
                $ctx->t('order_created_card', ['id' => $orderId]) . "\n\n"
                    . Views::receiptRequest($ctx, $order, PaymentService::cardDetails()),
                Keyboards::mainMenu($ctx)
            );
            return;
        }
        $ctx->setState('main');
        $ctx->reply($ctx->t('order_created_cash', [
            'id' => $orderId,
            'total' => $ctx->money((int) $order['total']),
        ]), Keyboards::mainMenu($ctx));
    }

    public static function cancel(Context $ctx): void
    {
        $ctx->setState('main');
        $ctx->reply($ctx->t('checkout_cancelled'), Keyboards::mainMenu($ctx));
    }

    public static function closed(Context $ctx): void
    {
        $ctx->setState('main');
        $ctx->reply($ctx->t('closed', ['hours' => Views::e(WorkHours::text())]), Keyboards::mainMenu($ctx));
    }

    private static function showConfirm(Context $ctx): void
    {
        $summary = CartService::summary($ctx->userId());
        if ($summary['items'] === []) {
            $ctx->setState('main');
            $ctx->reply($ctx->t('cart_empty'), Keyboards::mainMenu($ctx));
            return;
        }
        $ctx->reply(Views::confirm($ctx, $summary, $ctx->stateData()), Keyboards::confirmStep($ctx));
    }
}
