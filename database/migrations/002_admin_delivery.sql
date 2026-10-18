-- 002: admin paneli, yetkazish radiusi, yordam va aloqa sozlamalari.
-- Mavjud bazaga bir marta ishga tushiring (yangi o'rnatishda seed.sql da bor).
SET NAMES utf8mb4;

INSERT INTO settings (name, value) VALUES
    ('restaurant_lat', ''),
    ('restaurant_lng', ''),
    ('delivery_radius_km', '5'),
    ('admin_username', ''),
    ('admin_tg_id', '')
ON DUPLICATE KEY UPDATE name = name;
