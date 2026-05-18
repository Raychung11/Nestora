<?php
require_once __DIR__ . '/inc/functions.php';

$pageTitle = 'Comfort Quiz';
$pageDesc  = 'Discover your home feeling with the Nestora AI Comfort Advisor.';

$results = null;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $feeling     = input('home_feeling');
    $room        = input('room');
    $concern     = input('main_concern');
    $preference  = input('preference');
    $budget      = input('budget_range');
    $installment = input('installment_pref');
    $name        = input('name');
    $phone       = input('phone');
    $email       = input('email');

    if ($name === '')  { $errors[] = 'Please tell us your name.'; }
    if ($phone === '') { $errors[] = 'Please share a contact number so we can follow up warmly.'; }

    if (!$errors) {
        // ---- Budget ceiling for recommendations ----
        $budgetMax = match ($budget) {
            'Below RM500'      => 500,
            'RM500–RM1,500'    => 1500,
            'RM1,500–RM3,000'  => 3000,
            'Above RM3,000'    => 1000000,
            default            => 1000000,
        };
        $feelingKey = strtolower(trim($feeling));

        // ---- Furniture recommendation ----
        $fStmt = db()->prepare(
            "SELECT * FROM products
             WHERE status='active' AND product_type='furniture'
               AND COALESCE(promo_price, price) <= :bmax
             ORDER BY (feeling_tags LIKE :feel) DESC, is_featured DESC, updated_at DESC
             LIMIT 1"
        );
        $fStmt->execute([':bmax' => $budgetMax, ':feel' => '%' . $feelingKey . '%']);
        $recFurniture = $fStmt->fetch() ?: null;

        // ---- Essential oil recommendation ----
        $oStmt = db()->prepare(
            "SELECT * FROM products
             WHERE status='active' AND product_type='essential_oil'
             ORDER BY (feeling_tags LIKE :feel) DESC, is_featured DESC, updated_at DESC
             LIMIT 1"
        );
        $oStmt->execute([':feel' => '%' . $feelingKey . '%']);
        $recOil = $oStmt->fetch() ?: null;

        $recBundle = ($recFurniture && $recOil)
            ? effective_price($recFurniture) + effective_price($recOil)
            : null;

        $summary = sprintf(
            'Feeling: %s | Room: %s | Concern: %s | Prefers: %s | Budget: %s | Installment: %s',
            $feeling ?: '-', $room ?: '-', $concern ?: '-',
            $preference ?: '-', $budget ?: '-', $installment ?: '-'
        );

        // ---- Save lead ----
        $ins = db()->prepare(
            "INSERT INTO comfort_quiz_leads
             (name, phone, email, home_feeling, room, main_concern, preference,
              budget_range, installment_pref, recommendation, source, status)
             VALUES (:name,:phone,:email,:feeling,:room,:concern,:pref,:budget,:inst,:rec,'comfort_quiz','new')"
        );
        $ins->execute([
            ':name' => $name, ':phone' => $phone, ':email' => $email ?: null,
            ':feeling' => $feeling, ':room' => $room, ':concern' => $concern,
            ':pref' => $preference, ':budget' => $budget, ':inst' => $installment,
            ':rec' => $summary,
        ]);

        $results = [
            'name'      => $name,
            'feeling'   => $feeling,
            'furniture' => $recFurniture,
            'oil'       => $recOil,
            'bundle'    => $recBundle,
        ];
    }
}

require_once __DIR__ . '/inc/header.php';

$questions = [
    ['home_feeling', 'How do you want your home to feel?', ['Calm','Cozy','Fresh','Warm','Premium']],
    ['room', 'Which room do you want to improve?', ['Living room','Bedroom','Study room','Whole home']],
    ['main_concern', 'What is your main concern?', ['Stress','Messy home','Poor sleep','Lack of warmth','New home setup']],
    ['preference', 'Do you prefer?', ['Furniture recommendation','Scent recommendation','Full home comfort set']],
    ['budget_range', 'Budget range', ['Below RM500','RM500–RM1,500','RM1,500–RM3,000','Above RM3,000']],
    ['installment_pref', 'Interested in installment?', ['Yes','No']],
];
?>

<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Nestora AI Comfort Advisor</span>
        <h2>Discover Your Home Feeling</h2>
        <p>Welcome to Nestora. How would you like your home to feel today?</p>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($results): ?>
            <div class="form-card" style="max-width:760px">
                <div class="section-head" style="margin-bottom:24px">
                    <span class="eyebrow">Curated for you</span>
                    <h2>Hi <?= e($results['name']) ?>, here is your comfort set</h2>
                    <p>Based on a <strong><?= e($results['feeling'] ?: 'comforting') ?></strong> home feeling.</p>
                </div>

                <?php
                $renderRec = function (?array $prod, string $tag): void {
                    if (!$prod) return;
                    $img = product_image_url(product_primary_image((int)$prod['id'])); ?>
                    <a class="product-card" style="margin-bottom:18px" href="<?= base_url('/product.php?slug=' . urlencode($prod['slug'])) ?>">
                        <div style="display:flex;gap:18px;align-items:center;padding:16px">
                            <img src="<?= e($img) ?>" alt="<?= e($prod['name']) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:14px">
                            <div>
                                <span class="pc-feel"><?= e($tag) ?></span>
                                <h3><?= e($prod['name']) ?></h3>
                                <p class="muted" style="font-size:.9rem"><?= e($prod['short_description']) ?></p>
                                <div class="pc-price"><span class="now"><?= money(effective_price($prod)) ?></span></div>
                            </div>
                        </div>
                    </a>
                <?php };
                $renderRec($results['furniture'], 'Recommended furniture');
                $renderRec($results['oil'], 'Recommended scent');
                ?>

                <?php if ($results['bundle']): ?>
                    <div class="pd-inst">
                        <strong>Full Home Comfort Set &mdash; <?= money($results['bundle']) ?></strong><br>
                        <span class="muted">A curated furniture + signature scent pairing for your home.</span>
                    </div>
                <?php endif; ?>

                <p class="muted" style="margin:18px 0">Our Nestora AI Comfort Advisor would love to continue with you and confirm everything personally.</p>
                <a class="btn btn-primary btn-lg btn-block" href="<?= whatsapp_url('Hi Nestora, I just completed the Comfort Quiz. My name is ' . $results['name'] . '. I would like to continue.') ?>" target="_blank" rel="noopener">Continue on WhatsApp</a>
            </div>
        <?php else: ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form class="form-card" method="post" id="comfortQuiz">
                <?= csrf_field() ?>
                <div class="quiz-progress"><span></span></div>

                <?php foreach ($questions as $i => [$name, $label, $opts]): ?>
                    <div class="quiz-step<?= $i === 0 ? ' active' : '' ?>">
                        <h3><?= e($label) ?></h3>
                        <input type="hidden" name="<?= e($name) ?>" value="">
                        <div class="quiz-options" data-name="<?= e($name) ?>">
                            <?php foreach ($opts as $opt): ?>
                                <div class="quiz-opt" data-value="<?= e($opt) ?>"><?= e($opt) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="quiz-nav">
                            <?php if ($i > 0): ?>
                                <button type="button" class="btn btn-soft" data-prev>Back</button>
                            <?php else: ?><span></span><?php endif; ?>
                            <button type="button" class="btn btn-primary" data-next>Continue</button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Final step: lead capture -->
                <div class="quiz-step">
                    <h3>Almost there — where shall we send your comfort set?</h3>
                    <p class="muted" style="margin-bottom:18px">We&rsquo;ll curate a recommendation just for you.</p>
                    <div class="field"><label>Your name</label><input type="text" name="name" required></div>
                    <div class="form-row">
                        <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" required></div>
                        <div class="field"><label>Email (optional)</label><input type="email" name="email"></div>
                    </div>
                    <div class="quiz-nav">
                        <button type="button" class="btn btn-soft" data-prev>Back</button>
                        <button type="submit" class="btn btn-primary btn-lg">See my comfort set</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
