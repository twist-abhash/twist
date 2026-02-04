<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/helpers.php';

$isApi = defined('API_REQUEST') && API_REQUEST === true;

if (empty($_SESSION['user_id']) || empty($_SESSION['user_session_token'])) {
    if ($isApi) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    set_flash('error', 'Please login first.');
    redirect('/user/login.php');
}

$userAuthStmt = $pdo->prepare('SELECT id, full_name, email, phone, is_blocked, session_token FROM users WHERE id = ?');
$userAuthStmt->execute([(int)$_SESSION['user_id']]);
$userAuthUser = $userAuthStmt->fetch();

if (!$userAuthUser) {
    if ($isApi) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    destroy_session_and_redirect('/user/login.php', 'error', 'Session expired. Please login again.');
}

if ((int)$userAuthUser['is_blocked'] === 1) {
    $_SESSION = [];
    session_destroy();
    if ($isApi) {
        json_response(['success' => false, 'message' => 'Your account is blocked.'], 403);
    }
    session_start();
    set_flash('error', 'Your account is blocked.');
    redirect('/user/login.php');
}

if (!hash_equals((string)$userAuthUser['session_token'], (string)$_SESSION['user_session_token'])) {
    $_SESSION = [];
    session_destroy();
    if ($isApi) {
        json_response(['success' => false, 'message' => 'You were logged out because your account was logged in elsewhere.'], 401);
    }
    session_start();
    set_flash('error', 'You were logged out because your account was logged in elsewhere.');
    redirect('/user/login.php');
}

$GLOBALS['user_auth_user'] = $userAuthUser;
