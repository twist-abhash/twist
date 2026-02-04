<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('SELECT id, full_name, phone, email, password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        destroy_session_and_redirect('/user/login.php', 'error', 'Account not found.');
    }
} catch (Throwable $e) {
    app_log('PROFILE LOAD ERROR: ' . $e->getMessage());
    set_flash('error', 'Could not load profile.');
    redirect('/user/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!require_contact($phone, $email)) {
            $errors[] = 'At least one contact method (phone or email) is required.';
        }
        if (!is_valid_phone($phone)) {
            $errors[] = 'Phone must be exactly 10 digits.';
        }
        if (!is_valid_email($email)) {
            $errors[] = 'Invalid email format.';
        }

        if (!$errors) {
            try {
                if ($email !== '') {
                    $q = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
                    $q->execute([$email, $userId]);
                    if ($q->fetch()) {
                        $errors[] = 'Email already used by another account.';
                    }
                }

                if ($phone !== '') {
                    $q = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1');
                    $q->execute([$phone, $userId]);
                    if ($q->fetch()) {
                        $errors[] = 'Phone already used by another account.';
                    }
                }

                if (!$errors) {
                    $update = $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?');
                    $update->execute([
                        $fullName,
                        $phone !== '' ? $phone : null,
                        $email !== '' ? $email : null,
                        $userId,
                    ]);

                    $_SESSION['user_name'] = $fullName;
                    set_flash('success', 'Profile updated successfully.');
                    redirect('/user/profile.php');
                }
            } catch (Throwable $e) {
                app_log('PROFILE UPDATE ERROR: ' . $e->getMessage());
                $errors[] = 'Could not update profile.';
            }
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('/user/profile.php');
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        $errors = [];

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect('/user/profile.php');
        }

        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(32));
            $update = $pdo->prepare('UPDATE users SET password_hash = ?, session_token = ? WHERE id = ?');
            $update->execute([$hash, $token, $userId]);

            $_SESSION['user_session_token'] = $token;

            set_flash('success', 'Password changed successfully.');
            redirect('/user/profile.php');
        } catch (Throwable $e) {
            app_log('CHANGE PASSWORD ERROR: ' . $e->getMessage());
            set_flash('error', 'Could not change password.');
            redirect('/user/profile.php');
        }
    }
}

$pageTitle = 'Profile';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<h1>My Profile</h1>

<section class="card form-card">
    <h2>Edit Profile</h2>
    <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="update_profile">

        <label>Full Name</label>
        <input type="text" name="full_name" required value="<?php echo e($user['full_name']); ?>">

        <label>Phone (optional, 10 digits)</label>
        <input type="text" name="phone" maxlength="10" value="<?php echo e($user['phone'] ?? ''); ?>">

        <label>Email (optional)</label>
        <input type="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>">

        <button type="submit">Update Profile</button>
    </form>
</section>

<section class="card form-card">
    <h2>Change Password</h2>
    <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="change_password">

        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <button type="submit">Change Password</button>
    </form>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
