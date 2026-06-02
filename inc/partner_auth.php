<?php
/**
 * NESTORA.my - Partner portal authentication.
 * Stored under $_SESSION['partner'], separate from customer/admin.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function partner_set_session(array $partner): void
{
    session_regenerate_id(true);
    $_SESSION['partner'] = [
        'id'            => (int) $partner['id'],
        'name'          => (string) $partner['name'],
        'email'         => (string) $partner['email'],
        'tier'          => (string) $partner['tier'],
        'referral_code' => (string) $partner['referral_code'],
    ];
}

function partner_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        "SELECT * FROM partners WHERE email = :e AND status = 'active' LIMIT 1"
    );
    $stmt->execute([':e' => $email]);
    $p = $stmt->fetch();
    if (!$p || !password_verify($password, (string) $p['password_hash'])) {
        return false;
    }
    partner_set_session($p);
    db()->prepare('UPDATE partners SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => $p['id']]);
    return true;
}

function partner_logout(): void
{
    unset($_SESSION['partner']);
    session_regenerate_id(true);
}

function current_partner(): ?array
{
    return $_SESSION['partner'] ?? null;
}

function require_partner(): array
{
    $p = current_partner();
    if (!$p) {
        set_flash('error', 'Please sign in to access your Partner Portal.');
        redirect(base_url('/partner/login.php'));
    }
    return $p;
}

/** Load the full DB row for the signed-in partner (fresh, not just session snapshot). */
function current_partner_row(): ?array
{
    $sess = current_partner();
    if (!$sess) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $sess['id']]);
    return $stmt->fetch() ?: null;
}
