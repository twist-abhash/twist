<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/user/dashboard.php');
}

$form = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $form['full_name'] = trim($_POST['full_name'] ?? '');
    $form['phone'] = trim($_POST['phone'] ?? '');
    $form['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!require_contact($form['phone'], $form['email'])) {
        $errors[] = 'At least one contact method (phone or email) is required.';
    }
    if (!is_valid_phone($form['phone'])) {
        $errors[] = 'Phone must be exactly 10 digits.';
    }
    if (!is_valid_email($form['email'])) {
        $errors[] = 'Invalid email format.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (!$errors) {
        try {
            if ($form['email'] !== '') {
                $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $emailStmt->execute([$form['email']]);
                if ($emailStmt->fetch()) {
                    $errors[] = 'Email is already registered.';
                }
            }

            if ($form['phone'] !== '') {
                $phoneStmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
                $phoneStmt->execute([$form['phone']]);
                if ($phoneStmt->fetch()) {
                    $errors[] = 'Phone is already registered.';
                }
            }

            if (!$errors) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (full_name, phone, email, password_hash, is_blocked, session_token, created_at) VALUES (?, ?, ?, ?, 0, NULL, NOW())');
                $stmt->execute([
                    $form['full_name'],
                    $form['phone'] !== '' ? $form['phone'] : null,
                    $form['email'] !== '' ? $form['email'] : null,
                    $hash,
                ]);

                set_flash('success', 'Registration successful. Please login.');
                redirect('/user/login.php');
            }
        } catch (Throwable $e) {
            app_log('USER REGISTER ERROR: ' . $e->getMessage());
            $errors[] = 'Could not register user.';
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/user/register.php');
    }
}

$pageTitle = 'User Registration';
$portal = 'public';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-card">
    <h1>User Registration</h1>
    <form method="post">
        <?php echo csrf_input(); ?>

        <label>Full Name</label>
        <input type="text" name="full_name" required value="<?php echo e($form['full_name']); ?>">

        <label>Phone (optional, 10 digits)</label>
        <input type="text" name="phone" maxlength="10" value="<?php echo e($form['phone']); ?>">

        <label>Email (optional)</label>
        <input type="email" name="email" value="<?php echo e($form['email']); ?>">

        <label>Password</label>
        <div class="password-wrap">
            <input type="password" id="password" name="password" required>
            <button type="button" class="small-btn" id="togglePassword">Show</button>
        </div>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="/user/login.php">Login</a></p>
</section>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const pwd = document.getElementById('password');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        this.textContent = 'Hide';
    } else {
        pwd.type = 'password';
        this.textContent = 'Show';
    }
});
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
