<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Core\I18n;
use App\Services\DeliveryFee;

/**
 * Matn shablonlari. Foydalanuvchi/baza matnlari HTML uchun tozalanadi.
 */
final class Views
{
    public static function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @param array{items: list<array>, items_total: int, delivery_fee: int, total: int} $summary */
    public static function cartLines(Context $ctx, array $summary): string
    {
        $lines = [];
        foreach ($summary['items'] as $item) {
            $lines[] = $ctx->t('cart_line', [
                'name' => self::e($ctx->localized($item, 'name')),
                'qty' => $item['quantity'],
                'price' => $ctx->money((int) $item['price']),
                'total' => $ctx->money((int) $item['line_total']),
            ]);
        }
        return implode("\n", $lines);
    }

    public static function totals(Context $ctx, array $summary): string
    {
        $lines = [$ctx->t('cart_items_total', ['amount' => $ctx->money($summary['items_total'])])];
        $lines[] = $summary['delivery_fee'] === 0
            ? $ctx->t('cart_delivery_free')
            : $ctx->t('cart_delivery', ['amount' => $ctx->money($summary['delivery_fee'])]);
        $lines[] = $ctx->t('cart_total', ['amount' => $ctx->money($summary['total'])]);
        return implode("\n", $lines);
    }

    public static function cart(Context $ctx, array $summary): string
    {
        $text = $ctx->t('cart_title') . "\n\n" . self::cartLines($ctx, $summary) . "\n\n" . self::totals($ctx, $summary);
        $freeFrom = DeliveryFee::freeFrom();
        if ($summary['delivery_fee'] > 0 && $freeFrom > 0) {
            $text .= "\n\n" . $ctx->t('cart_free_hint', ['amount' => $ctx->money($freeFrom)]);
        }
        return $text;
    }

    public static function confirm(Context $ctx, array $summary, array $checkout): string
    {
        $parts = [
            $ctx->t('confirm_title'),
            self::cartLines($ctx, $summary),
            self::totals($ctx, $summary),
            implode("\n", array_filter([
                $ctx->t('confirm_address', ['address' => self::address($ctx->lang(), $checkout)]),
                isset($checkout['comment']) ? $ctx->t('confirm_comment', ['comment' => self::e($checkout['comment'])]) : null,
                $ctx->t('confirm_payment', ['method' => $ctx->t('payment.' . $checkout['payment_method'])]),
                $ctx->t('confirm_phone', ['phone' => self::e($ctx->user['phone'] ?? '')]),
            ])),
            $ctx->t('confirm_hint'),
        ];
        return implode("\n\n", $parts);
    }

    /** Manzil matni yoki xaritadagi lokatsiya havolasi. */
    public static function address(string $lang, array $data): string
    {
        $parts = [];
        if (!empty($data['address_text'])) {
            $parts[] = self::e((string) $data['address_text']);
        }
        if (isset($data['latitude'], $data['longitude'])) {
            $url = sprintf('https://maps.google.com/?q=%F,%F', (float) $data['latitude'], (float) $data['longitude']);
            $parts[] = '<a href="' . self::e($url) . '">' . I18n::t($lang, 'address_location') . '</a>';
        }
        return implode(', ', $parts);
    }

    public static function receiptRequest(Context $ctx, array $order, array $card): string
    {
        return $ctx->t('receipt_request', [
            'id' => $order['id'],
            'total' => $ctx->money((int) $order['total']),
            'card' => self::e($card['number']),
            'owner' => self::e($card['owner']),
        ]);
    }

    /**
     * Admin chatiga yuboriladigan buyurtma matni (o'zbek tilida).
     * @param array{order: array, items: list<array>} $details
     */
    public static function adminOrder(array $details, array $user): string
    {
        $order = $details['order'];
        $items = array_map(
            static fn(array $i) => sprintf(
                '• %s × %d = %s',
                self::e($i['name_snapshot']),
                $i['quantity'],
                I18n::money('uz', (int) $i['price_snapshot'] * (int) $i['quantity'])
            ),
            $details['items']
        );
        return I18n::t('uz', 'admin.new_order', [
            'id' => $order['id'],
            'name' => self::e($user['first_name'] ?? ''),
            'phone' => self::e($order['phone']),
            'payment' => I18n::t('uz', 'payment.' . $order['payment_method']),
            'address' => self::address('uz', $order),
            'comment' => self::e($order['comment'] ?? '—'),
            'items' => implode("\n", $items),
            'items_total' => I18n::money('uz', (int) $order['items_total']),
            'fee' => I18n::money('uz', (int) $order['delivery_fee']),
            'total' => I18n::money('uz', (int) $order['total']),
        ]);
    }
}
