<?php
require_once __DIR__ . '/inc/functions.php';

$pageTitle = 'Nestora AI Comfort Advisor';
$pageDesc  = 'Tell us how you want your home to feel and we will guide you.';

$results = null;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $room        = input('room');
    $feeling     = input('feeling');
    $interest    = input('interest');
    $budget      = input('budget');
    $installment = input('installment');
    $name        = input('name');
    $phone       = input('phone');
    $area        = input('delivery_area');

    if ($name === '')  { $errors[] = 'Please tell us your name.'; }
    if ($phone === '') { $errors[] = 'Please share a contact number.'; }

    if (!$errors) {
        $budgetMax = match ($budget) {
            'Below RM500'     => 500,
            'RM500–RM1,500'   => 1500,
            'RM1,500–RM3,000' => 3000,
            'Above RM3,000'   => 1000000,
            default           => 1000000,
        };
        $feelKey = strtolower(trim($feeling));

        $wantsFurniture = $interest !== 'Scent only';
        $wantsScent     = $interest !== 'Furniture only';

        $recFurniture = null;
        if ($wantsFurniture) {
            $fs = db()->prepare(
                "SELECT * FROM products
                 WHERE status='active' AND product_type='furniture'
                   AND COALESCE(promo_price, price) <= :bmax
                 ORDER BY (feeling_tags LIKE :feel) DESC, is_featured DESC, updated_at DESC
                 LIMIT 1"
            );
            $fs->execute([':bmax' => $budgetMax, ':feel' => '%' . $feelKey . '%']);
            $recFurniture = $fs->fetch() ?: null;
        }

        $recOil = null;
        if ($wantsScent) {
            $os = db()->prepare(
                "SELECT * FROM products
                 WHERE status='active' AND product_type='essential_oil'
                 ORDER BY (feeling_tags LIKE :feel) DESC, is_featured DESC, updated_at DESC
                 LIMIT 1"
            );
            $os->execute([':feel' => '%' . $feelKey . '%']);
            $recOil = $os->fetch() ?: null;
        }

        $interestSummary = trim(sprintf(
            'Room: %s | Feeling: %s | Interest: %s | Budget: %s | Installment: %s',
            $room ?: '-', $feeling ?: '-', $interest ?: '-', $budget ?: '-', $installment ?: '-'
        ));
        $recParts = [];
        if ($recFurniture) { $recParts[] = $recFurniture['name']; }
        if ($recOil)       { $recParts[] = $recOil['name']; }
        $recText = $recParts ? ('Suggested: ' . implode(' + ', $recParts)) : 'No direct match — advisor follow-up needed';

        db()->prepare(
            "INSERT INTO whatsapp_leads (name, phone, delivery_area, interest, message, source, status)
             VALUES (:n,:p,:a,:i,:m,'comfort_advisor','new')"
        )->execute([
            ':n' => $name, ':p' => $phone, ':a' => $area ?: null,
            ':i' => $interest ?: null, ':m' => $interestSummary . ' || ' . $recText,
        ]);

        $waMessage = "Hi Nestora, this is {$name}. " .
            "I'd like a {$feeling} feeling for my {$room}. " .
            ($recParts ? ('I am interested in: ' . implode(' + ', $recParts) . '. ') : '') .
            "Budget: {$budget}. Installment: {$installment}." .
            ($area ? " Delivery area: {$area}." : '');

        $results = [
            'name'      => $name,
            'feeling'   => $feeling,
            'furniture' => $recFurniture,
            'oil'       => $recOil,
            'wa'        => whatsapp_url($waMessage),
        ];
    }
}

require_once __DIR__ . '/inc/header.php';

$steps = [
    ['room', 'Which room would you like to improve?', ['Living room','Bedroom','Study room','Whole home']],
    ['feeling', 'How do you want it to feel?', ['Calm','Cozy','Fresh','Warm','Premium']],
    ['interest', 'What are you interested in?', ['Furniture only','Scent only','Full home comfort set']],
    ['budget', 'Your budget range?', ['Below RM500','RM500–RM1,500','RM1,500–RM3,000','Above RM3,000']],
    ['installment', 'Interested in a monthly comfort plan?', ['Yes','No']],
];
?>
<section class="band-soft" style="padding:56px 0 30px">
    <div class="container section-head" style="margin-bottom:0">
        <span class="eyebrow">Nestora AI Comfort Advisor</span>
        <h2>Welcome to Nestora</h2>
        <p>How would you like your home to feel today? A few questions and we&rsquo;ll guide you &mdash; then continue on WhatsApp.</p>
    </div>
</section>

<section>
    <div class="container">
        <?php if ($results): ?>
            <div class="form-card" style="max-width:760px">
                <div class="section-head" style="margin-bottom:24px">
                    <span class="eyebrow">Curated for you</span>
                    <h2>Hi <?= e($results['name']) ?>, here&rsquo;s a thoughtful start</h2>
                    <p>For a <strong><?= e($results['feeling'] ?: 'comforting') ?></strong> home feeling.</p>
                </div>
                <?php
                $card = function (?array $prod, string $tag): void {
                    if (!$prod) return;
                    $img = product_image_url(product_primary_image((int)$prod['id'])); ?>
                    <a class="product-card" style="margin-bottom:18px" href="<?= base_url('/product.php?slug=' . urlencode($prod['slug'])) ?>">
                        <div style="display:flex;gap:18px;align-items:center;padding:16px">
                            <img src="<?= e($img) ?>" alt="<?= e($prod['name']) ?>" style="width:110px;height:110px;object-fit:cover;border-radius:14px">
                            <div>
                                <span class="pc-feel"><?= e($tag) ?></span>
                                <h3><?= e($prod['name']) ?></h3>
                                <div class="pc-price"><span class="now"><?= money(effective_price($prod)) ?></span></div>
                            </div>
                        </div>
                    </a>
                <?php };
                $card($results['furniture'], 'Recommended furniture');
                $card($results['oil'], 'Recommended scent');
                if (!$results['furniture'] && !$results['oil']): ?>
                    <p class="muted">Our advisor will personally curate the perfect comfort for you.</p>
                <?php endif; ?>
                <p class="muted" style="margin:18px 0">Continue with our Nestora AI Comfort Advisor on WhatsApp to confirm everything personally.</p>
                <a class="btn btn-primary btn-lg btn-block" href="<?= e($results['wa']) ?>" target="_blank" rel="noopener">Continue on WhatsApp</a>
            </div>
        <?php else: ?>
            <?php foreach ($errors as $err): ?>
                <div class="flash flash-error"><?= e($err) ?></div>
            <?php endforeach; ?>
            <form class="form-card" method="post" id="comfortQuiz">
                <?= csrf_field() ?>
                <div class="quiz-progress"><span></span></div>
                <?php foreach ($steps as $i => [$name, $label, $opts]): ?>
                    <div class="quiz-step<?= $i === 0 ? ' active' : '' ?>">
                        <h3><?= e($label) ?></h3>
                        <input type="hidden" name="<?= e($name) ?>" value="">
                        <div class="quiz-options" data-name="<?= e($name) ?>">
                            <?php foreach ($opts as $opt): ?>
                                <div class="quiz-opt" data-value="<?= e($opt) ?>"><?= e($opt) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="quiz-nav">
                            <?php if ($i > 0): ?><button type="button" class="btn btn-soft" data-prev>Back</button>
                            <?php else: ?><span></span><?php endif; ?>
                            <button type="button" class="btn btn-primary" data-next>Continue</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="quiz-step">
                    <h3>Where shall our advisor reach you?</h3>
                    <p class="muted" style="margin-bottom:18px">We&rsquo;ll send your details to our team and open WhatsApp for you.</p>
                    <div class="field"><label>Your name</label><input type="text" name="name" required></div>
                    <div class="form-row">
                        <div class="field"><label>Phone / WhatsApp</label><input type="text" name="phone" required></div>
                        <div class="field"><label>Delivery area</label><input type="text" name="delivery_area"></div>
                    </div>
                    <div class="quiz-nav">
                        <button type="button" class="btn btn-soft" data-prev>Back</button>
                        <button type="submit" class="btn btn-primary btn-lg">Get my recommendation</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
