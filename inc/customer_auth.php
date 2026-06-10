<?php
/**
 * NESTORA.my - Customer authentication.
 * Customer sessions are kept under $_SESSION['customer'], separate from
 * the admin session, so the two never collide.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function customer_set_session(int $id, string $name, ?string $email): void
{
    session_regenerate_id(true);
    $_SESSION['customer'] = ['id' => $id, 'name' => $name, 'email' => $email];
}

/**
 * @return array{0:bool,1:string} [success, message]
 */
function customer_register(string $name, string $phone, string $email, string $password): array
{
    $pdo = db();
    // utf8mb4_unicode_ci is case-insensitive, so this also catches mixed case.
    $stmt = $pdo->prepare('SELECT id, password_hash FROM customers WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $existing = $stmt->fetch();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($existing) {
        if (!empty($existing['password_hash'])) {
            return [false, 'This email is already registered. Please sign in instead.'];
        }
        // Guest record from a previous checkout — attach credentials so
        // their earlier orders stay linked to this new account.
        $pdo->prepare('UPDATE customers SET name = :n, phone = :p, password_hash = :h WHERE id = :id')
            ->execute([':n' => $name, ':p' => $phone, ':h' => $hash, ':id' => $existing['id']]);
        $id = (int) $existing['id'];
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO customers (name, phone, email, password_hash) VALUES (:n,:p,:e,:h)'
        );
        $ins->execute([':n' => $name, ':p' => $phone, ':e' => $email, ':h' => $hash]);
        $id = (int) $pdo->lastInsertId();
    }

    customer_set_session($id, $name, $email);
    return [true, 'Welcome to Nestora.'];
}

function customer_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, name, email, password_hash FROM customers WHERE email = :e LIMIT 1'
    );
    $stmt->execute([':e' => $email]);
    $c = $stmt->fetch();

    if (!$c || empty($c['password_hash']) || !password_verify($password, $c['password_hash'])) {
        return false;
    }
    customer_set_session((int) $c['id'], (string) $c['name'], $c['email']);
    return true;
}

function customer_logout(): void
{
    unset($_SESSION['customer']);
    session_regenerate_id(true);
}

function current_customer(): ?array
{
    return $_SESSION['customer'] ?? null;
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        set_flash('error', 'Please sign in to view your account.');
        redirect(base_url('/login.php'));
    }
    return $c;
}
