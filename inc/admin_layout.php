<?php
/**
 * NESTORA.my - Admin layout.
 * Usage:
 *   $pageTitle = 'Dashboard';
 *   require_once __DIR__ . '/../inc/auth.php';
 *   $admin = require_admin();
 *   require_once __DIR__ . '/../inc/admin_layout.php';   // opens layout
 *   ... page content ...
 *   admin_layout_end();                                  // closes layout
 */

declare(strict_types=1);

$admin     = current_admin();
$pageTitle = $pageTitle ?? 'Admin';
$nav = [
    'dashboard.php'            => 'Dashboard',
    'orders.php'               => 'Orders',
    'payments.php'             => 'Payments',
    'quiz_leads.php'           => 'Comfort Quiz Leads',
    'whatsapp_leads.php'       => 'WhatsApp Leads',
    'installment_requests.php' => 'Installment Requests',
    'subscriptions.php'        => 'Scent Subscriptions',
    'products.php'             => 'Products',
    'bundles.php'              => 'Bundle Packages',
    'vouchers.php'             => 'Voucher Codes',
    'categories.php'           => 'Categories',
    'customers.php'            => 'Customers',
    'suppliers.php'            => 'Suppliers',
    'purchase_orders.php'      => 'Purchase Orders',
    'banners.php'              => 'Banners',
    'testimonials.php'         => 'Testimonials',
    'settings.php'             => 'Settings',
];
$currentFile = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; Nestora Admin</title>
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">NESTORA<span>Admin</span></div>
        <nav>
            <?php foreach ($nav as $file => $title): ?>
                <a href="<?= base_url('/admin/' . $file) ?>"
                   class="<?= $currentFile === $file ? 'active' : '' ?>"><?= e($title) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="admin-logout" href="<?= base_url('/admin/logout.php') ?>">Sign out</a>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <h1><?= e($pageTitle) ?></h1>
            <div class="admin-topbar-actions">
                <a class="btn btn-soft btn-sm" href="<?= base_url('/index.php') ?>" target="_blank" rel="noopener">View site &#8599;</a>
                <div class="admin-user">
                    <?= e($admin['name'] ?? 'Admin') ?>
                    <span class="admin-role"><?= e(label($admin['role'] ?? 'admin')) ?></span>
                </div>
                <a class="btn btn-danger btn-sm" href="<?= base_url('/admin/logout.php') ?>">Sign out</a>
            </div>
        </header>
        <div class="admin-content">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
<?php
/** Call this at the end of an admin page to close the layout. */
function admin_layout_end(): void
{
    ?>
        </div>
    </div>
</div>
</body>
</html>
<?php
}
