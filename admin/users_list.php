<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';

$search = trim($_GET['search'] ?? '');

try {
    if ($search !== '') {
        $stmt = $pdo->prepare('SELECT id, full_name, phone, email, is_blocked, created_at FROM users WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY created_at DESC');
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->prepare('SELECT id, full_name, phone, email, is_blocked, created_at FROM users ORDER BY created_at DESC');
        $stmt->execute();
    }
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    app_log('USERS LIST ERROR: ' . $e->getMessage());
    $users = [];
}

$pageTitle = 'Manage Users';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<h1>Users</h1>
<form method="get" class="inline-form">
    <input type="text" name="search" placeholder="Search by name/phone/email" value="<?php echo e($search); ?>">
    <button type="submit">Search</button>
</form>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo (int)$user['id']; ?></td>
                <td><?php echo e($user['full_name']); ?></td>
                <td><?php echo e($user['phone'] ?? '-'); ?></td>
                <td><?php echo e($user['email'] ?? '-'); ?></td>
                <td><?php echo (int)$user['is_blocked'] === 1 ? 'Blocked' : 'Active'; ?></td>
                <td><?php echo e(format_dt($user['created_at'])); ?></td>
                <td>
                    <?php if ((int)$user['is_blocked'] === 1): ?>
                        <a href="/admin/users_unblock.php?id=<?php echo (int)$user['id']; ?>">Unblock</a>
                    <?php else: ?>
                        <a href="/admin/users_block.php?id=<?php echo (int)$user['id']; ?>">Block</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
            <tr><td colspan="7">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
