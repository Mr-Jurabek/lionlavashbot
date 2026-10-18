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
use App\Core\I18n;
use App\Models\Product;
use App\Services\AdminAccess;
use App\Services\DeliveryFee;
use App\Services\DeliveryZone;
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
Env::set('ADMIN_TG_ID', '9001');

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

function press(string $data, bool $photoMessage = false, int $from = USER_ID): void
{
    global $updateId;
    $msg = ['message_id' => 555, 'chat' => ['id' => $from, 'type' => 'private'], 'date' => time()];
    if ($photoMessage) {
        $msg['photo'] = [['file_id' => 'x']];
    } else {
        $msg['text'] = 'old';
    }
    process([
        'update_id' => ++$updateId,
        'callback_query' => [
            'id' => 'cb' . $updateId,
            'from' => ['id' => $from, 'is_bot' => false, 'first_name' => 'Test'],
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

// Oshxona lokatsiyasi va radius (admin paneli sozlamalari o'rniga)
Setting::set('restaurant_lat', '41.311081');
Setting::set('restaurant_lng', '69.240562');
Setting::set('delivery_radius_km', '5');

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
check(user()['state'] === 'checkout_address', 'lokatsiya so\'raldi');
text('Al');
check(user()['state'] === 'checkout_address', 'matn manzil qabul qilinmadi (lokatsiya majburiy)');
send(['location' => ['latitude' => 41.0, 'longitude' => 69.0]]);
check(user()['state'] === 'checkout_address', 'radiusdan tashqari lokatsiya rad etildi');
send(['location' => ['latitude' => 41.311081, 'longitude' => 69.240562]]);
check(user()['state'] === 'checkout_details', 'lokatsiya qabul qilindi');
text('Chilonzor 9, 12-uy <script>');
check(user()['state'] === 'checkout_comment', 'podyezd/xonadon matni qabul qilindi');
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
text(t('btn.skip'));
text(t('btn.card'));
$tg->reset();
text(t('btn.confirm'));
$card = lastOrder();
check($card['status'] === 'awaiting_payment' && $card['payment_method'] === 'card', 'karta: avtomatik awaiting_payment');
check((int) $card['delivery_fee'] === 0 && (int) $card['total'] === 180000, '180 000 → yetkazish bepul');
check($card['latitude'] !== null && $card['address_text'] === '3-podyezd, 5-qavat', 'lokatsiya va podyezd saqlandi');
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
check($cash['status'] === 'delivered' && $cash['payment_status'] === 'unpaid', 'naqd: delivered, lekin kuryer tasdiqlaguncha unpaid');
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

section('9. Radius (havo chizig\'i)');
check(DeliveryZone::distanceKm(41.311081, 69.240562, 41.311081, 69.240562) < 1e-9, 'bir nuqta → 0 km');
$oneDegree = DeliveryZone::distanceKm(41.0, 69.0, 42.0, 69.0);
check(abs($oneDegree - 111.2) < 0.5, 'kenglik bo\'yicha 1° ≈ 111 km', "topildi: {$oneDegree}");
check(DeliveryZone::radiusKm() === 5.0, 'radius standart 5 km');
Setting::set('delivery_radius_km', '1');
check(DeliveryZone::radiusKm() === 3.0, 'radius 3 km dan kichik bo\'lmaydi');
Setting::set('delivery_radius_km', '5');

section('10. Admin paneli');
const ADMIN = 9001;
Db::run("INSERT INTO users (telegram_id, first_name, phone, lang) VALUES (?, ?, ?, ?)", [ADMIN, 'Admin', '+998900000001', 'uz']);
$adminUserId = (int) Db::one('SELECT id FROM users WHERE telegram_id = ?', [ADMIN])['id'];
function stateOf(int $telegramId): ?string
{
    return Db::one('SELECT state FROM users WHERE telegram_id = ?', [$telegramId])['state'] ?? null;
}
function alertShown(FakeTelegram $tg): bool
{
    return ($tg->find('answerCallbackQuery')[0]['show_alert'] ?? false) === true;
}
function sentTo(FakeTelegram $tg, int $chatId): array
{
    return array_values(array_filter($tg->find('sendMessage'), static fn($p) => $p['chat_id'] === $chatId));
}

$tg->reset();
press('adm:orders', false, USER_ID);
check(alertShown($tg), 'oddiy foydalanuvchi admin bo\'limini ocha olmaydi');
$tg->reset();
send(['text' => I18n::t('uz', 'btn.admin')], USER_ID);
check(str_contains($tg->lastText(), 'недоступно'), 'oddiy foydalanuvchi «Admin» tugmasiga javob olmaydi');

$tg->reset();
send(['text' => I18n::t('uz', 'btn.admin')], ADMIN);
check(str_contains($tg->lastText(), 'Admin paneli'), 'admin «Admin» tugmasi bilan paneli ochildi');
check(str_contains(json_encode($tg->calls), 'adm:orders') && str_contains(json_encode($tg->calls), 'adm:settings'), 'admin tugmalari ko\'rinadi');
check(AdminAccess::isAdmin(ADMIN) && !AdminAccess::isAdmin(USER_ID), 'admin ID .env dan olinadi');

$testOrder = Order::create([
    'user_id' => $adminUserId, 'status' => 'new', 'payment_method' => 'cash',
    'items_total' => 1000, 'delivery_fee' => 0, 'total' => 1000,
    'address_text' => null, 'latitude' => 41.3, 'longitude' => 69.2,
    'phone' => '+998900000001', 'comment' => null,
]);
$tg->reset();
press('adm:orders', false, ADMIN);
check(str_contains($tg->lastText(), 'Ochiq buyurtmalar'), 'ochiq buyurtmalar ro\'yxati');
press('aord:' . $testOrder, false, ADMIN);
check(str_contains(json_encode($tg->calls), "ast:{$testOrder}:accepted"), 'kartochkada «accepted» tugmasi bor');
check(!str_contains(json_encode($tg->calls), "ast:{$testOrder}:delivered"), 'kartochkada noto\'g\'ri o\'tish tugmasi yo\'q');
press('ast:' . $testOrder . ':accepted', false, ADMIN);
check(Order::find($testOrder)['status'] === 'accepted', 'admin buyurtmani qabul qildi');
$tg->reset();
press('ast:' . $testOrder . ':delivered', false, ADMIN);
check(alertShown($tg) && Order::find($testOrder)['status'] === 'accepted', 'accepted → delivered admin uchun ham rad etildi');

// Sozlamalar
$tg->reset();
press('adm:settings', false, ADMIN);
check(str_contains(json_encode($tg->calls), 'aset:delivery_radius_km'), 'sozlamalar ro\'yxati');
press('aset:delivery_fee', false, ADMIN);
check(stateOf(ADMIN) === 'admin_setting', 'sozlama qiymati so\'raldi');
send(['text' => 'abc'], ADMIN);
check(Setting::get('delivery_fee') === '10000', 'matn qiymat qabul qilinmadi');
send(['text' => '12000'], ADMIN);
check(Setting::get('delivery_fee') === '12000' && stateOf(ADMIN) === 'main', 'yetkazish narxi o\'zgardi');
Setting::set('delivery_fee', '10000');

press('aset:delivery_radius_km', false, ADMIN);
send(['text' => '7'], ADMIN);
check(Setting::get('delivery_radius_km') === '5', 'radius 7 km rad etildi (3–5 oralig\'ida)');
send(['text' => '4'], ADMIN);
check(Setting::get('delivery_radius_km') === '4', 'radius 4 km saqlandi');
Setting::set('delivery_radius_km', '5');

press('aset:restaurant_phone', false, ADMIN);
send(['text' => '+998 90 111 22 33'], ADMIN);
check(Setting::get('restaurant_phone') === '+998 90 111 22 33', 'telefon saqlandi');
press('aset:card_number', false, ADMIN);
send(['text' => 'abcd'], ADMIN);
check(Setting::get('card_number') === '8600 0000 0000 0000', 'noto\'g\'ri karta raqami rad etildi');

$tg->reset();
press('aset:delivery_fee', false, USER_ID);
check(alertShown($tg), 'oddiy foydalanuvchi sozlamani o\'zgartira olmaydi');

// Mahsulotlar
$tg->reset();
press('adm:products', false, ADMIN);
check(str_contains(json_encode($tg->calls), 'aprod:1'), 'mahsulotlar ro\'yxati');
press('aptog:1', false, ADMIN);
check((int) Product::find(1)['is_active'] === 0, 'mahsulot nofaol qilindi');
check(Product::findActive(1) === null, 'nofaol mahsulot menyuda ko\'rinmaydi');
press('aptog:1', false, ADMIN);
check(Product::findActive(1) !== null, 'mahsulot qayta faollashtirildi');
press('aprice:1', false, ADMIN);
send(['text' => '0'], ADMIN);
check((int) Product::find(1)['price'] === 28000, 'nol narx rad etildi');
send(['text' => '31000'], ADMIN);
check((int) Product::find(1)['price'] === 31000, 'narx o\'zgardi');
Product::setPrice(1, 28000);

// Admin almashtirish (tasdiqlash bilan)
press('aadm', false, ADMIN);
send(['text' => '90021'], ADMIN);
check(stateOf(ADMIN) === 'admin_change_confirm', 'yangi ID tasdiqlash bosqichiga o\'tdi');
press('aadmok', false, ADMIN);
check(Setting::get('admin_tg_id') === '90021', 'admin settings jadvalida almashtirildi');
check(!AdminAccess::isAdmin(ADMIN) && AdminAccess::isAdmin(90021), 'eski admin huquqi yo\'qoldi, yangisi oldi');
$tg->reset();
press('adm:orders', false, ADMIN);
check(alertShown($tg), 'eski admin endi panelga kira olmaydi');
Setting::set('admin_tg_id', '');
check(AdminAccess::isAdmin(ADMIN), 'settings bo\'sh bo\'lsa .env dagi admin ishlaydi');

section('11. Yordam va mijoz bilan bog\'lanish');
$tg->reset();
text(I18n::t('ru', 'btn.help'));
check(stateOf(USER_ID) === 'help_message', 'yordam: xabar so\'raldi');
$tg->reset();
text('Buyurtmam <b>kechikdi</b>');
$toAdmin = sentTo($tg, ADMIN);
check(count($toAdmin) === 1 && str_contains($toAdmin[0]['text'], '&lt;b&gt;kechikdi'), 'yordam xabari admin shaxsiy akkauntiga yuborildi (HTML tozalangan)');
check(str_contains(json_encode($toAdmin[0]['reply_markup'] ?? []), 'rp:' . USER_ID), 'admin xabarida «Javob» tugmasi bor');
check(stateOf(USER_ID) === 'main', 'yordamdan keyin menyuga qaytdi');

press('rp:' . USER_ID, false, ADMIN);
check(stateOf(ADMIN) === 'admin_reply', 'admin javob rejimiga o\'tdi');
$tg->reset();
send(['text' => 'Salom, tez yetkazamiz'], ADMIN);
$toCustomer = sentTo($tg, USER_ID);
check(count($toCustomer) === 1 && str_contains($toCustomer[0]['text'], 'Salom, tez yetkazamiz') && str_contains($toCustomer[0]['text'], 'Lion Lavash'), 'javob mijozga yetkazildi');

$tg->reset();
press('rp:' . USER_ID, false, USER_ID);
check(alertShown($tg), 'oddiy foydalanuvchi javob tugmasini ishlata olmaydi');

Setting::set('admin_username', '@lion_admin');
$tg->reset();
text(I18n::t('ru', 'btn.contact'));
check(str_contains($tg->lastText(), 'lion_admin'), 'aloqa: admin username ko\'rsatildi');
Setting::set('admin_username', '');

section('12. Kuryer (bitta bot ichida)');
const COURIER = 91001;
const OTHER_COURIER = 91011;
Db::run("INSERT INTO users (telegram_id, first_name, phone, lang) VALUES (?, ?, ?, ?)", [COURIER, 'Kuryer', '+998900000002', 'uz']);
Db::run("INSERT INTO users (telegram_id, first_name, phone, lang) VALUES (?, ?, ?, ?)", [OTHER_COURIER, 'Boshqa', '+998900000003', 'uz']);
$uid = (int) user()['id'];

$tg->reset();
press('acadd', false, ADMIN);
check(stateOf(ADMIN) === 'admin_courier_add', 'admin kuryer qo\'shish bosqichiga o\'tdi');
send(['forward_sender_name' => 'Yashirin'], ADMIN);
check(stateOf(ADMIN) === 'admin_courier_add', 'forward yopiq bo\'lsa, ID so\'raldi');
send(['text' => '123'], ADMIN);
check(stateOf(ADMIN) === 'admin_courier_add', '/start bosmagan ID qabul qilinmadi');
send(['forward_from' => ['id' => COURIER, 'first_name' => 'Kuryer', 'is_bot' => false]], ADMIN);
check(stateOf(ADMIN) === 'admin_courier_confirm', 'forward orqali kuryer aniqlandi');
press('acok', false, ADMIN);
check(\App\Services\CourierService::isActive(COURIER), 'kuryer tasdiqlandi va faol');

$courierId = (int) Db::one('SELECT id FROM couriers WHERE telegram_id = ?', [COURIER])['id'];
\App\Services\CourierService::add(OTHER_COURIER, 'Boshqa kuryer');
check(!\App\Services\CourierService::isActive(USER_ID), 'oddiy mijoz kuryer emas');
$tg->reset();
send(['text' => I18n::t('uz', 'btn.courier')], COURIER);
check(str_contains($tg->lastText(), 'tayinlangan buyurtma yo\'q'), 'kuryer menyusi ochildi (buyurtma yo\'q)');

// Buyurtma: kuryersiz → tayinlash
$courierOrder = Order::create([
    'user_id' => $uid, 'status' => 'accepted', 'payment_method' => 'cash',
    'items_total' => 30000, 'delivery_fee' => 10000, 'total' => 40000,
    'address_text' => 'Podyezd 2', 'latitude' => 41.3, 'longitude' => 69.2,
    'phone' => '+998900000001', 'comment' => null,
]);
$tg->reset();
press('acpick:' . $courierOrder, false, ADMIN);
check(str_contains(json_encode($tg->calls), 'asgn:' . $courierOrder . ':' . $courierId), 'kuryerlar ro\'yxati tayinlash tugmasi bilan');
press('asgn:' . $courierOrder . ':' . $courierId, false, ADMIN);
check((int) Order::find($courierOrder)['courier_id'] === $courierId, 'buyurtma kuryerga tayinlandi');
check(count(sentTo($tg, COURIER)) === 1, 'kuryerga tayinlash haqida xabar yuborildi');

$tg->reset();
press('cord:' . $courierOrder, false, COURIER);
check(str_contains($tg->lastText(), '#' . $courierOrder), 'kuryer o\'z buyurtmasini ochdi');
check(str_contains(json_encode($tg->calls), 'cgo:' . $courierOrder) === false, 'accepted holatida «yo\'lga chiqdim» yo\'q');

$tg->reset();
press('cord:' . $courierOrder, false, OTHER_COURIER);
check(alertShown($tg), 'boshqa kuryer tayinlanmagan buyurtmani ocha olmaydi');

// Holatni admin «tayyor»gacha olib boradi
OrderService::transition($courierOrder, 'preparing', 'cli');
OrderService::transition($courierOrder, 'ready', 'cli');
$tg->reset();
press('cgo:' . $courierOrder, false, OTHER_COURIER);
check(alertShown($tg) && Order::find($courierOrder)['status'] === 'ready', 'boshqa kuryer amalni bajara olmaydi');

press('cgo:' . $courierOrder, false, COURIER);
check(Order::find($courierOrder)['status'] === 'on_the_way', 'kuryer «yo\'lga chiqdim» bosdi');
press('cdone:' . $courierOrder, false, COURIER);
$delivered = Order::find($courierOrder);
check($delivered['status'] === 'delivered' && $delivered['payment_status'] === 'unpaid', 'kuryer yetkazdi, naqd hali unpaid');
$tg->reset();
press('cord:' . $courierOrder, false, COURIER);
check(str_contains(json_encode($tg->calls), 'ccash:' . $courierOrder), 'yetkazilgach «naqd oldim» tugmasi chiqadi');
press('ccash:' . $courierOrder, false, COURIER);
check(Order::find($courierOrder)['payment_status'] === 'paid', 'naqd oldim → paid');

$tg->reset();
press('acpick:' . $courierOrder, false, ADMIN);
check(alertShown($tg), 'yetkazilgan buyurtmaga kuryer tayinlab bo\'lmaydi');

// Kuryerni nofaol qilish va qayta faollashtirish
press('actog:' . $courierId, false, ADMIN);
check(!\App\Services\CourierService::isActive(COURIER), 'kuryer nofaol qilindi');
$tg->reset();
send(['text' => I18n::t('uz', 'btn.courier')], COURIER);
check(str_contains($tg->lastText(), 'ruxsat etilmagan'), 'nofaol kuryer menyuga kira olmaydi');
press('actog:' . $courierId, false, ADMIN);
check(\App\Services\CourierService::isActive(COURIER), 'kuryer qayta faollashtirildi');

$tg->reset();
press('cmenu', false, USER_ID);
check(alertShown($tg), 'oddiy mijoz kuryer menyusini ocha olmaydi');

// ---------------------------------------------------------------- natija
echo "\nNatija: {$passed} o'tdi, {$failed} xato\n";
exit($failed === 0 ? 0 : 1);
