<?php
require_once dirname(__DIR__) . '/inc/customer_auth.php';

$pageTitle = $pageTitle ?? get_setting('site_name', 'NESTORA');
$pageDesc  = $pageDesc ?? get_setting('tagline', 'A Home That Takes Care of You');

$ogSiteName = get_setting('site_name', 'NESTORA');
$ogUrl      = site_origin() . ($_SERVER['REQUEST_URI'] ?? '/');
$ogImgPath  = get_setting('share_image', '') ?: get_setting('ambassador_image', '');
$ogImage    = $ogImgPath ? site_origin() . base_url('/' . ltrim($ogImgPath, '/')) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDesc) ?>">
    <title><?= e($pageTitle) ?> &middot; NESTORA</title>

    <meta property="og:site_name" content="<?= e($ogSiteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($ogUrl) ?>">
    <meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDesc) ?>">
    <?php if ($ogImage): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('/assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="<?= base_url('/index.php') ?>" class="brand">
            <span class="brand-mark">NESTORA</span>
            <span class="brand-sub">A Home That Takes Care of You</span>
        </a>
        <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">&#9776;</button>
        <nav class="site-nav">
            <a href="<?= base_url('/index.php') ?>">Home</a>

            <div class="nav-group">
                <button type="button" class="nav-group-label" aria-haspopup="true">Shop</button>
                <div class="nav-dropdown">
                    <a href="<?= base_url('/products.php?type=furniture') ?>">Furniture</a>
                    <a href="<?= base_url('/products.php?type=essential_oil') ?>">Essential Oils</a>
                    <a href="<?= base_url('/installment.php') ?>">Installment Plan</a>
                </div>
            </div>

            <div class="nav-group">
                <button type="button" class="nav-group-label" aria-haspopup="true">Discover</button>
                <div class="nav-dropdown">
                    <a href="<?= base_url('/comfort_quiz.php') ?>">Comfort Quiz</a>
                    <a href="<?= base_url('/comfort_advisor.php') ?>">AI Comfort Advisor</a>
                </div>
            </div>

            <a href="<?= base_url('/about.php') ?>">About</a>
            <a href="<?= base_url('/contact.php') ?>">Contact</a>

            <?php if ($nestoraCustomer = current_customer()): ?>
                <a href="<?= base_url('/account.php') ?>">Hi, <?= e(explode(' ', trim($nestoraCustomer['name']))[0]) ?></a>
                <a href="<?= base_url('/logout.php') ?>">Sign out</a>
            <?php else: ?>
                <a href="<?= base_url('/login.php') ?>">Sign in</a>
            <?php endif; ?>
            <a class="nav-cta" href="<?= base_url('/cart.php') ?>">Cart</a>
        </nav>
    </div>
</header>
<main>
<?php foreach (get_flashes() as $flash): ?>
    <div class="container">
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    </div>
<?php endforeach; ?>
