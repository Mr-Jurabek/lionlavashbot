<?php

declare(strict_types=1);

namespace App\Bots\Customer;

/**
 * Klaviaturalar. callback_data 64 baytdan oshmasligi uchun qisqa prefikslar ishlatiladi
 * (ro'yxat CustomerBot::router() da).
 */
final class Keyboards
{
    public static function reply(array $rows): array
    {
        return ['keyboard' => $rows, 'resize_keyboard' => true];
    }

    public static function button(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    public static function inline(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    public static function mainMenu(Context $ctx): array
    {
        return self::reply([
            [['text' => $ctx->t('btn.menu')]],
            [['text' => $ctx->t('btn.cart')], ['text' => $ctx->t('btn.orders')]],
            [['text' => $ctx->t('btn.contact')], ['text' => $ctx->t('btn.lang')]],
        ]);
    }

    public static function languages(Context $ctx): array
    {
        return self::inline([[
            self::button($ctx->t('lang.uz'), 'lang:uz'),
            self::button($ctx->t('lang.ru'), 'lang:ru'),
        ]]);
    }

    public static function phoneRequest(Context $ctx): array
    {
        return self::reply([[['text' => $ctx->t('btn.send_phone'), 'request_contact' => true]]])
            + ['one_time_keyboard' => true];
    }

    public static function addressStep(Context $ctx): array
    {
        return self::reply([
            [['text' => $ctx->t('btn.send_location'), 'request_location' => true]],
            [['text' => $ctx->t('btn.cancel')]],
        ]);
    }

    public static function commentStep(Context $ctx): array
    {
        return self::reply([[['text' => $ctx->t('btn.skip')]], [['text' => $ctx->t('btn.cancel')]]]);
    }

    public static function paymentStep(Context $ctx): array
    {
        return self::reply([
            [['text' => $ctx->t('btn.cash')], ['text' => $ctx->t('btn.card')]],
            [['text' => $ctx->t('btn.cancel')]],
        ]);
    }

    public static function confirmStep(Context $ctx): array
    {
        return self::reply([[['text' => $ctx->t('btn.confirm')]], [['text' => $ctx->t('btn.cancel')]]]);
    }
}
