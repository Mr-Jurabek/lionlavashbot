<?php

declare(strict_types=1);

namespace App\Bots\Admin;

use App\Bots\Customer\Context;
use App\Bots\Customer\Keyboards;
use App\Core\I18n;
use App\Models\Product;
use App\Services\AdminAccess;
use App\Services\ServiceException;
use App\Services\SettingsService;

/**
 * Sozlamalar (settings jadvali) va mahsulotlar (faollik, narx) admin paneli orqali.
 */
final class AdminCatalogHandler
{
    private const PRICE_MAX = 100000000;

    public static function settingsList(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $rows = [];
        foreach (SettingsService::listing() as $key => [$label, $value]) {
            $shown = $value === '' ? '—' : mb_substr($value, 0, 20);
            $rows[] = [Keyboards::button($label . ': ' . $shown, 'aset:' . $key)];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.change_admin'), 'aadm')];
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:home')];
        $ctx->screen(I18n::t('uz', 'admin.settings_title'), Keyboards::inline($rows));
    }

    public static function editSetting(Context $ctx, string $key = ''): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $rule = SettingsService::rule($key);
        if ($rule === null) {
            $ctx->answer(I18n::t('uz', 'error.invalid_setting'), true);
            return;
        }
        $ctx->setState('admin_setting', ['key' => $key]);
        $ctx->reply(I18n::t('uz', 'admin.ask_setting', [
            'label' => $rule['label'],
            'value' => htmlspecialchars(\App\Models\Setting::get($key) ?: '—', ENT_QUOTES),
        ]), Keyboards::cancelOnly($ctx));
    }

    public static function settingInput(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $key = (string) ($ctx->stateData()['key'] ?? '');
        try {
            SettingsService::update($key, $ctx->text());
        } catch (ServiceException $e) {
            $ctx->reply(I18n::t('uz', 'admin.invalid_setting'), Keyboards::cancelOnly($ctx));
            return;
        }
        $ctx->setState('main');
        $ctx->reply(I18n::t('uz', 'admin.setting_saved'), AdminHandler::menuMarkup());
    }

    /** Admin ID ni almashtirish: 1-qadam — yangi ID kiritish. */
    public static function askAdminChange(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $ctx->setState('admin_change_id');
        $ctx->reply(I18n::t('uz', 'admin.ask_admin_id'), Keyboards::cancelOnly($ctx));
    }

    /** 2-qadam — tasdiqlash tugmasi. */
    public static function changeIdInput(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $value = trim($ctx->text());
        if (!ctype_digit($value) || strlen($value) < 5 || strlen($value) > 15) {
            $ctx->reply(I18n::t('uz', 'admin.invalid_setting'), Keyboards::cancelOnly($ctx));
            return;
        }
        $ctx->setState('admin_change_confirm', ['pending' => $value]);
        $ctx->reply(
            I18n::t('uz', 'admin.confirm_admin_change', ['id' => $value]),
            Keyboards::inline([[Keyboards::button(I18n::t('uz', 'admin.btn.confirm_admin'), 'aadmok')]])
        );
    }

    /** 3-qadam — faqat tasdiqlash holatida. */
    public static function confirmAdminChange(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $pending = (string) ($ctx->stateData()['pending'] ?? '');
        if ($ctx->state() !== 'admin_change_confirm' || $pending === '') {
            $ctx->answer(I18n::t('uz', 'admin.invalid_setting'), true);
            return;
        }
        try {
            AdminAccess::change($pending);
        } catch (ServiceException $e) {
            $ctx->answer(I18n::t('uz', 'admin.invalid_setting'), true);
            return;
        }
        $ctx->setState('main');
        $ctx->reply(I18n::t('uz', 'admin.admin_changed'), Keyboards::mainMenu($ctx));
    }

    public static function productsList(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $rows = [];
        foreach (Product::allForAdmin() as $p) {
            $mark = (int) $p['is_active'] === 1 ? '✅' : '⛔';
            $rows[] = [Keyboards::button($mark . ' ' . $p['name_uz'] . ' — ' . number_format((int) $p['price'], 0, '', ' '), 'aprod:' . $p['id'])];
        }
        $rows[] = [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:home')];
        $ctx->screen(I18n::t('uz', 'admin.products_title'), Keyboards::inline($rows));
    }

    public static function product(Context $ctx, string $id = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $p = Product::find((int) $id);
        if ($p === null) {
            $ctx->answer(I18n::t('uz', 'error.product_unavailable'), true);
            return;
        }
        $active = (int) $p['is_active'] === 1;
        $ctx->screen(I18n::t('uz', 'admin.product_card', [
            'name' => htmlspecialchars((string) $p['name_uz'], ENT_QUOTES),
            'price' => I18n::money('uz', (int) $p['price']),
            'active' => I18n::t('uz', $active ? 'admin.active' : 'admin.inactive'),
        ]), Keyboards::inline([
            [Keyboards::button(I18n::t('uz', $active ? 'admin.btn.toggle_off' : 'admin.btn.toggle_on'), 'aptog:' . $p['id'])],
            [Keyboards::button(I18n::t('uz', 'admin.btn.price'), 'aprice:' . $p['id'])],
            [Keyboards::button(I18n::t('uz', 'admin.btn.back'), 'adm:products')],
        ]));
    }

    public static function toggle(Context $ctx, string $id = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $p = Product::find((int) $id);
        if ($p !== null) {
            Product::setActive((int) $id, (int) $p['is_active'] !== 1);
        }
        self::product($ctx, $id);
    }

    public static function askPrice(Context $ctx, string $id = '0'): void
    {
        AdminAccess::require($ctx->telegramId());
        $ctx->answer();
        $ctx->setState('admin_price', ['product_id' => (int) $id]);
        $ctx->reply(I18n::t('uz', 'admin.ask_price'), Keyboards::cancelOnly($ctx));
    }

    public static function priceInput(Context $ctx): void
    {
        AdminAccess::require($ctx->telegramId());
        $value = trim($ctx->text());
        $productId = (int) ($ctx->stateData()['product_id'] ?? 0);
        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > self::PRICE_MAX || Product::find($productId) === null) {
            $ctx->reply(I18n::t('uz', 'admin.invalid_price'), Keyboards::cancelOnly($ctx));
            return;
        }
        Product::setPrice($productId, (int) $value);
        $ctx->setState('main');
        $ctx->reply(I18n::t('uz', 'admin.price_saved'), AdminHandler::menuMarkup());
    }
}
