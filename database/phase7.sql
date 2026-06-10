-- =====================================================================
-- NESTORA.my - Phase 7 migration
-- Purchase Orders (procurement): order stock from suppliers, receive it
-- into inventory, track supplier cost & payment. Idempotent.
--   CLI:     php install.php
--   Manual:  import this file in phpMyAdmin
-- =====================================================================

SET NAMES utf8mb4;

-- --- purchase_orders -------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_orders (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_number      VARCHAR(40)  NOT NULL,
    supplier_id    INT UNSIGNED NULL,
    status         ENUM('draft','ordered','partial','received','cancelled') NOT NULL DEFAULT 'draft',
    order_date     DATE NULL,
    expected_date  DATE NULL,
    received_date  DATE NULL,
    subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    notes          TEXT NULL,
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_po_number (po_number),
    KEY idx_po_supplier (supplier_id),
    KEY idx_po_status (status),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_po_admin    FOREIGN KEY (created_by)  REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- purchase_order_items --------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_id             INT UNSIGNED NOT NULL,
    product_id        INT UNSIGNED NULL,
    product_name      VARCHAR(190) NOT NULL,
    sku               VARCHAR(80)  NULL,
    quantity_ordered  INT NOT NULL DEFAULT 0,
    quantity_received INT NOT NULL DEFAULT 0,
    unit_cost         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_poi_po (po_id),
    CONSTRAINT fk_poi_po      FOREIGN KEY (po_id)      REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
