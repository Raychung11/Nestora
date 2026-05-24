-- =====================================================================
-- NESTORA.my - Phase 3 migration
-- Bundle packages, product pricing (base/selling/cost), invoice & receipt.
-- Safe to re-run on an existing database (idempotent).
--   CLI:     php install.php
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- products: base price (RRP/"worth") + internal cost price ------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='base_price');
SET @s := IF(@c=0,'ALTER TABLE products ADD COLUMN base_price DECIMAL(10,2) NULL AFTER price','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='cost_price');
SET @s := IF(@c=0,'ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) NULL AFTER supplier_cost','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- orders: invoice + receipt document numbers -------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='invoice_number');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN invoice_number VARCHAR(40) NULL AFTER admin_notes','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='invoice_issued_at');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN invoice_issued_at DATETIME NULL AFTER invoice_number','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='receipt_number');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN receipt_number VARCHAR(40) NULL AFTER invoice_issued_at','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='receipt_issued_at');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN receipt_issued_at DATETIME NULL AFTER receipt_number','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Bundle composition. A bundle is a product (product_type='bundle');
-- this table lists the component products and their quantities.
CREATE TABLE IF NOT EXISTS bundle_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bundle_id  INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity   INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bundle (bundle_id),
    KEY idx_bundle_product (product_id),
    CONSTRAINT fk_bundle_parent FOREIGN KEY (bundle_id)  REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_bundle_child  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document numbering prefixes ----------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('invoice_prefix', 'INV-'),
('receipt_prefix', 'RCP-')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
