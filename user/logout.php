<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare('UPDATE users SET session_token = NULL WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
    } catch (Throwable $e) {
        app_log('USER LOGOUT ERROR: ' . $e->getMessage());
    }
}

unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_session_token']);
set_flash('success', 'Logged out successfully.');
redirect('/user/login.php');
