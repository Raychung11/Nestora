<?php
/**
 * NESTORA.my - Invoice & receipt documents.
 *
 * An invoice number is issued automatically when an order is placed.
 * A receipt number is issued automatically once payment is verified.
 * Documents are viewed/printed via /document.php, gated by an
 * unguessable per-order token (or an admin / owning-customer session).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

/** Stable per-install secret used to sign document links. */
function document_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $pdo = db();
    $row = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='app_secret'")->fetchColumn();
    if ($row === false || $row === null || $row === '') {
        $gen = bin2hex(random_bytes(32));
        $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value) VALUES ('app_secret', :v)
             ON DUPLICATE KEY UPDATE setting_value = setting_value"
        )->execute([':v' => $gen]);
        // Re-read so concurrent first-writers converge on one value.
        $row = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='app_secret'")->fetchColumn();
    }
    $secret = (string) $row;
    return $secret;
}

function document_token(string $orderNumber): string
{
    return substr(hash_hmac('sha256', $orderNumber, document_secret()), 0, 24);
}

function verify_document_token(string $orderNumber, ?string $token): bool
{
    return is_string($token) && $token !== ''
        && hash_equals(document_token($orderNumber), $token);
}

function generate_doc_number(string $prefix): string
{
    return $prefix . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Issue an invoice number for an order if it doesn't have one yet. */
function ensure_invoice(PDO $pdo, int $orderId): void
{
    $cur = $pdo->prepare('SELECT invoice_number FROM orders WHERE id = :id');
    $cur->execute([':id' => $orderId]);
    if ($cur->fetchColumn()) {
        return;
    }
    $num = generate_doc_number((string) get_setting('invoice_prefix', 'INV-'));
    $pdo->prepare(
        "UPDATE orders SET invoice_number = :n, invoice_issued_at = NOW()
         WHERE id = :id AND (invoice_number IS NULL OR invoice_number = '')"
    )->execute([':n' => $num, ':id' => $orderId]);
}

/** Issue a receipt number for an order if it doesn't have one yet. */
function ensure_receipt(PDO $pdo, int $orderId): void
{
    $cur = $pdo->prepare('SELECT receipt_number FROM orders WHERE id = :id');
    $cur->execute([':id' => $orderId]);
    if ($cur->fetchColumn()) {
        return;
    }
    $num = generate_doc_number((string) get_setting('receipt_prefix', 'RCP-'));
    $pdo->prepare(
        "UPDATE orders SET receipt_number = :n, receipt_issued_at = NOW()
         WHERE id = :id AND (receipt_number IS NULL OR receipt_number = '')"
    )->execute([':n' => $num, ':id' => $orderId]);
}

/** Absolute, signed link to a printable document for emails/links. */
function document_link(string $orderNumber, string $type): string
{
    return site_origin() . base_url(
        '/document.php?order=' . urlencode($orderNumber)
        . '&type=' . urlencode($type)
        . '&k=' . document_token($orderNumber)
    );
}

/**
 * Full standalone printable HTML for an invoice or receipt.
 * $type is 'invoice' or 'receipt'.
 */
function render_document(array $order, array $items, string $type): string
{
    $isReceipt = $type === 'receipt';
    $docTitle  = $isReceipt ? 'RECEIPT' : 'INVOICE';
    $docNo     = $isReceipt ? (string) ($order['receipt_number'] ?? '') : (string) ($order['invoice_number'] ?? '');
    $docDate   = $isReceipt
        ? (string) ($order['receipt_issued_at'] ?? $order['updated_at'] ?? '')
        : (string) ($order['invoice_issued_at'] ?? $order['created_at'] ?? '');
    $dateOut   = $docDate ? date('d M Y', strtotime($docDate)) : date('d M Y');

    $coName  = trim((string) get_setting('company_name', '')) ?: (string) get_setting('site_name', 'NESTORA');
    $coReg   = trim((string) get_setting('company_reg_no', ''));
    $coAddr  = trim((string) get_setting('company_address', ''));
    $coEmail = (string) get_setting('contact_email', 'hello@nestora.my');
    $coPhone = (string) get_setting('contact_phone', '');

    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr>'
            . '<td>' . e($it['product_name']) . ($it['sku'] ? '<br><span class="muted">' . e((string) $it['sku']) . '</span>' : '') . '</td>'
            . '<td class="num">' . money((float) $it['unit_price']) . '</td>'
            . '<td class="num">' . (int) $it['quantity'] . '</td>'
            . '<td class="num">' . money((float) $it['line_total']) . '</td>'
            . '</tr>';
    }

    $paidBadge = $isReceipt
        ? '<div class="paid-stamp">PAID</div>'
        : '';

    $payNote = $isReceipt
        ? '<p class="muted">Payment received with thanks. This receipt confirms your payment for the order above.</p>'
        : '<p class="muted">Please complete payment using the details below, then upload your proof at '
            . e(site_origin() . base_url('/payment.php?order=' . urlencode((string) $order['order_number']))) . '</p>'
            . '<p class="muted">'
            . 'Bank: ' . e((string) get_setting('bank_name', '')) . ' &middot; '
            . 'Account: ' . e((string) get_setting('bank_account_name', '')) . ' &middot; '
            . e((string) get_setting('bank_account_number', '')) . '</p>';

    ob_start();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle . ' ' . $docNo) ?> &middot; <?= e($coName) ?></title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;color:#3a2f25;background:#f4ede2;margin:0;padding:28px}
  .sheet{max-width:780px;margin:0 auto;background:#fff;border:1px solid #e3d6c4;border-radius:14px;padding:40px 44px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;border-bottom:2px solid #5b4636;padding-bottom:20px;margin-bottom:24px}
  .brand{font-size:24px;letter-spacing:4px;color:#5b4636;font-weight:700}
  .brand small{display:block;letter-spacing:1px;font-size:11px;color:#8a7d6e;font-weight:400;margin-top:4px}
  .doc-meta{text-align:right}
  .doc-meta h1{margin:0;font-size:26px;letter-spacing:3px;color:#5b4636}
  .doc-meta p{margin:4px 0 0;font-size:13px;color:#6b5d4d}
  .parties{display:flex;justify-content:space-between;gap:30px;margin-bottom:22px;font-size:13px;line-height:1.55}
  .parties h3{margin:0 0 6px;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#8a7d6e}
  table{width:100%;border-collapse:collapse;margin-top:8px;font-size:13px}
  th{background:#f0e7da;color:#5b4636;text-align:left;padding:10px 12px;font-size:11px;letter-spacing:1px;text-transform:uppercase}
  td{padding:11px 12px;border-bottom:1px solid #ece2d2;vertical-align:top}
  .num{text-align:right;white-space:nowrap}
  .muted{color:#8a7d6e;font-size:12px}
  .totals{margin-top:18px;margin-left:auto;width:280px;font-size:14px}
  .totals div{display:flex;justify-content:space-between;padding:7px 0}
  .totals .grand{border-top:2px solid #5b4636;margin-top:6px;padding-top:12px;font-size:18px;color:#5b4636;font-weight:700}
  .paid-stamp{display:inline-block;margin-top:10px;border:3px solid #2e7d52;color:#2e7d52;padding:6px 18px;border-radius:8px;font-weight:700;letter-spacing:3px;transform:rotate(-4deg)}
  .foot{margin-top:34px;border-top:1px solid #ece2d2;padding-top:16px;font-size:11px;color:#8a7d6e;text-align:center}
  .actions{max-width:780px;margin:0 auto 16px;text-align:right}
  .btn{display:inline-block;background:#5b4636;color:#fff;text-decoration:none;padding:10px 20px;border-radius:8px;font-size:13px;border:0;cursor:pointer}
  @media print{ body{background:#fff;padding:0} .sheet{border:0;border-radius:0} .actions{display:none} }
</style>
</head>
<body>
<div class="actions"><button class="btn" onclick="window.print()">Print / Save as PDF</button></div>
<div class="sheet">
  <div class="top">
    <div>
      <div class="brand">NESTORA<small><?= e($coName) ?></small></div>
      <?php if ($coReg): ?><p class="muted" style="margin:8px 0 0">Reg. No: <?= e($coReg) ?></p><?php endif; ?>
      <?php if ($coAddr): ?><p class="muted" style="margin:4px 0 0;max-width:280px"><?= nl2br(e($coAddr)) ?></p><?php endif; ?>
      <p class="muted" style="margin:4px 0 0"><?= e($coEmail) ?><?= $coPhone ? ' &middot; ' . e($coPhone) : '' ?></p>
    </div>
    <div class="doc-meta">
      <h1><?= e($docTitle) ?></h1>
      <p><strong><?= e($docNo ?: '—') ?></strong></p>
      <p>Date: <?= e($dateOut) ?></p>
      <p>Order: <?= e((string) $order['order_number']) ?></p>
      <?= $paidBadge ?>
    </div>
  </div>

  <div class="parties">
    <div>
      <h3>Billed to</h3>
      <strong><?= e((string) $order['customer_name']) ?></strong><br>
      <?= e((string) $order['phone']) ?><?= $order['email'] ? '<br>' . e((string) $order['email']) : '' ?><br>
      <?= nl2br(e((string) ($order['address'] ?? ''))) ?>
    </div>
    <div style="text-align:right">
      <h3>Payment</h3>
      <?= e(label((string) $order['payment_method'])) ?><br>
      Status: <?= e(label((string) $order['payment_status'])) ?>
      <?php if ((int) $order['installment_months'] > 0): ?>
        <br><?= (int) $order['installment_months'] ?>-month plan
      <?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th>Item</th><th class="num">Unit</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
    <tbody><?= $rows ?></tbody>
  </table>

  <div class="totals">
    <div class="grand"><span>Total</span><span><?= money((float) $order['total_amount']) ?></span></div>
  </div>

  <div style="margin-top:22px"><?= $payNote ?></div>

  <div class="foot">
    Thank you for choosing NESTORA &mdash; A Home That Takes Care of You.<br>
    This document was generated electronically and is valid without signature.
  </div>
</div>
</body>
</html><?php
    return (string) ob_get_clean();
}
