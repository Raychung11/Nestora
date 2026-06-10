<?php
/**
 * NESTORA.my - Minimal SMTP mailer (no external library).
 *
 * Sends authenticated HTML mail via Hostinger SMTP (or any SMTP host).
 * All configuration lives in site_settings (Admin -> Settings), with an
 * optional NESTORA_SMTP_PASS environment override for the password.
 *
 * Design rule: sending must NEVER break a customer flow. Every public
 * call site wraps these helpers so a mail failure is logged and ignored.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/functions.php';

function mail_is_enabled(): bool
{
    if (get_setting('mail_enabled', '0') !== '1') {
        return false;
    }
    return smtp_password() !== '' && get_setting('smtp_user', '') !== '';
}

function smtp_password(): string
{
    $env = getenv('NESTORA_SMTP_PASS');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return (string) get_setting('smtp_pass', '');
}

/**
 * Send an HTML email. Returns true on success, false on any failure
 * (failure reason is error_log'd, never thrown to the caller).
 */
function send_mail(string $to, string $subject, string $htmlBody, ?string $replyTo = null): bool
{
    if (!mail_is_enabled()) {
        return false;
    }

    $host   = get_setting('smtp_host', 'smtp.hostinger.com') ?: 'smtp.hostinger.com';
    $port   = (int) (get_setting('smtp_port', '465') ?: 465);
    $secure = strtolower((string) get_setting('smtp_secure', 'ssl')); // ssl | tls
    $user   = (string) get_setting('smtp_user', 'hello@nestora.my');
    $pass   = smtp_password();
    $fromEmail = (string) get_setting('mail_from_email', $user) ?: $user;
    $fromName  = (string) get_setting('mail_from_name', 'NESTORA') ?: 'NESTORA';

    try {
        return smtp_dispatch(
            $host, $port, $secure, $user, $pass,
            $fromEmail, $fromName, $to, $subject, $htmlBody, $replyTo
        );
    } catch (Throwable $e) {
        error_log('[nestora-mail] ' . $e->getMessage());
        return false;
    }
}

/** Notify the store inbox. Reply-To is set so admin can reply to the lead. */
function notify_admin(string $subject, string $htmlBody, ?string $replyTo = null): bool
{
    $to = (string) get_setting('mail_admin_to', get_setting('contact_email', 'hello@nestora.my'));
    if ($to === '') {
        return false;
    }
    return send_mail($to, '[Nestora] ' . $subject, $htmlBody, $replyTo);
}

/** Wrap content in a simple branded HTML shell. */
function mail_template(string $heading, string $bodyHtml): string
{
    $year = date('Y');
    return '<div style="font-family:Arial,Helvetica,sans-serif;background:#fbf6ef;padding:28px">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e3d6c4;border-radius:16px;overflow:hidden">'
        . '<div style="background:#5b4636;color:#fff;padding:22px 26px;font-size:20px;letter-spacing:3px">NESTORA</div>'
        . '<div style="padding:26px">'
        . '<h2 style="color:#5b4636;margin:0 0 14px;font-size:20px">' . htmlspecialchars($heading, ENT_QUOTES) . '</h2>'
        . $bodyHtml
        . '</div>'
        . '<div style="padding:16px 26px;background:#f0e7da;color:#8a7d6e;font-size:12px">'
        . '&copy; ' . $year . ' NESTORA. A Home That Takes Care of You.</div>'
        . '</div></div>';
}

/* --------------------------------------------------------------------
 * Low-level SMTP conversation
 * ------------------------------------------------------------------ */
function smtp_dispatch(
    string $host, int $port, string $secure, string $user, string $pass,
    string $fromEmail, string $fromName, string $to, string $subject,
    string $htmlBody, ?string $replyTo
): bool {
    $transport = ($secure === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp  = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Last line of a reply has a space as the 4th char (e.g. "250 ").
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $expect = function (string $resp, string $codes) {
        if (!in_array(substr($resp, 0, 3), explode(',', $codes), true)) {
            throw new RuntimeException('SMTP error: ' . trim($resp));
        }
    };
    $cmd = function (string $c) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        return $read();
    };

    $expect($read(), '220');
    $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['SERVER_NAME'] ?? 'nestora.my');
    $expect($cmd("EHLO $ehloHost"), '250');

    if ($secure === 'tls') {
        $expect($cmd('STARTTLS'), '220');
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS negotiation failed');
        }
        $expect($cmd("EHLO $ehloHost"), '250');
    }

    $expect($cmd('AUTH LOGIN'), '334');
    $expect($cmd(base64_encode($user)), '334');
    $expect($cmd(base64_encode($pass)), '235');

    $expect($cmd('MAIL FROM:<' . $fromEmail . '>'), '250');
    $expect($cmd('RCPT TO:<' . $to . '>'), '250,251');
    $expect($cmd('DATA'), '354');

    $headers  = 'From: ' . mb_encode_mimeheader($fromName) . " <$fromEmail>\r\n";
    $headers .= 'To: <' . $to . ">\r\n";
    if ($replyTo) {
        $headers .= 'Reply-To: <' . $replyTo . ">\r\n";
    }
    $headers .= 'Subject: ' . mb_encode_mimeheader($subject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'Date: ' . date('r') . "\r\n";

    // Dot-stuff lines beginning with '.' per RFC 5321.
    $body = preg_replace('/^\./m', '..', $htmlBody);
    $expect($cmd($headers . "\r\n" . $body . "\r\n."), '250');
    $cmd('QUIT');
    fclose($fp);
    return true;
}
