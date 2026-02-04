<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['admin_id'])) {
    set_flash('error', 'Please login as admin.');
    redirect('/admin/login.php');
}

$adminAuthStmt = $pdo->prepare('SELECT id, username FROM admins WHERE id = ?');
$adminAuthStmt->execute([(int)$_SESSION['admin_id']]);
$adminAuthUser = $adminAuthStmt->fetch();

if (!$adminAuthUser) {
    destroy_session_and_redirect('/admin/login.php', 'error', 'Admin session expired. Please login again.');
}

$GLOBALS['admin_auth_user'] = $adminAuthUser;
