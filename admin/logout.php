<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

unset($_SESSION['admin_id'], $_SESSION['admin_username']);
set_flash('success', 'Logged out successfully.');
redirect('/admin/login.php');
