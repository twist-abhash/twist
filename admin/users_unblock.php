<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid user ID.');
    redirect('/admin/users_list.php');
}

$stmt = $pdo->prepare('SELECT id, full_name, is_blocked FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    set_flash('error', 'User not found.');
    redirect('/admin/users_list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    try {
        $update = $pdo->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?');
        $update->execute([$id]);

        admin_log($pdo, (int)$_SESSION['admin_id'], 'UNBLOCK_USER', 'Unblocked user #' . $id);

        set_flash('success', 'User unblocked successfully.');
        redirect('/admin/users_list.php');
    } catch (Throwable $e) {
        app_log('UNBLOCK USER ERROR: ' . $e->getMessage());
        set_flash('error', 'Could not unblock user.');
        redirect('/admin/users_list.php');
    }
}

$pageTitle = 'Unblock User';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <h1>Unblock User</h1>
    <p>Are you sure you want to unblock <strong><?php echo e($user['full_name']); ?></strong>?</p>
    <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
        <button type="submit">Yes, Unblock User</button>
        <a class="btn-link" href="/admin/users_list.php">Back</a>
    </form>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
