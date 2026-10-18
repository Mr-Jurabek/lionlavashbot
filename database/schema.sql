-- Lion Lavash — ma'lumotlar bazasi sxemasi (MySQL 5.7+/8, MariaDB 10.3+)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_status_log;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS couriers;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS processed_updates;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    telegram_id BIGINT NOT NULL,
    first_name  VARCHAR(255) NOT NULL DEFAULT '',
    phone       VARCHAR(20) NULL,
    lang        ENUM('uz', 'ru') NULL,
    state       VARCHAR(32) NULL,
    state_data  TEXT NULL COMMENT 'JSON',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_telegram (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_uz    VARCHAR(100) NOT NULL,
    name_ru    VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_categories_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id    INT UNSIGNED NOT NULL,
    name_uz        VARCHAR(150) NOT NULL,
    name_ru        VARCHAR(150) NOT NULL,
    description_uz TEXT NULL,
    description_ru TEXT NULL,
    price          INT UNSIGNED NOT NULL COMMENT 'so''m',
    photo_file_id  VARCHAR(255) NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    sort_order     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_products_category (category_id, is_active, sort_order),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   SMALLINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_user_product (user_id, product_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE couriers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    telegram_id BIGINT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_couriers_telegram (telegram_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    status          ENUM('new', 'awaiting_payment', 'payment_review', 'accepted', 'preparing',
                         'ready', 'on_the_way', 'delivered', 'cancelled') NOT NULL DEFAULT 'new',
    payment_method  ENUM('cash', 'card') NOT NULL,
    payment_status  ENUM('unpaid', 'pending_review', 'paid', 'rejected') NOT NULL DEFAULT 'unpaid',
    courier_id      INT UNSIGNED NULL,
    items_total     INT UNSIGNED NOT NULL,
    delivery_fee    INT UNSIGNED NOT NULL,
    total           INT UNSIGNED NOT NULL,
    address_text    VARCHAR(255) NULL,
    latitude        DECIMAL(10, 7) NULL,
    longitude       DECIMAL(10, 7) NULL,
    phone           VARCHAR(20) NOT NULL,
    comment         VARCHAR(500) NULL,
    receipt_file_id VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id, created_at),
    KEY idx_orders_status (status, created_at),
    KEY idx_orders_courier (courier_id),
    CONSTRAINT fk_orders_courier FOREIGN KEY (courier_id) REFERENCES couriers (id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NULL,
    name_snapshot  VARCHAR(150) NOT NULL,
    price_snapshot INT UNSIGNED NOT NULL,
    quantity       SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status   VARCHAR(20) NOT NULL,
    changed_by  VARCHAR(64) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_log_order (order_id),
    CONSTRAINT fk_status_log_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- update_id har bot uchun alohida hisoblanadi, shu sababli (bot, update_id) juftligi unikal
CREATE TABLE processed_updates (
    bot        VARCHAR(16) NOT NULL,
    update_id  BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bot, update_id),
    KEY idx_processed_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `key` MySQL'da zahiralangan so'z bo'lgani uchun ustun nomi `name`
CREATE TABLE settings (
    name  VARCHAR(64) NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
