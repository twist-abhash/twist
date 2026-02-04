<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid category ID.');
    redirect('/admin/categories_list.php');
}

$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = ?');
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    redirect('/admin/categories_list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        set_flash('error', 'Category name is required.');
        redirect('/admin/categories_edit.php?id=' . $id);
    }

    try {
        $update = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
        $update->execute([$name, $id]);

        admin_log($pdo, (int)$_SESSION['admin_id'], 'UPDATE_CATEGORY', 'Updated category #' . $id . ' to: ' . $name);

        set_flash('success', 'Category updated successfully.');
        redirect('/admin/categories_list.php');
    } catch (Throwable $e) {
        app_log('CATEGORY EDIT ERROR: ' . $e->getMessage());
        set_flash('error', 'Could not update category. Name may already exist.');
        redirect('/admin/categories_edit.php?id=' . $id);
    }
}

$pageTitle = 'Edit Category';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <h1>Edit Category</h1>
    <form method="post">
        <?php echo csrf_input(); ?>
        <label>Name</label>
        <input type="text" name="name" required value="<?php echo e($category['name']); ?>">
        <button type="submit">Update</button>
    </form>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
