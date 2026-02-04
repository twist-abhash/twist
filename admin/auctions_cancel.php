<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid auction ID.');
    redirect('/admin/auctions_list.php');
}

$stmt = $pdo->prepare('SELECT id, title, status FROM auctions WHERE id = ?');
$stmt->execute([$id]);
$auction = $stmt->fetch();
if (!$auction) {
    set_flash('error', 'Auction not found.');
    redirect('/admin/auctions_list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($auction['status'] === 'Cancelled') {
        set_flash('error', 'Auction is already cancelled.');
        redirect('/admin/auctions_list.php');
    }

    try {
        $update = $pdo->prepare("UPDATE auctions SET status = 'Cancelled' WHERE id = ?");
        $update->execute([$id]);

        admin_log($pdo, (int)$_SESSION['admin_id'], 'CANCEL_AUCTION', 'Cancelled auction #' . $id);

        set_flash('success', 'Auction cancelled successfully.');
        redirect('/admin/auctions_list.php');
    } catch (Throwable $e) {
        app_log('AUCTION CANCEL ERROR: ' . $e->getMessage());
        set_flash('error', 'Could not cancel auction.');
        redirect('/admin/auctions_list.php');
    }
}

$pageTitle = 'Cancel Auction';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <h1>Cancel Auction</h1>
    <p>Are you sure you want to cancel: <strong><?php echo e($auction['title']); ?></strong>?</p>
    <form method="post">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$auction['id']; ?>">
        <button type="submit" class="btn-danger">Yes, Cancel Auction</button>
        <a class="btn-link" href="/admin/auctions_list.php">Back</a>
    </form>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
