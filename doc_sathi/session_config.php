<?php
// Isolate Doc Sathi from other localhost PHP apps and from other Doc Sathi roles.

function doc_sathi_cookie_path()
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pathParts = explode('/', trim($scriptName, '/'));

    return isset($pathParts[0]) && $pathParts[0] !== '' ? '/' . $pathParts[0] : '/';
}

function doc_sathi_role_from_path()
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if (strpos($scriptName, '/admin/') !== false) {
        return 'a';
    }

    if (strpos($scriptName, '/doctor/') !== false) {
        return 'd';
    }

    if (strpos($scriptName, '/patient/') !== false) {
        return 'p';
    }

    return null;
}

function doc_sathi_session_name_for_role($role = null)
{
    switch ($role) {
        case 'a':
            return 'DOC_SATHI_ADMIN_SESSID';
        case 'd':
            return 'DOC_SATHI_DOCTOR_SESSID';
        case 'p':
            return 'DOC_SATHI_PATIENT_SESSID';
        default:
            return 'DOC_SATHI_PUBLIC_SESSID';
    }
}

function doc_sathi_session_cookie_options()
{
    return [
        'lifetime' => 0,
        'path' => doc_sathi_cookie_path(),
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function doc_sathi_configure_session($role = null)
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $role = $role ?? doc_sathi_role_from_path();

    session_name(doc_sathi_session_name_for_role($role));
    session_set_cookie_params(doc_sathi_session_cookie_options());
}

function doc_sathi_start_session($role = null)
{
    doc_sathi_configure_session($role);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function doc_sathi_expire_session_cookie($role = null)
{
    $options = doc_sathi_session_cookie_options();
    $options['expires'] = time() - 86400;

    unset($options['lifetime']);

    setcookie(doc_sathi_session_name_for_role($role), '', $options);
}

function doc_sathi_expire_legacy_session_cookies()
{
    if (!isset($_COOKIE['DOC_SATHI_SESSID'])) {
        return;
    }

    $options = doc_sathi_session_cookie_options();
    $options['expires'] = time() - 86400;

    unset($options['lifetime']);

    setcookie('DOC_SATHI_SESSID', '', $options);
}

doc_sathi_configure_session();
doc_sathi_expire_legacy_session_cookies();
?>
