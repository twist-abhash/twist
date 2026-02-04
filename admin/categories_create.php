<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        set_flash('error', 'Category name is required.');
        redirect('/admin/categories_create.php');
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);

        admin_log($pdo, (int)$_SESSION['admin_id'], 'CREATE_CATEGORY', 'Created category: ' . $name);

        set_flash('success', 'Category created successfully.');
        redirect('/admin/categories_list.php');
    } catch (Throwable $e) {
        app_log('CATEGORY CREATE ERROR: ' . $e->getMessage());
        set_flash('error', 'Could not create category. Name may already exist.');
        redirect('/admin/categories_create.php');
    }
}

$pageTitle = 'Create Category';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <h1>Create Category</h1>
    <form method="post">
        <?php echo csrf_input(); ?>
        <label>Name</label>
        <input type="text" name="name" required value="<?php echo e($name); ?>">
        <button type="submit">Save</button>
    </form>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
