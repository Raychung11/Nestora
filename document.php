<?php
/**
 * NESTORA.my - Printable invoice / receipt.
 *
 * Access: a signed token (?k=), OR a logged-in admin, OR the customer
 * who owns the order (matched by account id or order email).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/customer_auth.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/documents.php';

$orderNumber = input('order');
$type        = input('type') === 'receipt' ? 'receipt' : 'invoice';
$token       = input('k');

if ($orderNumber === '') {
    http_response_code(404);
    exit('Document not found.');
}

$pdo  = db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
$stmt->execute([':n' => $orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Document not found.');
}

// --- Authorisation -------------------------------------------------------
$allowed = false;

if (current_admin()) {
    $allowed = true;
} elseif (verify_document_token($orderNumber, $token)) {
    $allowed = true;
} elseif ($cust = current_customer()) {
    if ((int) ($order['customer_id'] ?? 0) === (int) $cust['id']) {
        $allowed = true;
    } else {
        $row = $pdo->prepare('SELECT email FROM customers WHERE id = :id LIMIT 1');
        $row->execute([':id' => $cust['id']]);
        $custEmail = (string) $row->fetchColumn();
        if ($custEmail !== '' && $order['email'] && strcasecmp($custEmail, (string) $order['email']) === 0) {
            $allowed = true;
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('You are not authorised to view this document.');
}

// Backfill an invoice number on first view (orders predating this feature).
if ($type === 'invoice' && empty($order['invoice_number'])) {
    ensure_invoice($pdo, (int) $order['id']);
    $stmt->execute([':n' => $orderNumber]);
    $order = $stmt->fetch();
}

// A receipt only exists once payment has been verified.
if ($type === 'receipt' && empty($order['receipt_number'])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<p style="font-family:Arial;padding:40px;color:#5b4636">'
        . 'A receipt will be available here once your payment has been verified.</p>');
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id');
$itemsStmt->execute([':id' => $order['id']]);
$items = $itemsStmt->fetchAll();

header('Content-Type: text/html; charset=utf-8');
echo render_document($order, $items, $type);
