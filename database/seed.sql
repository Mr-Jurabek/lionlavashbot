-- Lion Lavash — boshlang'ich ma'lumotlar (schema.sql dan keyin ishga tushiriladi)
SET NAMES utf8mb4;

-- Karta raqami va egasi — o'rnatishdan keyin haqiqiy qiymatga almashtiring (README ga qarang)
INSERT INTO settings (name, value) VALUES
    ('delivery_fee', '10000'),
    ('free_delivery_from', '150000'),
    ('card_number', '8600 0000 0000 0000'),
    ('card_owner', 'ISM FAMILIYA'),
    ('restaurant_phone', '+998 90 000 00 00'),
    ('work_hours', '10:00-23:00'),
    ('restaurant_lat', ''),
    ('restaurant_lng', ''),
    ('delivery_radius_km', '5'),
    ('admin_username', ''),
    ('admin_tg_id', '');

INSERT INTO categories (id, name_uz, name_ru, sort_order) VALUES
    (1, 'Burgerlar', 'Бургеры', 1),
    (2, 'Lavash', 'Лаваш', 2),
    (3, 'Ichimliklar', 'Напитки', 3);

INSERT INTO products (category_id, name_uz, name_ru, description_uz, description_ru, price, sort_order) VALUES
    (1, 'Gamburger', 'Гамбургер', 'Mol go''shti kotleti, pomidor, salat, sous', 'Говяжья котлета, помидор, салат, соус', 28000, 1),
    (1, 'Chizburger', 'Чизбургер', 'Mol go''shti kotleti, cheddar pishlog''i, tuzlangan bodring', 'Говяжья котлета, сыр чеддер, маринованный огурец', 32000, 2),
    (1, 'Dabl burger', 'Дабл бургер', 'Ikki qavat kotlet, pishloq, piyoz', 'Двойная котлета, сыр, лук', 45000, 3),
    (2, 'Tovuqli lavash', 'Лаваш с курицей', 'Tovuq go''shti, kartoshka fri, sabzavotlar', 'Курица, картофель фри, овощи', 30000, 1),
    (2, 'Go''shtli lavash', 'Лаваш с говядиной', 'Mol go''shti, sabzavotlar, maxsus sous', 'Говядина, овощи, фирменный соус', 35000, 2),
    (2, 'Pishloqli lavash', 'Лаваш с сыром', 'Tovuq go''shti, ikki xil pishloq', 'Курица, два вида сыра', 38000, 3),
    (3, 'Coca-Cola 0.5 l', 'Coca-Cola 0,5 л', NULL, NULL, 9000, 1),
    (3, 'Choy', 'Чай', 'Qora yoki ko''k choy', 'Чёрный или зелёный', 5000, 2),
    (3, 'Suv 0.5 l', 'Вода 0,5 л', NULL, NULL, 4000, 3);
