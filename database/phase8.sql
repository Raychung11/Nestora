-- =====================================================================
-- NESTORA.my - Phase 8 migration
-- Partnership program: hybrid referral + reseller wholesale, with
-- application/approval flow and tiered rates (Starter / Elite).
-- Safe to re-run on an existing database (idempotent).
--   CLI:     php install.php
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- --- partner_applications  (public application inbox) ---------------
CREATE TABLE IF NOT EXISTS partner_applications (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(150)  NOT NULL,
    business_name   VARCHAR(180)  NULL,
    email           VARCHAR(190)  NOT NULL,
    phone           VARCHAR(40)   NOT NULL,
    message         TEXT          NULL,
    requested_tier  ENUM('starter','elite') NOT NULL DEFAULT 'starter',
    status          ENUM('new','approved','rejected') NOT NULL DEFAULT 'new',
    reviewed_by     INT UNSIGNED  NULL,
    reviewed_at     DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_app_status (status),
    KEY idx_app_email  (email),
    CONSTRAINT fk_app_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- partners ------------------------------------------------------
CREATE TABLE IF NOT EXISTS partners (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                     VARCHAR(150)  NOT NULL,
    business_name            VARCHAR(180)  NULL,
    email                    VARCHAR(190)  NOT NULL,
    phone                    VARCHAR(40)   NULL,
    address                  TEXT          NULL,
    password_hash            VARCHAR(255)  NOT NULL,
    referral_code            VARCHAR(40)   NOT NULL,
    tier                     ENUM('starter','elite') NOT NULL DEFAULT 'starter',
    commission_rate          DECIMAL(5,2) NOT NULL DEFAULT 10.00, -- % of referred order total
    wholesale_discount_rate  DECIMAL(5,2) NOT NULL DEFAULT 20.00, -- % off retail for own purchases
    status                   ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    application_id           INT UNSIGNED  NULL,
    last_login_at            DATETIME      NULL,
    created_at               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partner_email (email),
    UNIQUE KEY uq_partner_ref   (referral_code),
    KEY idx_partner_status (status),
    CONSTRAINT fk_partner_app FOREIGN KEY (application_id) REFERENCES partner_applications(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- partner_commissions  (one per referred order) ------------------
CREATE TABLE IF NOT EXISTS partner_commissions (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id        INT UNSIGNED NOT NULL,
    order_id          INT UNSIGNED NOT NULL,
    base_amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    rate              DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status            ENUM('pending','approved','paid','void') NOT NULL DEFAULT 'pending',
    notes             VARCHAR(255)  NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commission_order (order_id),
    KEY idx_commission_partner (partner_id),
    KEY idx_commission_status (status),
    CONSTRAINT fk_commission_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
    CONSTRAINT fk_commission_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- partner_payouts  (admin records cash paid to a partner) --------
CREATE TABLE IF NOT EXISTS partner_payouts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id  INT UNSIGNED NOT NULL,
    amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    method      VARCHAR(40)   NULL,
    reference   VARCHAR(120)  NULL,
    notes       TEXT          NULL,
    paid_at     DATE          NULL,
    recorded_by INT UNSIGNED  NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payout_partner (partner_id),
    CONSTRAINT fk_payout_partner FOREIGN KEY (partner_id)  REFERENCES partners(id)     ON DELETE CASCADE,
    CONSTRAINT fk_payout_admin   FOREIGN KEY (recorded_by) REFERENCES admin_users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- orders: referral / wholesale bookkeeping -----------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='partner_id');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN partner_id INT UNSIGNED NULL','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='referral_code');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN referral_code VARCHAR(40) NULL','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='is_wholesale');
SET @s := IF(@c=0,'ALTER TABLE orders ADD COLUMN is_wholesale TINYINT(1) NOT NULL DEFAULT 0','DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings --------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('partner_program_enabled',     '0'),
('partner_program_public_text', 'Grow with Nestora. Earn from every comfort home you inspire — Starter or Elite, your tier, your terms.'),
('partner_starter_commission',  '10'),
('partner_starter_wholesale',   '20'),
('partner_elite_commission',    '20'),
('partner_elite_wholesale',     '30')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
