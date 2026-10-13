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
3. Sozlamalarni o'zingizniki bilan almashtiring (phpMyAdmin → SQL):

```sql
UPDATE settings SET value = '8600 1234 5678 9012' WHERE name = 'card_number';
UPDATE settings SET value = 'ALIYEV ALI'          WHERE name = 'card_owner';
UPDATE settings SET value = '+998 90 123 45 67'   WHERE name = 'restaurant_phone';
UPDATE settings SET value = '10:00-23:00'         WHERE name = 'work_hours';     -- 10:00-02:00 ham bo'ladi
UPDATE settings SET value = '10000'               WHERE name = 'delivery_fee';
UPDATE settings SET value = '150000'              WHERE name = 'free_delivery_from'; -- 0 = bepul yetkazish yo'q
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
| `ADMIN_CHAT_ID` | Yangi buyurtma va cheklar yuboriladigan chat ID |

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

Admin bot keyingi siklda qo'shiladi. Hozircha holatni o'zgartirish uchun:

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

- **Admin bot:** `public/admin.php` faylini `customer.php` nusxasi sifatida yarating. U `ADMIN_BOT_TOKEN` va `ADMIN_WEBHOOK_SECRET` ishlatadi, `Webhook::markProcessed('admin', …)` chaqiradi va `app/Bots/Admin/` dagi bot bilan ishlaydi. Holatni o'zgartirish uchun `OrderService::transition($id, $to, 'admin:<tg_id>')` chaqiriladi. Mijozni xabardor qilish uchun `new CustomerNotifier(new Telegram(CUSTOMER_BOT_TOKEN))` ro'yxatdan o'tkaziladi.
- **Online to'lov (Click/Payme):** `PaymentService` ga yangi usul qo'shiladi (`METHODS`, `statusAfterCreate`, `isTransitionAllowed`, `paymentStatusOnTransition`). `orders.payment_method` ENUM'i ham kengaytiriladi.
- **Veb-panel va Mini App:** `Services` va `Models` qatlamlaridan foydalanadi.
