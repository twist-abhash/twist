<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid category ID.');
    redirect('/admin/categories_list.php');
}

try {
    $check = $pdo->prepare('SELECT COUNT(*) FROM auctions WHERE category_id = ?');
    $check->execute([$id]);
    if ((int)$check->fetchColumn() > 0) {
        set_flash('error', 'Cannot delete category with auctions.');
        redirect('/admin/categories_list.php');
    }

    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->execute([$id]);

    admin_log($pdo, (int)$_SESSION['admin_id'], 'DELETE_CATEGORY', 'Deleted category #' . $id);

    set_flash('success', 'Category deleted successfully.');
} catch (Throwable $e) {
    app_log('CATEGORY DELETE ERROR: ' . $e->getMessage());
    set_flash('error', 'Could not delete category.');
}

redirect('/admin/categories_list.php');
