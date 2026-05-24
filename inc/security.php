<?php
/**
 * NESTORA.my - Login rate limiting (brute-force throttle).
 *
 * Tracks failed sign-in attempts per IP + scope ('admin' | 'customer').
 * After LOGIN_MAX_ATTEMPTS failures the IP is locked for LOGIN_LOCK_MINUTES.
 * A successful login clears the counter.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

const LOGIN_MAX_ATTEMPTS  = 5;
const LOGIN_LOCK_MINUTES  = 15;

function client_ip(): string
{
    // REMOTE_ADDR only: the proxy headers are client-controlled and must
    // not be trusted for a security decision.
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip !== '' ? substr($ip, 0, 45) : '0.0.0.0';
}

/**
 * @return array{0:bool,1:int} [allowed, seconds_until_unlock]
 */
function login_throttle_check(string $scope): array
{
    try {
        $stmt = db()->prepare(
            'SELECT locked_until FROM login_attempts WHERE ip = :ip AND scope = :s LIMIT 1'
        );
        $stmt->execute([':ip' => client_ip(), ':s' => $scope]);
        $until = $stmt->fetchColumn();
        if ($until) {
            $remaining = strtotime((string) $until) - time();
            if ($remaining > 0) {
                return [false, $remaining];
            }
        }
    } catch (Throwable $e) {
        // Fail open: never lock people out because of a DB hiccup.
    }
    return [true, 0];
}

function login_record_failure(string $scope): void
{
    try {
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO login_attempts (ip, scope, attempts) VALUES (:ip, :s, 1)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1'
        )->execute([':ip' => client_ip(), ':s' => $scope]);

        $row = $pdo->prepare('SELECT attempts FROM login_attempts WHERE ip = :ip AND scope = :s');
        $row->execute([':ip' => client_ip(), ':s' => $scope]);
        $attempts = (int) $row->fetchColumn();

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $pdo->prepare(
                "UPDATE login_attempts
                 SET locked_until = DATE_ADD(NOW(), INTERVAL :m MINUTE), attempts = 0
                 WHERE ip = :ip AND scope = :s"
            )->execute([':m' => LOGIN_LOCK_MINUTES, ':ip' => client_ip(), ':s' => $scope]);
        }
    } catch (Throwable $e) {
        // Best-effort only.
    }
}

function login_record_success(string $scope): void
{
    try {
        db()->prepare('DELETE FROM login_attempts WHERE ip = :ip AND scope = :s')
            ->execute([':ip' => client_ip(), ':s' => $scope]);
    } catch (Throwable $e) {
        // Best-effort only.
    }
}

function login_lock_message(int $seconds): string
{
    $mins = max(1, (int) ceil($seconds / 60));
    return 'Too many sign-in attempts. Please try again in about ' . $mins
        . ' minute' . ($mins === 1 ? '' : 's') . '.';
}
