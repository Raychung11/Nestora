</main>
<a class="whatsapp-float" href="<?= whatsapp_url() ?>" target="_blank" rel="noopener" aria-label="Chat with Nestora AI Comfort Advisor">
    <span>Chat with Nestora AI</span>
</a>
<footer class="site-footer">
    <div class="container footer-grid">
        <?php
            $coName = trim((string) get_setting('company_name', ''));
            $coReg  = trim((string) get_setting('company_reg_no', ''));
            $coAddr = trim((string) get_setting('company_address', ''));
        ?>
        <div>
            <div class="brand-mark footer-brand">NESTORA</div>
            <p class="footer-tag">More Than A Home. A Feeling.</p>
            <p class="footer-muted"><?= e(get_setting('installment_public_text', 'Bring comfort home today, pay comfortably over time.')) ?></p>
            <?php if ($coName || $coReg || $coAddr): ?>
                <div class="footer-company">
                    <?php if ($coName): ?><p class="footer-company-name"><?= e($coName) ?></p><?php endif; ?>
                    <?php if ($coReg): ?><p class="footer-muted">Reg. No: <?= e($coReg) ?></p><?php endif; ?>
                    <?php if ($coAddr): ?><p class="footer-muted"><?= nl2br(e($coAddr)) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <h4>Explore</h4>
            <a href="<?= base_url('/products.php?type=furniture') ?>">Furniture</a>
            <a href="<?= base_url('/products.php?type=essential_oil') ?>">Essential Oils</a>
            <a href="<?= base_url('/comfort_quiz.php') ?>">Comfort Quiz</a>
            <a href="<?= base_url('/installment.php') ?>">Installment Plan</a>
        </div>
        <div>
            <h4>Nestora</h4>
            <a href="<?= base_url('/about.php') ?>">About Nestora</a>
            <a href="<?= base_url('/contact.php') ?>">Contact / WhatsApp</a>
            <a href="<?= base_url('/privacy.php') ?>">Privacy Policy</a>
            <a href="<?= base_url('/terms.php') ?>">Terms &amp; Conditions</a>
        </div>
        <div>
            <h4>Get in touch</h4>
            <p class="footer-muted"><?= e(get_setting('contact_email', 'hello@nestora.my')) ?></p>
            <p class="footer-muted"><?= e(get_setting('contact_phone', '+60 12-345 6789')) ?></p>
            <a class="btn btn-soft" href="<?= whatsapp_url() ?>" target="_blank" rel="noopener">WhatsApp Us</a>
            <?php
                $socials = array_filter([
                    'Facebook'  => trim((string) get_setting('social_facebook', '')),
                    'Instagram' => trim((string) get_setting('social_instagram', '')),
                    'TikTok'    => trim((string) get_setting('social_tiktok', '')),
                    'YouTube'   => trim((string) get_setting('social_youtube', '')),
                ]);
            ?>
            <?php if ($socials): ?>
                <div class="footer-social">
                    <?php foreach ($socials as $label => $url): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer me"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= $coName ? e($coName) : 'NESTORA&trade;' ?>. All rights reserved.</span>
        <span>Designed for Emotional Comfort.</span>
    </div>
</footer>
<script src="<?= base_url('/assets/js/main.js') ?>"></script>
</body>
</html>
