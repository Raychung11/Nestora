-- =====================================================================
-- NESTORA.my - Phase 2 migration
-- Safe to run on an existing Phase 1 database (idempotent).
--   CLI:     php install.php   (re-running also applies this)
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- Manual payment upload ----------------------------------------------
CREATE TABLE IF NOT EXISTS payment_proofs (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED  NOT NULL,
    method       ENUM('bank_transfer','cash_deposit','fpx','other') NOT NULL DEFAULT 'bank_transfer',
    amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reference    VARCHAR(120)  NULL,
    file_path    VARCHAR(255)  NOT NULL,
    note         TEXT          NULL,
    status       ENUM('submitted','verified','rejected') NOT NULL DEFAULT 'submitted',
    reviewed_by  INT UNSIGNED  NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_proof_order (order_id),
    KEY idx_proof_status (status),
    CONSTRAINT fk_proof_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_proof_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bank / payment settings --------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('bank_name', 'Maybank'),
('bank_account_name', 'NESTORA ENTERPRISE'),
('bank_account_number', '5123 4567 8901'),
('payment_instructions', 'Transfer the exact order total to the account above, then upload your payment proof. Our Nestora team will verify and confirm your order personally.')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
