<?php
require_once __DIR__ . '/../inc/auth.php';
admin_logout();
set_flash('info', 'You have been signed out.');
redirect(base_url('/admin/login.php'));
