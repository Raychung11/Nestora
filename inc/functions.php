<?php
/**
 * NESTORA.my - Core reusable functions
 * Loaded by every public and admin page.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --------------------------------------------------------------------
 * Output escaping
 * ------------------------------------------------------------------ */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* --------------------------------------------------------------------
 * Redirect helper
 * ------------------------------------------------------------------ */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    return BASE_URL . $path;
}

/* --------------------------------------------------------------------
 * CSRF protection (used on all admin POST forms + public forms)
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
        http_response_code(419);
        exit('Invalid or expired form session. Please go back and try again.');
    }
}

/* --------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------ */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* --------------------------------------------------------------------
 * Input helpers
 * ------------------------------------------------------------------ */
function input(string $key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(trim($text, '-'));
    $text = preg_replace('~[^-a-z0-9]+~', '', $text) ?? '';
    $text = trim(preg_replace('~-+~', '-', $text) ?? '', '-');
    return $text !== '' ? $text : 'item-' . substr((string) time(), -6);
}

/* --------------------------------------------------------------------
 * Money & installment helpers
 * ------------------------------------------------------------------ */
function money(float $amount): string
{
    return 'RM' . number_format($amount, 2);
}

/**
 * Effective price = promo price when set and lower, otherwise price.
 */
function effective_price(array $product): float
{
    $price = (float) $product['price'];
    if (isset($product['promo_price']) && $product['promo_price'] !== null
        && (float) $product['promo_price'] > 0
        && (float) $product['promo_price'] < $price) {
        return (float) $product['promo_price'];
    }
    return $price;
}

/**
 * monthly_payment = product_price / months
 * (Optional processing fee reserved for future use.)
 */
function monthly_payment(float $price, int $months, float $processing_fee = 0.0): float
{
    if ($months <= 0) {
        return $price;
    }
    return ($price + $processing_fee) / $months;
}

function installment_label(array $product): ?string
{
    if (empty($product['installment_eligible'])) {
        return null;
    }
    $months = (int) ($product['max_installment_months'] ?? 24);
    $monthly = monthly_payment(effective_price($product), $months);
    return 'From ' . money($monthly) . '/month for ' . $months . ' months';
}

/* --------------------------------------------------------------------
 * Site settings (key/value)
 * ------------------------------------------------------------------ */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/* --------------------------------------------------------------------
 * WhatsApp helper
 * ------------------------------------------------------------------ */
function whatsapp_url(?string $message = null): string
{
    $number = preg_replace('/\D+/', '', (string) get_setting('whatsapp_number', '60123456789'));
    $text   = $message ?? get_setting('whatsapp_default_message', 'Hi Nestora, I would like to discover my home feeling.');
    return 'https://wa.me/' . $number . '?text=' . rawurlencode((string) $text);
}

/* --------------------------------------------------------------------
 * Product image helper
 * ------------------------------------------------------------------ */
function product_primary_image(int $productId): ?string
{
    $stmt = db()->prepare(
        'SELECT file_path FROM product_images
         WHERE product_id = :pid
         ORDER BY is_primary DESC, sort_order ASC, id ASC
         LIMIT 1'
    );
    $stmt->execute([':pid' => $productId]);
    $path = $stmt->fetchColumn();
    return $path !== false ? (string) $path : null;
}

function product_image_url(?string $filePath): string
{
    if ($filePath && is_file(APP_ROOT . '/' . ltrim($filePath, '/'))) {
        return base_url('/' . ltrim($filePath, '/'));
    }
    // Graceful placeholder keeps the premium look even with no photo yet.
    return base_url('/assets/images/placeholder.svg');
}

/* --------------------------------------------------------------------
 * Order number generator
 * ------------------------------------------------------------------ */
function generate_order_number(): string
{
    return 'NST-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/* --------------------------------------------------------------------
 * Image upload validation (jpg, png, webp only)
 * ------------------------------------------------------------------ */
function handle_image_upload(array $file, string $targetDir, string $prefix = 'img'): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Please try again.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image too large (max 5MB).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $ext      = $allowed[$mime];
    $filename = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = rtrim($targetDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    // Return path relative to project root for storage in DB.
    return 'uploads/' . basename($targetDir) . '/' . $filename;
}

/* --------------------------------------------------------------------
 * Human-friendly status label
 * ------------------------------------------------------------------ */
function label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}
