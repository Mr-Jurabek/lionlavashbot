-- 003: kuryerlar (bitta bot ichida). Mavjud bazaga bir marta ishga tushiring.
SET NAMES utf8mb4;

CREATE TABLE couriers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    telegram_id BIGINT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_couriers_telegram (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN courier_id INT UNSIGNED NULL AFTER payment_status,
    ADD KEY idx_orders_courier (courier_id),
    ADD CONSTRAINT fk_orders_courier FOREIGN KEY (courier_id) REFERENCES couriers (id) ON DELETE SET NULL;
