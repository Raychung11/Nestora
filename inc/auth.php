<?php
/**
 * NESTORA.my - Admin authentication guard.
 * require_once this at the top of every protected /admin page.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function admin_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, name, email, password_hash, role, status
         FROM admin_users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    // Prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['admin'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];

    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => $user['id']]);

    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin']);
    session_regenerate_id(true);
}

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        set_flash('error', 'Please sign in to continue.');
        redirect(base_url('/admin/login.php'));
    }
    return $admin;
}
