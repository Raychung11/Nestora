<?php
/**
 * NESTORA.my - /admin/ entry point.
 * Sends visitors to the dashboard if signed in, otherwise to login.
 */

require_once __DIR__ . '/../inc/auth.php';

redirect(base_url(current_admin() ? '/admin/dashboard.php' : '/admin/login.php'));
