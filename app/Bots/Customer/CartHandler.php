<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Services\CartService;

final class CartHandler
{
    public static function show(Context $ctx): void
    {
        $summary = CartService::summary($ctx->userId());
        if ($summary['items'] === []) {
            $ctx->screen($ctx->t('cart_empty'), Keyboards::inline([
                [Keyboards::button($ctx->t('btn.menu'), 'cats')],
            ]));
            return;
        }
        $rows = [];
        foreach ($summary['items'] as $item) {
            $pid = $item['product_id'];
            $rows[] = [
                Keyboards::button('➖', "ci:{$pid}:dec"),
                Keyboards::button(mb_substr($ctx->localized($item, 'name'), 0, 20) . ' · ' . $item['quantity'], 'noop'),
                Keyboards::button('➕', "ci:{$pid}:inc"),
                Keyboards::button('❌', "ci:{$pid}:del"),
            ];
        }
        $rows[] = [Keyboards::button($ctx->t('btn.clear_cart'), 'cclear'), Keyboards::button($ctx->t('btn.menu'), 'cats')];
        $rows[] = [Keyboards::button($ctx->t('btn.checkout'), 'checkout')];
        $ctx->screen(Views::cart($ctx, $summary), Keyboards::inline($rows));
    }

    public static function item(Context $ctx, string $productId = '0', string $op = ''): void
    {
        $pid = (int) $productId;
        match ($op) {
            'inc' => CartService::change($ctx->userId(), $pid, 1),
            'dec' => CartService::change($ctx->userId(), $pid, -1),
            'del' => CartService::remove($ctx->userId(), $pid),
            default => null,
        };
        self::show($ctx);
    }

    public static function clear(Context $ctx): void
    {
        CartService::clear($ctx->userId());
        $ctx->answer($ctx->t('cart_cleared'));
        self::show($ctx);
    }
}
