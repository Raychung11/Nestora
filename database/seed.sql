-- =====================================================================
-- NESTORA.my - Seed Data (Phase 1 MVP demo content)
-- Default admin login:
--   email:    admin@nestora.my
--   password: NestoraAdmin123
-- !! Change this password immediately after first login. !!
-- =====================================================================

SET NAMES utf8mb4;

-- Admin user ----------------------------------------------------------
INSERT INTO admin_users (name, email, password_hash, role, status)
VALUES ('Nestora Admin', 'admin@nestora.my',
        '$2y$12$5GhXHpmtByx0wegYzNQ/g.VXYp3rkmMpfCWSTqKDeeiQfWrXdhn2y',
        'admin', 'active')
ON DUPLICATE KEY UPDATE email = email;

-- Categories ----------------------------------------------------------
INSERT INTO categories (name, slug, type, description, sort_order, status) VALUES
('Nestora Living', 'furniture', 'furniture', 'Calming, comfort-first furniture for a home that takes care of you.', 1, 'active'),
('Nestora Scent', 'essential-oil', 'essential_oil', 'Signature home scents crafted around emotional comfort and memory.', 2, 'active'),
('Comfort Bundles', 'bundles', 'bundle', 'Thoughtfully selected full home comfort sets.', 3, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Suppliers (admin only, never public) --------------------------------
INSERT INTO suppliers (company_name, contact_person, phone, email, product_categories, payment_terms, status) VALUES
('Comfort Living Furnishings', 'Lim W.', '+60123000001', 'partner1@example.com', 'furniture', '30 days', 'active'),
('Aroma House Supply', 'Tan S.', '+60123000002', 'partner2@example.com', 'essential_oil', 'COD', 'active')
ON DUPLICATE KEY UPDATE company_name = company_name;

-- Products: Furniture -------------------------------------------------
INSERT INTO products
(name, slug, sku, category_id, product_type, short_description, long_description,
 feeling_tags, material, dimensions, delivery_note, price, promo_price,
 installment_eligible, max_installment_months, supplier_cost, supplier_id,
 stock_status, is_featured, status)
VALUES
('Hygge Comfort Sofa', 'hygge-comfort-sofa', 'NL-SOFA-001',
 (SELECT id FROM categories WHERE slug='furniture'), 'furniture',
 'A sink-in, cloud-soft sofa designed to slow your evenings down.',
 'The Hygge Comfort Sofa is built for the moments that matter most — the unwinding, the closeness, the quiet. Deep cushions and a warm, earthy frame make your living room feel like an embrace.',
 'calm,cozy,warm', 'Solid oak frame, high-resilience foam, oat linen blend',
 'W 210cm x D 95cm x H 85cm',
 'Delivery timeline will be confirmed by our Nestora team after order confirmation.',
 2890.00, 2490.00, 1, '24', 1750.00,
 (SELECT id FROM suppliers WHERE company_name='Comfort Living Furnishings'),
 'available', 1, 'active'),

('Calm Bedroom Bedframe', 'calm-bedroom-bedframe', 'NL-BED-002',
 (SELECT id FROM categories WHERE slug='furniture'), 'furniture',
 'A grounding, low-profile bedframe for deeper, calmer sleep.',
 'Designed around rest. The Calm Bedroom Bedframe uses warm wood tones and a soft headboard to turn your bedroom into a sanctuary for restorative sleep.',
 'calm,premium', 'Engineered wood, soft-touch upholstered headboard',
 'Queen — W 165cm x L 215cm x H 100cm',
 'Delivery timeline will be confirmed by our Nestora team after order confirmation.',
 1980.00, NULL, 1, '24', 1180.00,
 (SELECT id FROM suppliers WHERE company_name='Comfort Living Furnishings'),
 'available', 1, 'active'),

('Warmth Study Desk', 'warmth-study-desk', 'NL-DESK-003',
 (SELECT id FROM categories WHERE slug='furniture'), 'furniture',
 'A focused yet warm workspace that feels like home, not an office.',
 'The Warmth Study Desk brings calm productivity home with rounded edges, a warm timber surface and a clutter-light design that keeps the mind clear.',
 'fresh,premium', 'Solid rubberwood, powder-coated steel legs',
 'W 120cm x D 60cm x H 75cm',
 'Delivery timeline will be confirmed by our Nestora team after order confirmation.',
 890.00, 790.00, 1, '12', 510.00,
 (SELECT id FROM suppliers WHERE company_name='Comfort Living Furnishings'),
 'available', 0, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Products: Essential Oils (Launch Collection) ------------------------
INSERT INTO products
(name, slug, sku, category_id, product_type, short_description, long_description,
 feeling_tags, scent_profile, scent_mood, scent_notes, best_room_usage,
 usage_instructions, safety_disclaimer, bottle_size, price, promo_price,
 installment_eligible, max_installment_months, supplier_cost, supplier_id,
 stock_status, is_featured, status)
VALUES
('Sunday Cotton', 'sunday-cotton', 'NS-OIL-001',
 (SELECT id FROM categories WHERE slug='essential-oil'), 'essential_oil',
 'Clean, soft, comforting — like fresh laundry on a slow Sunday.',
 'Sunday Cotton wraps your home in the feeling of a fresh, unhurried morning. A signature scent built for gentle comfort and a calm reset.',
 'fresh,calm', 'Top: cotton blossom / Heart: white musk / Base: soft amber',
 'Clean, soft, comforting', 'Cotton blossom, white musk, soft amber',
 'Bedroom, living room',
 'Add 4–6 drops to a diffuser with water, or a few drops on a cotton pad. Diffuse 30–60 minutes.',
 'For aromatic use only. Keep away from children and pets. Discontinue use if irritation occurs. Not for ingestion.',
 '30ml', 79.00, 69.00, 0, '6', 32.00,
 (SELECT id FROM suppliers WHERE company_name='Aroma House Supply'),
 'available', 1, 'active'),

('Evening Tea', 'evening-tea', 'NS-OIL-002',
 (SELECT id FROM categories WHERE slug='essential-oil'), 'essential_oil',
 'Calm, warm, relaxing — a quiet cup of tea for your home.',
 'Evening Tea is the scent of winding down. Warm, soothing and softly herbal, it helps your home exhale at the end of the day.',
 'calm,warm', 'Top: bergamot / Heart: black tea / Base: warm vanilla',
 'Calm, warm, relaxing', 'Bergamot, black tea, warm vanilla',
 'Bedroom, study room',
 'Add 4–6 drops to a diffuser with water. Best diffused in the evening for a calming wind-down.',
 'For aromatic use only. Keep away from children and pets. Discontinue use if irritation occurs. Not for ingestion.',
 '30ml', 79.00, NULL, 0, '6', 32.00,
 (SELECT id FROM suppliers WHERE company_name='Aroma House Supply'),
 'available', 1, 'active'),

('Warm Kitchen', 'warm-kitchen', 'NS-OIL-003',
 (SELECT id FROM categories WHERE slug='essential-oil'), 'essential_oil',
 'Cozy, familiar, warmth — the comfort of a home that is lived in.',
 'Warm Kitchen brings back the feeling of a home full of warmth and familiarity. Spiced, gentle and grounding.',
 'cozy,warm', 'Top: sweet orange / Heart: cinnamon / Base: vanilla & clove',
 'Cozy, familiar, warmth', 'Sweet orange, cinnamon, vanilla, clove',
 'Living room, whole home',
 'Add 4–6 drops to a diffuser with water. Great for gatherings and cozy evenings.',
 'For aromatic use only. Keep away from children and pets. Discontinue use if irritation occurs. Not for ingestion.',
 '30ml', 79.00, 69.00, 0, '6', 32.00,
 (SELECT id FROM suppliers WHERE company_name='Aroma House Supply'),
 'available', 1, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Testimonials --------------------------------------------------------
INSERT INTO testimonials (customer_name, location, message, rating, sort_order, status) VALUES
('Aishah R.', 'Kuala Lumpur', 'My living room finally feels calm. The sofa is unbelievably comfortable and the scent makes coming home a feeling, not just an arrival.', 5, 1, 'active'),
('Daniel T.', 'Penang', 'The installment plan made it easy to bring real comfort home. Nestora''s team kept me updated the whole way.', 5, 2, 'active'),
('Mei Ling', 'Johor Bahru', 'Evening Tea is now part of my nightly routine. I sleep better and my home feels warmer.', 5, 3, 'active')
ON DUPLICATE KEY UPDATE customer_name = customer_name;

-- Homepage banners ----------------------------------------------------
INSERT INTO homepage_banners (title, subtitle, link_url, cta_label, sort_order, status) VALUES
('More Than A Home. A Feeling.', 'Designed for emotional comfort. A home that takes care of you.', '/products.php', 'Shop Furniture', 1, 'active')
ON DUPLICATE KEY UPDATE title = title;

-- Site settings -------------------------------------------------------
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'NESTORA'),
('tagline', 'A Home That Takes Care of You'),
('hero_headline', 'More Than A Home. A Feeling.'),
('hero_subtext', 'Designed for emotional comfort. Curated furniture and signature home scents for a home that takes care of you.'),
('whatsapp_number', '60123456789'),
('whatsapp_default_message', 'Hi Nestora, I would like to discover my home feeling.'),
('contact_email', 'hello@nestora.my'),
('contact_phone', '+60 12-345 6789'),
('installment_public_text', 'Bring comfort home today, pay comfortably over time.'),
('delivery_public_text', 'Delivery timeline will be confirmed by our Nestora team after order confirmation.'),
('ambassador_name', 'Nestora Comfort Ambassador'),
('ambassador_text', 'Curated with people who believe a home should feel like care.')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
