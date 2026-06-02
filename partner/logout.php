<?php
require_once __DIR__ . '/../inc/partner_auth.php';
partner_logout();
set_flash('info', 'You have signed out of the Partner Portal.');
redirect(base_url('/partner/login.php'));
