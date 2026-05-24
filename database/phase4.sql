-- =====================================================================
-- NESTORA.my - Phase 4 migration
-- HitPay online payment gateway (payment method + return reference).
-- Safe to re-run on an existing database (idempotent).
--   CLI:     php install.php
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- orders: allow 'hitpay' as a payment method (re-applying is harmless) --
ALTER TABLE orders
  MODIFY COLUMN payment_method
  ENUM('bank_transfer','fpx','installment','cash_deposit','hitpay')
  NOT NULL DEFAULT 'bank_transfer';

-- orders: which gateway processed it + the gateway payment-request id ---
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='payment_gateway');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN payment_gateway VARCHAR(20) NULL AFTER payment_method','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='payment_ref');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN payment_ref VARCHAR(80) NULL AFTER payment_gateway','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- HitPay configuration defaults (admin overrides in Settings) ----------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('hitpay_enabled',  '0'),
('hitpay_mode',     'sandbox'),
('hitpay_currency', 'MYR'),
('hitpay_api_key',  ''),
('hitpay_salt',     '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
