# Lion Lavash — Telegram orqali yetkazib berish

Lion Lavash burgerxonasining mijoz boti. Sof PHP 8.3, MySQL/MariaDB, Composer va tashqi kutubxonalarsiz.
Hozir 1-sikl tayyor: yadro va mijoz boti. Talablar va qabul qilingan qarorlar [docs/spec.md](docs/spec.md) da.

## Talablar

- PHP 8.3, kengaytmalar: `pdo_mysql`, `curl`, `mbstring`, `json`
- MySQL 5.7+ / 8.x yoki MariaDB 10.3+
- HTTPS domen (Telegram webhook faqat HTTPS bilan ishlaydi)

## O'rnatish (ISPmanager, shared hosting)

### 1. Fayllarni yuklash

Loyihani to'liqligicha yuklang, masalan `~/lionlavash/`. Saytning **docroot** papkasini
`~/lionlavash/public` qilib belgilang (ISPmanager → Saytlar → sayt → «Корневая директория» / «Root directory»).

> Docroot'ni o'zgartirib bo'lmasa va u loyiha ildiziga qarasa: ildizdagi `.htaccess` hamma narsani
> yopadi, faqat `public/` ochiq qoladi. Bu holda `APP_URL` oxiriga `/public` qo'shing.

`storage/logs/` papkasiga PHP yoza olishi kerak (odatda `755` huquqi yetarli).

### 2. Bazani yaratish

1. ISPmanager → «Ma'lumotlar bazalari» bo'limida `utf8mb4` kodirovkali yangi baza va foydalanuvchi yarating.
2. phpMyAdmin → baza → «Import»: avval `database/schema.sql`, keyin `database/seed.sql`.
   Baza avvaldan bor bo'lsa, quyidagi migratsiyalarni **tartib bilan, bir martadan** import qiling:
   `database/migrations/002_admin_delivery.sql`, keyin `database/migrations/003_couriers.sql`.
   (Yangi o'rnatishda `schema.sql` va `seed.sql` ularni o'zi o'z ichiga oladi.)
3. Sozlamalarni o'zingizniki bilan almashtiring (phpMyAdmin → SQL):

```sql
UPDATE settings SET value = '8600 1234 5678 9012' WHERE name = 'card_number';
UPDATE settings SET value = 'ALIYEV ALI'          WHERE name = 'card_owner';
UPDATE settings SET value = '+998 90 123 45 67'   WHERE name = 'restaurant_phone';
UPDATE settings SET value = '10:00-23:00'         WHERE name = 'work_hours';     -- 10:00-02:00 ham bo'ladi
UPDATE settings SET value = '10000'               WHERE name = 'delivery_fee';
UPDATE settings SET value = '150000'              WHERE name = 'free_delivery_from'; -- 0 = bepul yetkazish yo'q
UPDATE settings SET value = '41.311081'           WHERE name = 'restaurant_lat';  -- oshxona lokatsiyasi (Google Maps'dan)
UPDATE settings SET value = '69.240562'           WHERE name = 'restaurant_lng';
UPDATE settings SET value = '5'                   WHERE name = 'delivery_radius_km'; -- 3 dan 5 gacha
UPDATE settings SET value = 'lion_admin'          WHERE name = 'admin_username';  -- @siz, «Aloqa» bo'limida ko'rinadi
```

> **Diqqat:** `schema.sql` mavjud jadvallarni o'chirib, qaytadan yaratadi. Uni faqat yangi bazada ishga tushiring.

### 3. `.env` ni to'ldirish

`.env.example` dan nusxa olib, `.env` nomi bilan loyiha ildiziga saqlang. Bu fayl `public/` ichiga emas, ildizga qo'yiladi.

| Kalit | Qiymat |
|---|---|
| `APP_DEBUG` | Production'da `false` |
| `APP_URL` | `https://sizning-domen.uz` (public/ ga qaraydigan manzil) |
| `DB_*` | 2-qadamda yaratilgan baza ma'lumotlari |
| `CUSTOMER_BOT_TOKEN` | @BotFather bergan token |
| `CUSTOMER_WEBHOOK_SECRET` | Tasodifiy satr, 32+ belgi, faqat `A-Z a-z 0-9 _ -` |
| `ADMIN_TG_ID` | Admin Telegram ID (faqat shu akkaunt admin paneliga kiradi) |
| `ADMIN_CHAT_ID` | Yangi buyurtma va cheklar yuboriladigan chat ID |

Admin uchun `.env` ga Telegram ID kiriting (`ADMIN_TG_ID`). Shu akkaunt botda «🛠 Admin» tugmasini ko'radi.
Admin ID ni admin paneli → Sozlamalar → «Adminni almashtirish» orqali keyinroq o'zgartirish mumkin (u `settings` jadvaliga yoziladi, `.env` zaxira bo'lib qoladi).

`ADMIN_CHAT_ID` ni bilish uchun: admin mijoz botida `/start` bosadi (bot boshlamagan odamga xabar yoza olmaydi),
keyin @userinfobot orqali o'z ID sini oladi. Guruh ishlatilsa, mijoz botini guruhga qo'shing; guruh ID si `-100…` bilan boshlanadi.

### 4. Webhook'ni o'rnatish

**A usul — o'z kompyuteringizdan.** `bin/set-webhook.php` bazaga ulanmaydi, shuning uchun loyihaning lokal
nusxasi va to'ldirilgan `.env` yetarli:

```bash
php bin/set-webhook.php
```

Tekshirish uchun `php bin/set-webhook.php --info`, o'chirish uchun `php bin/set-webhook.php --delete`.

**B usul — brauzer orqali.** Quyidagi manzilni oching. `<...>` qismlarini `.env` dagi qiymatlar bilan almashtiring:

```
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<DOMEN>/customer.php&secret_token=<SECRET>&allowed_updates=["message","callback_query"]
```

**C usul — ISPmanager cron.** Bir martalik vazifa yarating: `php ~/lionlavash/bin/set-webhook.php`.

### 5. Tekshirish

- Botga `/start` yozing: til → telefon → menyu.
- `https://<DOMEN>/customer.php` ni brauzerda ochsangiz, `405` qaytishi kerak.
- `https://<DOMEN>/.env` va (docroot ildiz bo'lsa) `/app/`, `/storage/` manzillari `403` yoki `404` qaytarishi kerak.
- Xatolar `storage/logs/app-YYYY-MM-DD.log` faylida.

## Buyurtma holatini sinov uchun o'zgartirish

Buyurtmalarni admin paneldan (botda «🛠 Admin» → «📋 Ochiq buyurtmalar») boshqarish mumkin. Lokal sinov uchun skript ham bor:

```bash
php bin/set-order-status.php 12 accepted
```

Mijoz botda xabar oladi. Noto'g'ri o'tish (masalan, `delivered → new`) rad etiladi.
Holatlar va ruxsat etilgan o'tishlar `docs/spec.md` da. Karta to'lovini rad etish uchun `payment_review` holatidagi buyurtmani `awaiting_payment` ga o'tkazing.

Serverda SSH yo'qligi sababli bu skript lokal sinov uchun mo'ljallangan. Serverda uni ISPmanager'dagi bir martalik cron orqali ishga tushirish mumkin.

## Lokal sinovlar

`tests/run.php` integratsion sinovlarni ishga tushiradi. U haqiqiy MySQL va soxta Telegram mijozi bilan ishlaydi.
**Sinov bazasi har safar qaytadan yaratiladi**, uning nomida `test` so'zi bo'lishi shart:

```bash
TEST_DB_HOST=127.0.0.1 TEST_DB_PORT=3306 TEST_DB_NAME=lionlavash_test TEST_DB_USER=root php tests/run.php
```

Sinovlar quyidagilarni qamrab oladi: yetkazish narxi, ish vaqti, ro'yxatdan o'tish, menyu va savat, naqd va karta oqimlari,
chekni rad etish va qayta yuborish, holat o'tishlari, takroriy `update_id` va «Tasdiqlash» tugmasining ikki marta bosilishi.

## Tuzilma

```
public/          docroot: faqat webhook kirish nuqtalari
app/Core/        Autoloader, Env, Db, Telegram, Router, State, I18n, Logger, Webhook
app/Models/      SQL faqat shu yerda (prepared statements)
app/Services/    biznes qoidalar: CartService, OrderService, PaymentService, DeliveryFee, WorkHours
app/Bots/        Telegram bilan muloqot (Customer/; Admin/ va Courier/ keyingi sikllarda)
lang/            uz.php, ru.php
database/        schema.sql, seed.sql
bin/             CLI skriptlar
tests/           integratsion sinovlar
storage/logs/    loglar (docroot'dan tashqarida)
```

## Keyingi sikllar uchun

- **Admin paneli** bitta bot ichida (`app/Bots/Admin/`), faqat admin ID si uchun ochiladi. Veb-panel keyinroq qo'shiladi.
- **Kuryer menyusi** ham shu bot ichida (`app/Bots/Courier/`). Alohida kuryer boti kerak emas.

## Kuryerlar

1. Kuryer botga `/start` bosadi (ro'yxatdan o'tadi).
2. Admin botda «🛠 Admin» → «🚚 Kuryerlar» → «➕ Kuryer qo'shish» tanlaydi va kuryerning xabarini **forward** qiladi
   (yoki Telegram ID sini yozadi). Kuryerning maxfiylik sozlamasi forward'ni yopib qo'ysa, ID ni qo'lda yozish kerak.
3. Admin ismni tekshirib «✅ Tasdiqlash» bosadi. Kuryer faol bo'ladi, unda «🚚 Kuryer» tugmasi paydo bo'ladi.
4. Admin buyurtma kartochkasida «🚚 Kuryer tayinlash» bosib kuryerni tanlaydi. Kuryerga buyurtma xabari boradi.
5. Kuryer «🚚 Kuryer» → buyurtmani ochadi: «🚚 Yo'lga chiqdim» → «✅ Yetkazdim» → «💵 Naqd oldim» (naqd to'lov uchun).

Kuryer faqat o'ziga tayinlangan buyurtmani o'zgartira oladi. Kuryerni o'chirib bo'lmaydi, faqat nofaol qilinadi (✅/⛔ tugmasi).
- **Online to'lov (Click/Payme):** `PaymentService` ga yangi usul qo'shiladi (`METHODS`, `statusAfterCreate`, `isTransitionAllowed`, `paymentStatusOnTransition`). `orders.payment_method` ENUM'i ham kengaytiriladi.
- **Veb-panel va Mini App:** `Services` va `Models` qatlamlaridan foydalanadi.
