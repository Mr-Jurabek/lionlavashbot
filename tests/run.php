<?php

declare(strict_types=1);

/**
 * Integratsion sinovlar: haqiqiy MySQL sinov bazasi + soxta Telegram mijozi.
 * DIQQAT: sinov bazasi har ishga tushirishda qayta yaratiladi — production bazani ko'rsatmang!
 *
 *   TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_NAME=lionlavash_test TEST_DB_USER=root TEST_DB_PASS= php tests/run.php
 */

use App\Bots\Customer\CustomerBot;
use App\Bots\Customer\CustomerNotifier;
use App\Core\Db;
use App\Core\Env;
use App\Core\Telegram;
use App\Core\Webhook;
use App\Models\Order;
use App\Models\Setting;
use App\Services\DeliveryFee;
use App\Services\OrderService;
use App\Services\ServiceException;
use App\Services\WorkHours;

if (PHP_SAPI !== 'cli') {
    exit;
}

define('LIONLAVASH_SKIP_ENV', true);
require dirname(__DIR__) . '/app/bootstrap.php';

Env::set('DB_HOST', getenv('TEST_DB_HOST') ?: '127.0.0.1');
Env::set('DB_PORT', getenv('TEST_DB_PORT') ?: '3306');
Env::set('DB_NAME', getenv('TEST_DB_NAME') ?: 'lionlavash_test');
Env::set('DB_USER', getenv('TEST_DB_USER') ?: 'root');
Env::set('DB_PASS', getenv('TEST_DB_PASS') ?: '');
Env::set('ADMIN_CHAT_ID', '-100500');

if (!str_contains((string) Env::get('DB_NAME'), 'test')) {
    fwrite(STDERR, "Xavfsizlik: TEST_DB_NAME nomida 'test' bo'lishi shart\n");
    exit(1);
}

// ---------------------------------------------------------------- yordamchilar

final class FakeTelegram extends Telegram
{
    /** @var list<array{string, array}> */
    public array $calls = [];
    private int $messageId = 1000;

    public function __construct()
    {
        parent::__construct('test');
    }

    public function call(string $method, array $params = []): mixed
    {
        $this->calls[] = [$method, $params];
        return str_starts_with($method, 'send') ? ['message_id' => ++$this->messageId] : true;
    }

    public function reset(): void
    {
        $this->calls = [];
    }

    /** @return list<array> */
    public function find(string $method): array
    {
        return array_values(array_map(
            static fn($c) => $c[1],
            array_filter($this->calls, static fn($c) => $c[0] === $method)
        ));
    }

    public function lastText(): string
    {
        for ($i = count($this->calls) - 1; $i >= 0; $i--) {
            $p = $this->calls[$i][1];
            if (isset($p['text']) || isset($p['caption'])) {
                return (string) ($p['text'] ?? $p['caption']);
            }
        }
        return '';
    }

    public function allTexts(): string
    {
        return implode("\n---\n", array_map(static fn($c) => (string) ($c[1]['text'] ?? $c[1]['caption'] ?? ''), $this->calls));
    }
}

$passed = 0;
$failed = 0;
function check(bool $condition, string $name, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        echo "  ✗ {$name}" . ($details !== '' ? "\n      {$details}" : '') . "\n";
    }
}

function section(string $title): void
{
    echo "\n{$title}\n";
}

function resetDatabase(): void
{
    foreach (['schema.sql', 'seed.sql'] as $file) {
        $sql = (string) file_get_contents(BASE_PATH . '/database/' . $file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql)))) as $statement) {
            Db::connect()->exec($statement);
        }
    }
    Setting::flush();
}

$updateId = 1;
$tg = new FakeTelegram();
$bot = new CustomerBot($tg);
OrderService::resetListeners();
(new CustomerNotifier($tg))->register();

const USER_ID = 7001;
const OTHER_ID = 7002;

/** customer.php dagi kabi: idempotentlik + handle. */
function process(array $update): bool
{
    global $bot;
    if (!Webhook::markProcessed(CustomerBot::NAME, (int) $update['update_id'])) {
        return false;
    }
    $bot->handle($update);
    return true;
}

function message(array $fields, int $from = USER_ID, string $chatType = 'private'): array
{
    global $updateId;
    return [
        'update_id' => ++$updateId,
        'message' => $fields + [
            'message_id' => $updateId,
            'from' => ['id' => $from, 'is_bot' => false, 'first_name' => 'Test <b>User</b>'],
            'chat' => ['id' => $from, 'type' => $chatType],
            'date' => time(),
        ],
    ];
}

function send(array $fields, int $from = USER_ID): array
{
    $update = message($fields, $from);
    process($update);
    return $update;
}

function text(string $text): array
{
    return send(['text' => $text]);
}

function press(string $data, bool $photoMessage = false): void
{
    global $updateId;
    $msg = ['message_id' => 555, 'chat' => ['id' => USER_ID, 'type' => 'private'], 'date' => time()];
    if ($photoMessage) {
        $msg['photo'] = [['file_id' => 'x']];
    } else {
        $msg['text'] = 'old';
    }
    process([
        'update_id' => ++$updateId,
        'callback_query' => [
            'id' => 'cb' . $updateId,
            'from' => ['id' => USER_ID, 'is_bot' => false, 'first_name' => 'Test'],
            'message' => $msg,
            'data' => $data,
        ],
    ]);
}

function user(): array
{
    return Db::one('SELECT * FROM users WHERE telegram_id = ?', [USER_ID]);
}

function lastOrder(): ?array
{
    return Db::one('SELECT * FROM orders ORDER BY id DESC LIMIT 1');
}

function countOrders(): int
{
    return (int) Db::one('SELECT COUNT(*) c FROM orders')['c'];
}

function t(string $key, array $params = []): string
{
    return \App\Core\I18n::t('uz', $key, $params);
}

// ---------------------------------------------------------------- sinovlar

resetDatabase();
Setting::set('work_hours', '00:00-00:00'); // 24 soat — oqim sinovlari vaqtga bog'liq bo'lmasin

section('1. Yetkazib berish narxi');
check(DeliveryFee::forItemsTotal(149999) === 10000, '149 999 < 150 000 → 10 000');
check(DeliveryFee::forItemsTotal(150000) === 0, '150 000 = chegara → bepul');
check(DeliveryFee::forItemsTotal(200000) === 0, '200 000 → bepul');
Setting::set('free_delivery_from', '0');
check(DeliveryFee::forItemsTotal(999999) === 10000, 'free_delivery_from = 0 → har doim pullik');
Setting::set('free_delivery_from', '150000');

section('2. Ish vaqti');
Setting::set('work_hours', '10:00-23:00');
$at = static fn(string $hm) => new DateTimeImmutable('2026-01-01 ' . $hm);
check(!WorkHours::isOpen($at('09:59')), '10:00-23:00 → 09:59 yopiq');
check(WorkHours::isOpen($at('10:00')), '10:00-23:00 → 10:00 ochiq');
check(WorkHours::isOpen($at('22:59')), '10:00-23:00 → 22:59 ochiq');
check(!WorkHours::isOpen($at('23:00')), '10:00-23:00 → 23:00 yopiq');
Setting::set('work_hours', '10:00-02:00');
check(WorkHours::isOpen($at('01:30')), '10:00-02:00 → 01:30 ochiq (tungi)');
check(!WorkHours::isOpen($at('03:00')), '10:00-02:00 → 03:00 yopiq');
Setting::set('work_hours', '00:00-00:00');

section('3. Ro\'yxatdan o\'tish');
text('/start');
check(isset($tg->find('sendMessage')[0]['reply_markup']['inline_keyboard']), '/start → til tanlash (inline)');
$tg->reset();
text('salom');
check(str_contains($tg->lastText(), 'Tilni tanlang'), 'til tanlanmaguncha boshqa xabar → yana til so\'raladi');
press('lang:uz');
check(user()['lang'] === 'uz', 'til saqlandi');
check(str_contains($tg->allTexts(), t('ask_phone')), 'telefon so\'raldi');
$tg->reset();
send(['contact' => ['phone_number' => '998901112233', 'user_id' => OTHER_ID, 'first_name' => 'Boshqa']]);
check(user()['phone'] === null, 'boshqa odamning kontakti rad etildi');
send(['contact' => ['phone_number' => '998901234567', 'user_id' => USER_ID, 'first_name' => 'Test']]);
check(user()['phone'] === '+998901234567', 'o\'z raqami saqlandi');
check(user()['state'] === 'main', 'asosiy menyu holati');
check(str_contains($tg->lastText(), 'Test &lt;b&gt;User&lt;/b&gt;'), 'ism HTML uchun tozalandi');

section('4. Menyu va savat');
$tg->reset();
text(t('btn.menu'));
$kb = $tg->find('sendMessage')[0]['reply_markup']['inline_keyboard'] ?? [];
check(($kb[0][0]['callback_data'] ?? '') === 'cat:1', 'kategoriyalar ro\'yxati');
$tg->reset();
press('cat:1');
check(count($tg->find('editMessageText')) === 1, 'kategoriya → xabar tahrirlandi (yangi xabar emas)');
$tg->reset();
press('prod:1');
check(str_contains($tg->lastText(), 'Gamburger') && str_contains($tg->lastText(), '1 ta'), 'mahsulot kartochkasi');
$tg->reset();
press('prod:1:2');
check(str_contains($tg->lastText(), '2 ta') && str_contains($tg->lastText(), '56 000'), '+ → miqdor 2, jami 56 000');
press('add:1:2');
press('add:4:1');
$tg->reset();
press('ci:4:inc');
check(str_contains($tg->lastText(), '2 × 30 000'), 'savatda miqdor oshirildi');
check(str_contains($tg->lastText(), '116 000') && str_contains($tg->lastText(), '126 000'), 'savat: 116 000 + 10 000 = 126 000');
press('add:9:1');
press('ci:9:del');
check((int) Db::one('SELECT COUNT(*) c FROM cart_items')['c'] === 2, 'mahsulot savatdan o\'chirildi');
$tg->reset();
press('prod:1:999');
check(str_contains($tg->lastText(), '50 ta'), 'miqdor 50 bilan cheklangan');
Db::run('UPDATE products SET photo_file_id = ? WHERE id = 2', ['PHOTO2']);
$tg->reset();
press('prod:2');
check(count($tg->find('deleteMessage')) === 1 && count($tg->find('sendPhoto')) === 1, 'matn → rasmli kartochka: o\'chirib, rasm yuborildi');
$tg->reset();
press('prod:2:3', true);
check(count($tg->find('editMessageCaption')) === 1, 'rasmli kartochkada miqdor → caption tahrirlandi');

section('5. Naqd buyurtma');
$tg->reset();
press('checkout');
check(user()['state'] === 'checkout_address', 'manzil so\'raldi');
text('Al');
check(user()['state'] === 'checkout_address', 'qisqa manzil rad etildi');
text('Chilonzor 9, 12-uy <script>');
check(user()['state'] === 'checkout_comment', 'manzil qabul qilindi');
text(t('btn.skip'));
check(user()['state'] === 'checkout_payment', 'izoh o\'tkazib yuborildi');
text('nimadir');
check(user()['state'] === 'checkout_payment', 'noto\'g\'ri to\'lov usuli → yana so\'raladi');
text(t('btn.cash'));
check(user()['state'] === 'checkout_confirm', 'tasdiqlash ekrani');
check(str_contains($tg->lastText(), '126 000') && str_contains($tg->lastText(), '&lt;script&gt;'), 'tasdiqlash: jami va tozalangan manzil');
$tg->reset();
$confirmUpdate = text(t('btn.confirm'));
$order = lastOrder();
check($order !== null && $order['status'] === 'new' && $order['payment_method'] === 'cash', 'buyurtma yaratildi: new, cash');
check((int) $order['items_total'] === 116000 && (int) $order['delivery_fee'] === 10000 && (int) $order['total'] === 126000, 'summalar to\'g\'ri');
check((int) Db::one('SELECT COUNT(*) c FROM cart_items')['c'] === 0, 'savat tozalandi');
check((int) Db::one('SELECT COUNT(*) c FROM order_items WHERE order_id = ?', [$order['id']])['c'] === 2, 'order_items: 2 qator (snapshot)');
$adminMsgs = array_filter($tg->find('sendMessage'), static fn($p) => $p['chat_id'] === '-100500');
check(count($adminMsgs) === 1, 'ADMIN_CHAT_ID ga xabar yuborildi');
check(user()['state'] === 'main', 'holat main ga qaytdi');

$before = countOrders();
check(process($confirmUpdate) === false, 'xuddi shu update_id qayta → qayta ishlanmadi');
text(t('btn.confirm'));
check(countOrders() === $before, '«Tasdiqlash» ikkinchi marta (yangi update_id) → ikkinchi buyurtma yo\'q');

section('6. Karta buyurtma va chek');
press('add:3:4'); // 4 × 45 000 = 180 000 ≥ 150 000 → bepul
press('checkout');
send(['location' => ['latitude' => 41.311081, 'longitude' => 69.240562]]);
text('3-podyezd, 5-qavat');
text(t('btn.card'));
$tg->reset();
text(t('btn.confirm'));
$card = lastOrder();
check($card['status'] === 'awaiting_payment' && $card['payment_method'] === 'card', 'karta: avtomatik awaiting_payment');
check((int) $card['delivery_fee'] === 0 && (int) $card['total'] === 180000, '180 000 → yetkazish bepul');
check($card['latitude'] !== null && $card['address_text'] === null, 'lokatsiya saqlandi');
check(str_contains($tg->allTexts(), '8600 0000 0000 0000'), 'karta raqami ko\'rsatildi');
check(user()['state'] === 'await_receipt', 'chek kutilmoqda');
$tg->reset();
text('mana chek');
check(str_contains($tg->lastText(), t('receipt_expected')), 'matn chek emas → qayta so\'raldi');
send(['photo' => [['file_id' => 'small'], ['file_id' => 'RECEIPT_BIG']]]);
$card = Order::find((int) $card['id']);
check($card['status'] === 'payment_review' && $card['receipt_file_id'] === 'RECEIPT_BIG', 'chek → payment_review, eng katta rasm saqlandi');
check($card['payment_status'] === 'pending_review', 'payment_status = pending_review');
check(($tg->find('sendPhoto')[0]['chat_id'] ?? null) === '-100500', 'chek admin chatiga yuborildi');

$tg->reset();
OrderService::transition((int) $card['id'], 'awaiting_payment', 'cli');
$card = Order::find((int) $card['id']);
check($card['payment_status'] === 'rejected', 'rad etildi → payment_status = rejected');
$rejectMsg = $tg->find('sendMessage')[0] ?? [];
check(($rejectMsg['reply_markup']['inline_keyboard'][0][0]['callback_data'] ?? '') === 'rcpt:' . $card['id'], 'mijozga «chek yuborish» tugmasi bilan xabar');
press('rcpt:' . $card['id']);
check(user()['state'] === 'await_receipt', 'qayta chek yuborish holati');
send(['document' => ['file_id' => 'PDF1', 'mime_type' => 'application/pdf']]);
check(Order::find((int) $card['id'])['status'] === 'payment_review', 'PDF chek qabul qilindi');
check(count($tg->find('sendDocument')) === 1, 'PDF admin chatiga sendDocument bilan');
$tg->reset();
$card = OrderService::transition((int) $card['id'], 'accepted', 'cli');
check($card['payment_status'] === 'paid', 'tasdiqlandi → paid');
check(str_contains($tg->lastText(), t('status.accepted')), 'mijozga «qabul qilindi» xabari');

section('7. Holat o\'tishlari');
$cashId = (int) $order['id'];
foreach (['accepted', 'preparing', 'ready', 'on_the_way', 'delivered'] as $s) {
    OrderService::transition($cashId, $s, 'cli');
}
$cash = Order::find($cashId);
check($cash['status'] === 'delivered' && $cash['payment_status'] === 'paid', 'naqd: new → … → delivered, paid');
$rejected = static function (int $id, string $to): bool {
    try {
        OrderService::transition($id, $to, 'cli');
        return false;
    } catch (ServiceException $e) {
        return $e->reason === 'invalid_transition';
    }
};
check($rejected($cashId, 'new'), 'delivered → new rad etildi');
check($rejected($cashId, 'cancelled'), 'delivered → cancelled rad etildi');
$logCount = (int) Db::one('SELECT COUNT(*) c FROM order_status_log WHERE order_id = ?', [$cashId])['c'];
check($logCount === 6, 'order_status_log: 6 yozuv (yaratish + 5 o\'tish)', "topildi: {$logCount}");

$base = ['user_id' => user()['id'], 'items_total' => 1, 'delivery_fee' => 0, 'total' => 1, 'address_text' => 'x',
    'latitude' => null, 'longitude' => null, 'phone' => '+1', 'comment' => null];
$newCash = Order::create($base + ['status' => 'new', 'payment_method' => 'cash']);
$newCard = Order::create($base + ['status' => 'new', 'payment_method' => 'card']);
check($rejected($newCash, 'awaiting_payment'), 'naqd: new → awaiting_payment rad etildi');
check($rejected($newCard, 'accepted'), 'karta: new → accepted (to\'lovsiz) rad etildi');
check($rejected($newCash, 'delivered'), 'new → delivered (sakrash) rad etildi');
OrderService::transition($newCash, 'cancelled', 'cli');
check(Order::find($newCash)['status'] === 'cancelled', 'faol holatdan → cancelled');

section('8. Boshqa holatlar');
press('add:1:1');
press('checkout');
text(t('btn.cart'));
check(user()['state'] === 'main', 'rasmiylashtirish paytida menyu tugmasi → holat bekor');
// Hozirgi vaqtni o'z ichiga olmaydigan oraliq: +2 soatdan +3 soatgacha
Setting::set('work_hours', date('H:i', strtotime('+2 hours')) . '-' . date('H:i', strtotime('+3 hours')));
$tg->reset();
press('checkout');
check(str_contains($tg->allTexts(), 'Ish vaqtimiz') || str_contains($tg->allTexts(), 'qabul qilinmaydi'), 'ish vaqtidan tashqari → ish vaqti haqida xabar');
check(user()['state'] === 'main', 'ish vaqtidan tashqari → rasmiylashtirish boshlanmadi');
Setting::set('work_hours', '00:00-00:00');
$tg->reset();
process(message(['text' => '/start'], USER_ID, 'group'));
check($tg->calls === [], 'guruh chatidagi xabar e\'tiborsiz qoldirildi');
$tg->reset();
press('rcpt:' . $cashId);
check(($tg->find('answerCallbackQuery')[0]['show_alert'] ?? false) === true, 'yetkazilgan buyurtmaga chek → rad (alert)');
$tg->reset();
text(t('btn.orders'));
check(str_contains($tg->lastText(), '#' . $cashId), 'Buyurtmalarim ro\'yxati');
press('lang:ru');
$tg->reset();
text('🛒 Корзина');
check(str_contains($tg->lastText(), 'Корзина'), 'til ru ga o\'zgardi, ruscha tugma ishlaydi');

// ---------------------------------------------------------------- natija
echo "\nNatija: {$passed} o'tdi, {$failed} xato\n";
exit($failed === 0 ? 0 : 1);
