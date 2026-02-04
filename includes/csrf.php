<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_validate(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void
{
    if (!csrf_validate()) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}
