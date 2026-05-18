-- =====================================================================
-- NESTORA.my - Database Schema (Phase 1 MVP)
-- Engine: MySQL / InnoDB / utf8mb4
-- Notes:
--   * All important tables include created_at / updated_at
--   * Supplier cost is stored but NEVER exposed publicly (admin only)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- 1. admin_users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120)  NOT NULL,
    email         VARCHAR(190)  NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('admin','sales_admin','supplier_admin') NOT NULL DEFAULT 'admin',
    status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME      NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. customers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150)  NOT NULL,
    phone         VARCHAR(40)   NOT NULL,
    email         VARCHAR(190)  NULL,
    password_hash VARCHAR(255)  NULL,
    address       TEXT          NULL,
    city          VARCHAR(120)  NULL,
    state         VARCHAR(120)  NULL,
    postcode      VARCHAR(20)   NULL,
    notes         TEXT          NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_phone (phone),
    KEY idx_customer_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120)  NOT NULL,
    slug        VARCHAR(140)  NOT NULL,
    type        ENUM('furniture','essential_oil','diffuser','bundle','general') NOT NULL DEFAULT 'general',
    description TEXT          NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    status      ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_category_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. suppliers  (created before products for FK reference)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name       VARCHAR(180)  NOT NULL,
    contact_person     VARCHAR(150)  NULL,
    phone              VARCHAR(40)   NULL,
    email              VARCHAR(190)  NULL,
    product_categories VARCHAR(255)  NULL,
    address            TEXT          NULL,
    payment_terms      VARCHAR(180)  NULL,
    notes              TEXT          NULL,
    status             ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. products
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                 VARCHAR(190)  NOT NULL,
    slug                 VARCHAR(210)  NOT NULL,
    sku                  VARCHAR(80)   NOT NULL,
    category_id          INT UNSIGNED  NULL,
    product_type         ENUM('furniture','essential_oil','diffuser','bundle') NOT NULL DEFAULT 'furniture',
    short_description    VARCHAR(500)  NULL,
    long_description     TEXT          NULL,
    feeling_tags         VARCHAR(255)  NULL,            -- e.g. "calm,cozy,warm"
    scent_profile        VARCHAR(255)  NULL,            -- essential oil top/heart/base notes
    scent_mood           VARCHAR(180)  NULL,
    scent_notes          VARCHAR(255)  NULL,
    best_room_usage      VARCHAR(180)  NULL,
    usage_instructions   TEXT          NULL,
    safety_disclaimer    TEXT          NULL,
    bottle_size          VARCHAR(80)   NULL,            -- essential oil
    material             VARCHAR(255)  NULL,            -- furniture
    dimensions           VARCHAR(255)  NULL,            -- furniture
    delivery_note        VARCHAR(255)  NULL,
    price                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    promo_price          DECIMAL(10,2) NULL,
    installment_eligible TINYINT(1)    NOT NULL DEFAULT 0,
    max_installment_months ENUM('6','12','24') NOT NULL DEFAULT '24',
    supplier_cost        DECIMAL(10,2) NULL,            -- ADMIN ONLY - never public
    supplier_id          INT UNSIGNED  NULL,
    stock_status         ENUM('available','preorder','checking','unavailable') NOT NULL DEFAULT 'available',
    is_featured          TINYINT(1)    NOT NULL DEFAULT 0,
    status               ENUM('draft','active','hidden') NOT NULL DEFAULT 'draft',
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_slug (slug),
    UNIQUE KEY uq_product_sku (sku),
    KEY idx_product_type (product_type),
    KEY idx_product_status (status),
    KEY idx_product_category (category_id),
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_product_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. product_images
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED  NOT NULL,
    file_path   VARCHAR(255)  NOT NULL,
    alt_text    VARCHAR(190)  NULL,
    is_primary  TINYINT(1)    NOT NULL DEFAULT 0,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_image_product (product_id),
    CONSTRAINT fk_image_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number       VARCHAR(40)   NOT NULL,
    customer_id        INT UNSIGNED  NULL,
    customer_name      VARCHAR(150)  NOT NULL,
    phone              VARCHAR(40)   NOT NULL,
    email              VARCHAR(190)  NULL,
    address            TEXT          NULL,
    total_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method     ENUM('bank_transfer','fpx','installment','cash_deposit') NOT NULL DEFAULT 'bank_transfer',
    installment_months ENUM('0','6','12','24') NOT NULL DEFAULT '0',
    order_status       ENUM('new','pending_confirmation','payment_pending','paid','supplier_checking','supplier_confirmed','preparing_delivery','delivered','completed','cancelled','refunded') NOT NULL DEFAULT 'new',
    payment_status     ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
    supplier_status    ENUM('not_started','checking','confirmed','unavailable') NOT NULL DEFAULT 'not_started',
    delivery_status    ENUM('not_started','preparing','shipped','delivered') NOT NULL DEFAULT 'not_started',
    admin_notes        TEXT          NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_number (order_number),
    KEY idx_order_status (order_status),
    KEY idx_order_payment (payment_status),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. order_items
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED  NOT NULL,
    product_id   INT UNSIGNED  NULL,
    product_name VARCHAR(190)  NOT NULL,
    sku          VARCHAR(80)   NULL,
    unit_price   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity     INT           NOT NULL DEFAULT 1,
    line_total   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_item_order (order_id),
    CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. comfort_quiz_leads
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comfort_quiz_leads (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(150)  NULL,
    phone             VARCHAR(40)   NULL,
    email             VARCHAR(190)  NULL,
    home_feeling      VARCHAR(60)   NULL,   -- Q1
    room              VARCHAR(60)   NULL,   -- Q2
    main_concern      VARCHAR(60)   NULL,   -- Q3
    preference        VARCHAR(60)   NULL,   -- Q4
    budget_range      VARCHAR(60)   NULL,   -- Q5
    installment_pref  VARCHAR(10)   NULL,   -- Q6
    recommendation    TEXT          NULL,
    source            VARCHAR(40)   NOT NULL DEFAULT 'comfort_quiz',
    status            ENUM('new','contacted','converted','closed') NOT NULL DEFAULT 'new',
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lead_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. installment_requests
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS installment_requests (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED  NULL,
    customer_name   VARCHAR(150)  NOT NULL,
    phone           VARCHAR(40)   NOT NULL,
    email           VARCHAR(190)  NULL,
    product_summary VARCHAR(255)  NULL,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    months          ENUM('6','12','24') NOT NULL DEFAULT '24',
    monthly_payment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM('new','reviewing','approved','rejected') NOT NULL DEFAULT 'new',
    admin_notes     TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inst_status (status),
    CONSTRAINT fk_inst_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. homepage_banners
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS homepage_banners (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(190)  NULL,
    subtitle    VARCHAR(255)  NULL,
    image_path  VARCHAR(255)  NULL,
    link_url    VARCHAR(255)  NULL,
    cta_label   VARCHAR(80)   NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    status      ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. testimonials
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS testimonials (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_name VARCHAR(150)  NOT NULL,
    location      VARCHAR(120)  NULL,
    message       TEXT          NOT NULL,
    rating        TINYINT       NOT NULL DEFAULT 5,
    photo_path    VARCHAR(255)  NULL,
    sort_order    INT           NOT NULL DEFAULT 0,
    status        ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. site_settings  (key/value store for homepage content, contact, etc.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key  VARCHAR(120)  NOT NULL,
    setting_value TEXT         NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- WhatsApp / AI lead capture (future-ready, Phase 2/3)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsapp_leads (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150)  NULL,
    phone       VARCHAR(40)   NULL,
    delivery_area VARCHAR(150) NULL,
    interest    VARCHAR(190)  NULL,
    message     TEXT          NULL,
    source      VARCHAR(40)   NOT NULL DEFAULT 'whatsapp',
    status      ENUM('new','contacted','converted','closed') NOT NULL DEFAULT 'new',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wa_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
