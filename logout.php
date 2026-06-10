<?php
require_once __DIR__ . '/inc/customer_auth.php';
customer_logout();
set_flash('info', 'You have been signed out.');
redirect(base_url('/index.php'));
