<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/user/dashboard.php');
}

$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        set_flash('error', 'Phone/Email and password are required.');
        redirect('/user/login.php');
    }

    try {
        if (is_valid_email($identity) && strpos($identity, '@') !== false) {
            $stmt = $pdo->prepare('SELECT id, full_name, password_hash, is_blocked FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$identity]);
        } else {
            $stmt = $pdo->prepare('SELECT id, full_name, password_hash, is_blocked FROM users WHERE phone = ? LIMIT 1');
            $stmt->execute([$identity]);
        }

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            set_flash('error', 'Invalid login credentials.');
            redirect('/user/login.php');
        }

        if ((int)$user['is_blocked'] === 1) {
            set_flash('error', 'Your account is blocked.');
            redirect('/user/login.php');
        }

        $token = bin2hex(random_bytes(32));
        $update = $pdo->prepare('UPDATE users SET session_token = ? WHERE id = ?');
        $update->execute([$token, (int)$user['id']]);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_session_token'] = $token;

        set_flash('success', 'Welcome back.');
        redirect('/user/dashboard.php');
    } catch (Throwable $e) {
        app_log('USER LOGIN ERROR: ' . $e->getMessage());
        set_flash('error', 'Something went wrong.');
        redirect('/user/login.php');
    }
}

$pageTitle = 'User Login';
$portal = 'public';
$pageBodyClass = 'page-login-centered';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-card auth-card-login">
    <h1>User Login</h1>
    <form method="post">
        <?php echo csrf_input(); ?>

        <label>Phone or Email</label>
        <input type="text" name="identity" required value="<?php echo e($identity); ?>">

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>
    <p>New user? <a href="/user/register.php">Create account</a></p>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
