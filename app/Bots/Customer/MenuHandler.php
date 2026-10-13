<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Models\Category;
use App\Models\Product;
use App\Services\CartService;

/**
 * Kategoriyalar → mahsulotlar → mahsulot kartochkasi (−/+) → savatga qo'shish.
 */
final class MenuHandler
{
    private const DESCRIPTION_MAX = 700; // rasm izohi 1024 belgidan oshmasligi uchun

    public static function categories(Context $ctx): void
    {
        $categories = Category::allActive();
        if ($categories === []) {
            $ctx->screen($ctx->t('no_categories'), null);
            return;
        }
        $buttons = array_map(
            static fn(array $c) => Keyboards::button($ctx->localized($c, 'name'), 'cat:' . $c['id']),
            $categories
        );
        $rows = array_chunk($buttons, 2);
        $rows[] = [Keyboards::button($ctx->t('btn.cart'), 'cart')];
        $ctx->screen($ctx->t('categories'), Keyboards::inline($rows));
    }

    public static function category(Context $ctx, string $id = '0'): void
    {
        $category = Category::findActive((int) $id);
        if ($category === null) {
            self::categories($ctx);
            return;
        }
        $products = Product::activeByCategory((int) $category['id']);
        $rows = [];
        foreach ($products as $p) {
            $rows[] = [Keyboards::button(
                $ctx->localized($p, 'name') . ' — ' . $ctx->money((int) $p['price']),
                'prod:' . $p['id']
            )];
        }
        $rows[] = [Keyboards::button($ctx->t('btn.back'), 'cats'), Keyboards::button($ctx->t('btn.cart'), 'cart')];
        $text = $products === []
            ? $ctx->t('no_products')
            : $ctx->t('products', ['category' => Views::e($ctx->localized($category, 'name'))]);
        $ctx->screen($text, Keyboards::inline($rows));
    }

    /** Mahsulot kartochkasi. Miqdor callback_data ichida saqlanadi — bazaga yozilmaydi. */
    public static function product(Context $ctx, string $id = '0', string $qty = '1'): void
    {
        $product = Product::findActive((int) $id);
        if ($product === null) {
            $ctx->answer($ctx->t('error.product_unavailable'), true);
            self::categories($ctx);
            return;
        }
        $qty = max(1, CartService::clamp((int) $qty));
        $description = trim($ctx->localized($product, 'description'));
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $description = mb_substr($description, 0, self::DESCRIPTION_MAX) . '…';
        }
        $price = (int) $product['price'];
        $text = $ctx->t('product_card', [
            'name' => Views::e($ctx->localized($product, 'name')),
            'description' => $description === '' ? '' : Views::e($description) . "\n",
            'price' => $ctx->money($price),
            'qty' => $qty,
            'total' => $ctx->money($price * $qty),
        ]);
        $pid = $product['id'];
        $markup = Keyboards::inline([
            [
                Keyboards::button('➖', 'prod:' . $pid . ':' . max(1, $qty - 1)),
                Keyboards::button((string) $qty, 'noop'),
                Keyboards::button('➕', 'prod:' . $pid . ':' . min(CartService::MAX_QUANTITY, $qty + 1)),
            ],
            [Keyboards::button($ctx->t('btn.add_to_cart'), 'add:' . $pid . ':' . $qty)],
            [Keyboards::button($ctx->t('btn.back'), 'cat:' . $product['category_id'])],
        ]);
        $photo = $product['photo_file_id'] !== null && $product['photo_file_id'] !== '' ? (string) $product['photo_file_id'] : null;
        $ctx->screen($text, $markup, $photo);
    }

    public static function add(Context $ctx, string $id = '0', string $qty = '1'): void
    {
        $product = Product::findActive((int) $id);
        if ($product === null) {
            $ctx->answer($ctx->t('error.product_unavailable'), true);
            self::categories($ctx);
            return;
        }
        $inCart = CartService::add($ctx->userId(), (int) $product['id'], max(1, (int) $qty));
        $ctx->answer($ctx->t('added_to_cart', ['qty' => $inCart]));
        self::category($ctx, (string) $product['category_id']);
    }
}
