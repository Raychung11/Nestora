-- =====================================================================
-- NESTORA.my - Phase 6 migration
-- Scent refill subscriptions (schedule-based recurring orders).
-- Safe to re-run on an existing database (idempotent).
--   CLI:     php install.php
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- --- subscriptions ---------------------------------------------------
CREATE TABLE IF NOT EXISTS subscriptions (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id       INT UNSIGNED NOT NULL,
    product_id        INT UNSIGNED NULL,         -- the refilled oil (SET NULL if removed)
    product_name      VARCHAR(190) NOT NULL,     -- snapshot
    sku               VARCHAR(80)  NULL,         -- snapshot
    quantity          INT NOT NULL DEFAULT 1,
    frequency         ENUM('monthly','bimonthly','quarterly') NOT NULL DEFAULT 'monthly',
    unit_price        DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- subscriber price per refill
    customer_name     VARCHAR(150) NOT NULL,
    phone             VARCHAR(40)  NULL,
    email             VARCHAR(190) NULL,
    address           TEXT         NULL,
    status            ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
    next_renewal_date DATE         NULL,
    last_order_id     INT UNSIGNED NULL,         -- no FK (avoids circular reference)
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sub_customer (customer_id),
    KEY idx_sub_status (status),
    KEY idx_sub_due (status, next_renewal_date),
    CONSTRAINT fk_sub_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_product  FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- orders: link an order back to the subscription that spawned it ---
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='subscription_id');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN subscription_id INT UNSIGNED NULL','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings --------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('subscriptions_enabled',         '0'),
('subscription_discount_percent', '10'),
('subscription_public_text',      'Never run out of your favourite scent. Subscribe and we will deliver a fresh refill on your schedule — pause or cancel anytime.'),
('cron_key',                      '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
