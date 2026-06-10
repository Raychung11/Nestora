-- =====================================================================
-- NESTORA.my - Phase 5 migration
-- Voucher codes, stock/inventory, order-status notifications, and
-- login rate-limiting (security). Safe to re-run (idempotent).
--   CLI:     php install.php
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- --- products: inventory tracking ------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='track_inventory');
SET @s := IF(@c=0,'ALTER TABLE products ADD COLUMN track_inventory TINYINT(1) NOT NULL DEFAULT 0 AFTER stock_status','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='stock_quantity');
SET @s := IF(@c=0,'ALTER TABLE products ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0 AFTER track_inventory','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='low_stock_threshold');
SET @s := IF(@c=0,'ALTER TABLE products ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 0 AFTER stock_quantity','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- orders: voucher + stock + notification bookkeeping --------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='subtotal_amount');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN subtotal_amount DECIMAL(10,2) NULL AFTER total_amount','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='discount_amount');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal_amount','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='voucher_code');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN voucher_code VARCHAR(40) NULL AFTER discount_amount','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='stock_decremented');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN stock_decremented TINYINT(1) NOT NULL DEFAULT 0 AFTER voucher_code','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='last_status_notified');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN last_status_notified VARCHAR(40) NULL AFTER stock_decremented','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- vouchers --------------------------------------------------------
CREATE TABLE IF NOT EXISTS vouchers (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(40)  NOT NULL,
    description VARCHAR(160) NULL,
    type        ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    value       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_spend   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_uses    INT NOT NULL DEFAULT 0,          -- 0 = unlimited
    used_count  INT NOT NULL DEFAULT 0,
    starts_at   DATE NULL,
    expires_at  DATE NULL,
    status      ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_voucher_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- login_attempts (admin & customer brute-force throttle) ----------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip           VARCHAR(45)  NOT NULL,
    scope        VARCHAR(20)  NOT NULL,   -- 'admin' | 'customer'
    attempts     INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ip_scope (ip, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
