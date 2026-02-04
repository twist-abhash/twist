<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}

$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        set_flash('error', 'Username and password are required.');
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                set_flash('success', 'Welcome admin.');
                redirect('/admin/dashboard.php');
            }

            set_flash('error', 'Invalid login credentials.');
        } catch (Throwable $e) {
            app_log('ADMIN LOGIN ERROR: ' . $e->getMessage());
            set_flash('error', 'Something went wrong.');
        }
    }

    redirect('/admin/login.php');
}

$pageTitle = 'Admin Login';
$portal = 'public';
$pageBodyClass = 'page-login-centered';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-card auth-card-login">
    <h1>Admin Login</h1>
    <form method="post">
        <?php echo csrf_input(); ?>
        <label>Username</label>
        <input type="text" name="username" value="<?php echo e($username); ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
