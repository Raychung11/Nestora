<?php
require_once __DIR__ . '/inc/functions.php';
$pageTitle = 'About Nestora';
$pageDesc  = 'NESTORA is an emotional home living brand — a home that takes care of you.';
require_once __DIR__ . '/inc/header.php';
?>
<section class="hero" style="padding:80px 0">
    <div class="container hero-inner">
        <span class="eyebrow">About Nestora</span>
        <h1>More Than A Home. A Feeling.</h1>
        <p>NESTORA is not just a furniture shop. It is an emotional home living brand built around warmth, calm and the comfort of coming home.</p>
    </div>
</section>

<section>
    <div class="container" style="max-width:820px">
        <h2 style="margin-bottom:14px">A home that takes care of you</h2>
        <p class="muted" style="margin-bottom:26px">We believe a home should do more than look beautiful — it should make you feel held. Every Nestora piece and signature scent is thoughtfully selected to support warmth, calm and emotional comfort.</p>

        <div class="eco-grid" style="grid-template-columns:repeat(3,1fr);margin:30px 0">
            <div class="eco-card"><h3>Comfort Living</h3><span>Calming furniture</span></div>
            <div class="eco-card"><h3>Scent Memory</h3><span>Signature home scents</span></div>
            <div class="eco-card"><h3>Home Wellness</h3><span>AI comfort guidance</span></div>
        </div>

        <h2 style="margin:30px 0 14px">Designed for emotional comfort</h2>
        <p class="muted">From curated furniture to our launch scent collection — Sunday Cotton, Evening Tea and Warm Kitchen — Nestora is here to make your home feel like care. Bring comfort home today, pay comfortably over time.</p>

        <div style="margin-top:34px">
            <a class="btn btn-primary btn-lg" href="<?= base_url('/comfort_quiz.php') ?>">Discover Your Home Feeling</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
