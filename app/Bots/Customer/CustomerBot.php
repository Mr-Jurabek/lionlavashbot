<?php

declare(strict_types=1);

namespace App\Bots\Customer;

use App\Bots\Admin\AdminCatalogHandler;
use App\Bots\Admin\AdminCouriersHandler;
use App\Bots\Courier\CourierHandler;
use App\Bots\Admin\AdminHandler;
use App\Bots\Admin\AdminOrdersHandler;
use App\Core\I18n;
use App\Core\Logger;
use App\Core\Router;
use App\Core\Telegram;
use App\Models\User;
use App\Services\ServiceException;

/**
 * Mijoz boti (admin funksiyalari ham shu bot ichida, faqat admin ID si uchun).
 * update → foydalanuvchi → ro'yxatdan o'tish tekshiruvi → Router → handler.
 */
final class CustomerBot
{
    public const NAME = 'customer';

    private ?Router $router = null;

    public function __construct(private readonly Telegram $tg)
    {
    }

    public function handle(array $update): void
    {
        $from = $update['message']['from'] ?? $update['callback_query']['from'] ?? null;
        $chatType = $update['message']['chat']['type'] ?? $update['callback_query']['message']['chat']['type'] ?? 'private';
        // Faqat shaxsiy chat (bot admin guruhiga qo'shilgan bo'lsa, guruh xabarlari e'tiborsiz qoldiriladi)
        if ($from === null || $chatType !== 'private' || !empty($from['is_bot'])) {
            return;
        }

        $user = User::touch((int) $from['id'], (string) ($from['first_name'] ?? ''));
        $ctx = new Context($this->tg, $update, $user);

        try {
            if ($this->registrationComplete($ctx)) {
                $this->router()->dispatch($update, $ctx->state(), $ctx);
            }
        } catch (ServiceException $e) {
            $this->showServiceError($ctx, $e);
        } catch (\Throwable $e) {
            Logger::exception($e, ['update_id' => $update['update_id'] ?? null]);
            $ctx->answer();
            $ctx->reply($ctx->t('error.generic'));
        } finally {
            $ctx->answer();
        }
    }

    /** Til yoki telefon yo'q bo'lsa — ro'yxatdan o'tish bosqichiga qaytaradi. */
    private function registrationComplete(Context $ctx): bool
    {
        $callbackPrefix = explode(':', (string) ($ctx->callback()['data'] ?? ''))[0];
        if (empty($ctx->user['lang'])) {
            if ($callbackPrefix === 'lang') {
                return true;
            }
            StartHandler::askLang($ctx);
            return false;
        }
        if (empty($ctx->user['phone'])) {
            if ($callbackPrefix === 'lang') {
                return true;
            }
            if (isset($ctx->message()['contact'])) {
                StartHandler::contact($ctx);
                return false;
            }
            StartHandler::askPhone($ctx);
            return false;
        }
        return true;
    }

    private function showServiceError(Context $ctx, ServiceException $e): void
    {
        if ($e->reason === 'closed') {
            $ctx->answer();
            CheckoutHandler::closed($ctx);
            return;
        }
        $text = $ctx->t('error.' . $e->reason);
        if ($ctx->callback() !== null) {
            $ctx->answer(strip_tags($text), true);
            return;
        }
        $ctx->setState('main');
        $ctx->reply($text, Keyboards::mainMenu($ctx));
    }

    private function router(): Router
    {
        if ($this->router !== null) {
            return $this->router;
        }
        // Asosiy menyu tugmalari har qanday holatda ishlaydi va joriy jarayonni bekor qiladi
        $menu = static fn(string $key, callable $handler) => [
            static fn(string $text): bool => I18n::matches($key, $text),
            static function (Context $ctx) use ($handler): void {
                $ctx->setState('main');
                $handler($ctx);
            },
        ];

        $r = new Router();
        $r->command('start', [StartHandler::class, 'start']);

        $r->text(...$menu('btn.menu', [MenuHandler::class, 'categories']));
        $r->text(...$menu('btn.cart', [CartHandler::class, 'show']));
        $r->text(...$menu('btn.orders', [OrdersHandler::class, 'list']));
        $r->text(...$menu('btn.help', [SupportHandler::class, 'ask']));
        $r->text(...$menu('btn.contact', [StartHandler::class, 'contactInfo']));
        $r->text(...$menu('btn.lang', [StartHandler::class, 'languageMenu']));
        $r->text(...$menu('btn.admin', [AdminHandler::class, 'home']));
        $r->text(...$menu('btn.courier', [CourierHandler::class, 'home']));
        $r->text(static fn(string $t): bool => I18n::matches('btn.cancel', $t), [CheckoutHandler::class, 'cancel']);

        $r->state('checkout_address', [CheckoutHandler::class, 'address']);
        $r->state('checkout_details', [CheckoutHandler::class, 'details']);
        $r->state('checkout_comment', [CheckoutHandler::class, 'comment']);
        $r->state('checkout_payment', [CheckoutHandler::class, 'payment']);
        $r->state('checkout_confirm', [CheckoutHandler::class, 'confirm']);
        $r->state('await_receipt', [PaymentHandler::class, 'receipt']);
        $r->state('help_message', [SupportHandler::class, 'message']);
        $r->state('admin_msg_order', [AdminHandler::class, 'orderForMessage']);
        $r->state('admin_reply', [AdminHandler::class, 'replyText']);
        $r->state('admin_setting', [AdminCatalogHandler::class, 'settingInput']);
        $r->state('admin_change_id', [AdminCatalogHandler::class, 'changeIdInput']);
        $r->state('admin_price', [AdminCatalogHandler::class, 'priceInput']);
        $r->state('admin_courier_add', [AdminCouriersHandler::class, 'addInput']);
        $r->fallback([StartHandler::class, 'fallback']);

        $r->callback('lang', [StartHandler::class, 'chooseLang']);
        $r->callback('cats', [MenuHandler::class, 'categories']);
        $r->callback('cat', [MenuHandler::class, 'category']);
        $r->callback('prod', [MenuHandler::class, 'product']);
        $r->callback('add', [MenuHandler::class, 'add']);
        $r->callback('cart', [CartHandler::class, 'show']);
        $r->callback('ci', [CartHandler::class, 'item']);
        $r->callback('cclear', [CartHandler::class, 'clear']);
        $r->callback('checkout', [CheckoutHandler::class, 'start']);
        $r->callback('rcpt', [PaymentHandler::class, 'startReceipt']);
        // Admin (har bir handler ichida AdminAccess::require() tekshiradi)
        $r->callback('adm', [AdminHandler::class, 'section']);
        $r->callback('rp', [AdminHandler::class, 'replyStart']);
        $r->callback('aord', [AdminOrdersHandler::class, 'show']);
        $r->callback('ast', [AdminOrdersHandler::class, 'setStatus']);
        $r->callback('arc', [AdminOrdersHandler::class, 'receipt']);
        $r->callback('aset', [AdminCatalogHandler::class, 'editSetting']);
        $r->callback('aadm', [AdminCatalogHandler::class, 'askAdminChange']);
        $r->callback('aadmok', [AdminCatalogHandler::class, 'confirmAdminChange']);
        $r->callback('aprod', [AdminCatalogHandler::class, 'product']);
        $r->callback('aptog', [AdminCatalogHandler::class, 'toggle']);
        $r->callback('aprice', [AdminCatalogHandler::class, 'askPrice']);
        $r->callback('acadd', [AdminCouriersHandler::class, 'askAdd']);
        $r->callback('acok', [AdminCouriersHandler::class, 'confirm']);
        $r->callback('actog', [AdminCouriersHandler::class, 'toggle']);
        $r->callback('acpick', [AdminCouriersHandler::class, 'pick']);
        $r->callback('asgn', [AdminCouriersHandler::class, 'assign']);
        // Kuryer menyusi
        $r->callback('cmenu', [CourierHandler::class, 'menu']);
        $r->callback('cord', [CourierHandler::class, 'show']);
        $r->callback('cgo', static fn(Context $ctx, string $id = '0') => CourierHandler::act($ctx, $id, 'pickup'));
        $r->callback('cdone', static fn(Context $ctx, string $id = '0') => CourierHandler::act($ctx, $id, 'deliver'));
        $r->callback('ccash', static fn(Context $ctx, string $id = '0') => CourierHandler::act($ctx, $id, 'cash'));
        $r->callbackFallback(static fn(Context $ctx) => $ctx->answer());

        return $this->router = $r;
    }
}
