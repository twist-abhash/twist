<?php
require_once __DIR__ . "/session_config.php";
require_once __DIR__ . "/algorithms.php";

date_default_timezone_set('Asia/Kathmandu');
$date = date('Y-m-d');

include("connection.php");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$email = '';
$dashboardByType = [
    'a' => 'admin/index.php',
    'p' => 'patient/index.php',
    'd' => 'doctor/index.php',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!doc_sathi_email_is_valid($email)) {
        $error = doc_sathi_email_policy_message();
    } else {
        try {
            $authResult = doc_sathi_authenticate_user($database, $email, $password);

            if ($authResult['ok']) {
                $utype = $authResult['usertype'];

                doc_sathi_start_session($utype);
                session_regenerate_id(true);
                $_SESSION['user'] = $email;
                $_SESSION['usertype'] = $utype;
                $_SESSION['date'] = $date;

                header('Location: ' . $dashboardByType[$utype]);
                exit;
            }

            $error = (string)$authResult['message'];
        } catch (Throwable $exception) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/auth-flow.css">
    <link rel="stylesheet" href="css/login.css">
    <title>Doc Sathi | Login</title>
</head>
<body class="auth-page login-page">
    <main class="auth-shell login-shell">
        <section class="auth-brand-panel login-brand-panel">
            <a href="index.html" class="auth-logo login-logo">
                <img src="img/logo1.png" alt="Doc Sathi logo">
            </a>
            <p class="eyebrow">Secure Access</p>
            <h1>Sign in to Doc Sathi.</h1>
            <p class="brand-copy">
                Access your patient, doctor, or admin workspace with the account you already registered.
            </p>

            <div class="login-role-pills" aria-label="Account types">
                <span>Patients</span>
                <span>Doctors</span>
                <span>Admin</span>
            </div>

            <div class="login-highlights">
                <div class="login-highlight">
                    <strong>Appointments and schedules</strong>
                    <span>Review bookings, session availability, and day-to-day updates from one place.</span>
                </div>
                <div class="login-highlight">
                    <strong>Verification and account access</strong>
                    <span>Track approval status, manage profile details, and return to your workspace quickly.</span>
                </div>
            </div>
        </section>

        <section class="auth-card login-card">
            <p class="eyebrow">Account Login</p>
            <h2>Welcome back</h2>
            <p class="auth-subtext">Use the email and password linked to your Doc Sathi account.</p>

            <?php if ($error !== ''): ?>
                <div class="auth-alert error" aria-live="polite">
                    <?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="auth-form">
                <div class="form-grid login-form-grid">
                    <div class="field field-full">
                        <label for="email">Email Address</label>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            value="<?php echo h($email); ?>"
                            placeholder="name@example.com"
                            autocomplete="email"
                            inputmode="email"
                            autofocus
                            required
                        >
                    </div>

                    <div class="field field-full">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >
                            <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password" aria-controls="password" title="Show password">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="primary-action">Log In</button>
            </form>

            <p class="login-divider"><span>New to Doc Sathi?</span></p>
            <p class="auth-link-row">Create your account in the role that fits you. <a href="register.php">Choose registration</a></p>
        </section>
    </main>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) {
                return;
            }

            button.addEventListener('click', function() {
                const shouldShow = input.type === 'password';
                input.type = shouldShow ? 'text' : 'password';
                button.classList.toggle('is-visible', shouldShow);
                button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
                button.setAttribute('title', shouldShow ? 'Hide password' : 'Show password');
            });
        });
    </script>
</body>
</html>
