<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';

$search = trim($_GET['search'] ?? '');

try {
    if ($search !== '') {
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE name LIKE ? ORDER BY name ASC');
        $stmt->execute(['%' . $search . '%']);
        $categories = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT id, name FROM categories ORDER BY name ASC');
        $stmt->execute();
        $categories = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    app_log('CATEGORIES LIST ERROR: ' . $e->getMessage());
    $categories = [];
}

$pageTitle = 'Manage Categories';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions">
    <h1>Categories</h1>
    <a class="btn-link" href="/admin/categories_create.php">Add Category</a>
</div>

<form method="get" class="inline-form">
    <input type="text" name="search" placeholder="Search categories" value="<?php echo e($search); ?>">
    <button type="submit">Search</button>
</form>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td><?php echo (int)$category['id']; ?></td>
                <td><?php echo e($category['name']); ?></td>
                <td class="table-actions">
                    <a class="action-link" href="/admin/categories_edit.php?id=<?php echo (int)$category['id']; ?>">Edit</a>
                    <a class="action-link action-danger" href="/admin/categories_delete.php?id=<?php echo (int)$category['id']; ?>" onclick="return confirm('Delete this category?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?>
            <tr><td colspan="3">No categories found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
