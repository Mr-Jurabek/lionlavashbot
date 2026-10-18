# Lion Lavash — 1-sikl spetsifikatsiyasi (tuzatilgan)

Asl topshiriq (`claude-code-prompt.md`) asosida. Quyida faqat asl matndan **farq qiladigan** yoki
unda **aniqlanmagan** joylar keltirilgan. Qolgan talablar o'zgarishsiz amal qiladi.

## 1. Topilgan xato va kamchiliklar → qabul qilingan qaror

| # | Muammo | Qaror |
|---|--------|-------|
| 1 | `processed_updates.update_id` ni `UNIQUE` qilish faqat bitta bot uchun to'g'ri. Har botning `update_id` hisobi alohida, shuning uchun admin/kuryer botlari qo'shilganda ular to'qnashadi. | `UNIQUE (bot, update_id)`. Yadro keyingi sikllarda o'zgarmaydi. |
| 2 | `update_id` idempotentligi «Tasdiqlash» tugmasining **ikki marta bosilishidan** himoya qilmaydi, chunki har bosishning `update_id` si boshqa. | `OrderService::createFromCart()` foydalanuvchi qatorini `SELECT … FOR UPDATE` bilan qulflaydi va savatni shu tranzaksiyada tozalaydi. Ikkinchi so'rov bo'sh savatga duch keladi va buyurtma yaratilmaydi. |
| 3 | `settings` jadvalidagi `key` ustuni MySQL'da zahiralangan so'z. | Ustunlar `name`, `value` deb nomlandi. |
| 4 | Vaqt zonasi ko'rsatilmagan. Shared hosting serveri boshqa zonada bo'lishi mumkin, natijada ish vaqti noto'g'ri tekshiriladi. | `.env` → `APP_TIMEZONE=Asia/Tashkent`. MySQL sessiyasi ham shu siljishga o'rnatiladi. |
| 5 | `work_hours` formati aniqlanmagan, tungi ish vaqti (masalan, 10:00–02:00) ham hisobga olinmagan. | Format `HH:MM-HH:MM`. Agar `end < start` bo'lsa, oraliq yarim tundan o'tadi. `start == end` bo'lsa, 24 soat ochiq. |
| 6 | Bepul yetkazish chegarasi bir xil emas: bir joyda «oshsa», boshqa joyda «ko'p bo'lsa» deyilgan. Summa chegaraga **teng** bo'lgan holat noaniq. | `items_total >= free_delivery_from` bo'lsa, yetkazish bepul. `free_delivery_from = 0` bo'lsa, bu imkoniyat o'chiq. |
| 7 | Naqd buyurtma `new` holatida qoladimi yoki darhol `accepted` ga o'tadimi, aniq emas. | `new` holatida qoladi. `new → accepted` o'tishini operator qiladi (hozircha `bin/set-order-status.php`). Karta buyurtmasi esa yaratilishi bilan avtomatik `awaiting_payment` ga o'tadi. |
| 8 | `payment_status` qiymatlari aniqlanmagan. | `unpaid` → (chek yuborildi) `pending_review` → `paid` yoki `rejected`. Naqd buyurtma `delivered` bo'lganda `paid`. Bekor qilinganda qiymat o'zgarmaydi (qaytarish qo'lda). |
| 9 | O'tish qoidalari to'lov usuliga bog'liq, lekin jadvalda bu ko'rsatilmagan. | Naqd: `awaiting_payment`/`payment_review` holatlariga o'tish taqiqlangan. Karta: `new → accepted` taqiqlangan. |
| 10 | Chek **rad etilsa**, mijoz uni qayta qanday yuborishi aytilmagan. | Rad etish xabarida va «Buyurtmalarim» bo'limida «📎 Chek yuborish» tugmasi chiqadi. |
| 11 | Admin chekni **ko'rmasa**, karta orqali to'lovni tasdiqlab bo'lmaydi. | Chek yuborilganda u `ADMIN_CHAT_ID` ga rasm yoki hujjat ko'rinishida jo'natiladi. |
| 12 | `ADMIN_CHAT_ID` ga xabar **mijoz boti** orqali ketadi. | Admin mijoz botida `/start` bosishi kerak, yoki guruh bo'lsa bot guruhga qo'shilishi kerak (README'da yozilgan). |
| 13 | SSH yo'q bo'lsa, `bin/*.php` CLI skriptlarini serverda ishga tushirib bo'lmaydi. | `set-webhook.php` bazaga ulanmaydi, uni o'z kompyuteringizdan ishga tushirish mumkin. Brauzer orqali o'rnatish usuli ham README'da bor. `set-order-status.php` lokal sinov uchun mo'ljallangan, serverda esa ISPmanager'ning bir martalik cron'i orqali ishga tushiriladi. |
| 14 | Docroot'ni `public/` ga o'zgartirib bo'lmaydigan hosting uchun aniq yo'l ko'rsatilmagan. | Loyiha ildizidagi `.htaccess` `public/` dan boshqa hamma narsani 403 bilan yopadi. |
| 15 | Telefon raqamini boshqa odamning kontakti bilan yuborish mumkin edi. | `contact.user_id == from.id` tekshiriladi. |
| 16 | Mahsulot rasmi bilan ro'yxat orasida o'tganda `editMessageText` rasmni matnga aylantira olmaydi. | Xabar turi bir xil bo'lsa, tahrirlanadi. Tur o'zgarsa, eski xabar o'chirilib, yangisi yuboriladi. |
| 17 | `photo_file_id` har bot uchun alohida beriladi, shuning uchun seed'ga yozib bo'lmaydi. | Seed'da `NULL`. Rasmsiz mahsulot matn ko'rinishida chiqadi. Rasmlar admin bot siklida qo'shiladi. |
| 18 | Narx turi aniqlanmagan. | So'm butun son (`INT UNSIGNED`), tiyinsiz. |
| 19 | Savat va buyurtma orasida narx o'zgarishi yoki mahsulotning nofaol bo'lishi hisobga olinmagan. | Summa tasdiqlash paytidagi joriy narxdan hisoblanadi. Nofaol mahsulotlar savatda ko'rinmaydi va buyurtmaga kirmaydi. |
| 20 | Kiritiladigan ma'lumotlarning uzunligi cheklanmagan. | Manzil 3–255 belgi, izoh ≤ 500 belgi, bitta mahsulot miqdori ≤ 50. |
| 21 | `processed_updates` jadvalini qachon tozalash aniqlanmagan. | Har 100 so'rovdan taxminan bittasida 3 kundan eski yozuvlar o'chiriladi, cron kerak emas. |
| 22 | Telegram 200 bo'lmagan javobda so'rovni qayta yuboradi. | Autentifikatsiyadan o'tgan so'rovga har doim 200 qaytariladi. Xato logga yoziladi, mijozga umumiy xabar ko'rsatiladi. |
| 23 | Asl matndagi «quriasan» — imlo xatosi (to'g'risi: «qurasan»). | — |

## 2. Holatlar jadvali (`OrderService::TRANSITIONS`)

```
new              → awaiting_payment (faqat karta), accepted (faqat naqd), cancelled
awaiting_payment → payment_review, cancelled
payment_review   → accepted, awaiting_payment (rad etish), cancelled
accepted         → preparing, cancelled
preparing        → ready, cancelled
ready            → on_the_way, cancelled
on_the_way       → delivered, cancelled
delivered, cancelled → (yakuniy)
```

`changed_by` maydoni `system`, `customer:<tg_id>`, `cli` qiymatlarini oladi. Keyingi siklda `admin:<tg_id>` va `courier:<tg_id>` qo'shiladi.

## 3. Mijoz botidagi holatlar (`users.state`)

Ro'yxatdan o'tish alohida holat bilan emas, `users.lang` va `users.phone` maydonlari bilan aniqlanadi: ulardan biri bo'sh bo'lsa, bot shu bosqichni so'raydi.

`main` → `checkout_address` → `checkout_comment` → `checkout_payment` → `checkout_confirm` → (karta) `await_receipt` → `main`.

Asosiy menyu tugmasi, «❌ Bekor qilish» yoki `/start` har qanday holatda bosilsa, rasmiylashtirish bekor qilinadi.

## 4. Admin va yetkazish (2-qaror, admin bot bitta bot ichida)

- **Admin alohida bot emas.** Admin paneli mijoz botida, faqat admin Telegram ID si uchun ochiladi («🛠 Admin» tugmasi). Har bir admin amali `AdminAccess::require()` orqali tekshiriladi.
- **Admin ID:** `.env` da `ADMIN_TG_ID`. Admin paneli → Sozlamalar → «Adminni almashtirish» orqali `settings.admin_tg_id` ga yoziladi (tasdiqlash bilan). Settings bo'sh bo'lsa, `.env` qiymati ishlatiladi.
- **Lokatsiya majburiy.** Mijoz lokatsiya yuboradi, u havo chizig'i bo'yicha (haversine) oshxonadan `delivery_radius_km` (3–5 km) ichida bo'lishi kerak. Radius tashqarisida buyurtma rad etiladi.
- **Matn manzil ixtiyoriy:** podyezd, qavat, xonadon. Izoh alohida bosqich.
- **Yordam:** mijoz «🆘 Yordam» orqali yozadi, xabar admin shaxsiy akkauntiga keladi. Admin «✉️ Javob» tugmasi bilan javob beradi.
- **Aloqa:** restoran telefoni, ish vaqti va `admin_username` (@ bilan havola).
- **Admin sozlamalari** (`settings`): yetkazish narxi, bepul chegara, radius, oshxona lokatsiyasi, karta raqami/egasi, telefon, ish vaqti, admin username. Har bir qiymat kiritilganda tekshiriladi.
- **Admin matnlari faqat o'zbek tilida** (mijoz matnlari uz va ru).

## 5. Kuryer (3-qaror, bitta bot ichida)

- **Kuryer alohida bot emas.** Faol kuryerga botda «🚚 Kuryer» tugmasi ko'rinadi. Jadval: `couriers` (telegram_id unique, is_active). Kuryer o'chirilmaydi, faqat nofaol qilinadi.
- **Qo'shish:** admin kuryerning xabarini forward qiladi yoki ID kiritadi. Kuryer avval `/start` bosgan bo'lishi kerak.
- **Biriktirish:** admin buyurtmaga kuryerni tanlaydi (`orders.courier_id`). Faqat yakunlanmagan buyurtmaga (accepted, preparing, ready, on_the_way).
- **Kuryer amallari:** ready → on_the_way («Yo'lga chiqdim»), on_the_way → delivered («Yetkazdim»), keyin naqd bo'lsa «Naqd oldim» → payment_status = paid.
- **Naqd to'lov:** yetkazilgach `unpaid` qoladi. Faqat kuryer «Naqd oldim» tasdiqlagandan keyin `paid` bo'ladi (`OrderService::markCashPaid`).
- **Ruxsat:** kuryer faqat o'ziga tayinlangan buyurtmani ko'radi va o'zgartiradi. Boshqa kuryer amalini rad etish `CourierService::act()` ichida tekshiriladi.
- **Kuryer matnlari faqat o'zbekcha.**

## 6. Boshqa qarorlar

- `order_items.name_snapshot` o'zbekcha nom bilan saqlanadi (admin xabarlari o'zbek tilida).
- Callback ma'lumotlari: `lang:uz`, `cats`, `cat:ID`, `prod:ID[:QTY]`, `add:ID:QTY`, `cart`, `ci:ID:inc|dec|del`, `cclear`, `checkout`, `rcpt:ORDER_ID`, `noop`. Mahsulot kartochkasidagi miqdor bazaga yozilmaydi, u `callback_data` ichida saqlanadi.
- Chek sifatida rasm yoki hujjat qabul qilinadi (JPEG, PNG, WEBP, HEIC, PDF).
- `OrderService::onTransition()` tinglovchilari tranzaksiya commit bo'lgandan keyin chaqiriladi. Keyingi sikllarda admin va kuryer botlari o'z tinglovchilarini shu yerga qo'shadi.
