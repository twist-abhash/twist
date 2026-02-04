<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$auctionId = (int)($_GET['auction_id'] ?? $_POST['auction_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($auctionId <= 0) {
    set_flash('error', 'Invalid auction ID.');
    redirect('/user/my_orders.php');
}

close_auction_if_due($pdo, $auctionId);
sync_auction_status($pdo, $auctionId);

try {
    $stmt = $pdo->prepare('SELECT * FROM auctions WHERE id = ? LIMIT 1');
    $stmt->execute([$auctionId]);
    $auction = $stmt->fetch();

    if (!$auction) {
        set_flash('error', 'Auction not found.');
        redirect('/user/my_orders.php');
    }

    if ($auction['status'] !== 'Ended') {
        set_flash('error', 'Checkout is available only after auction ends.');
        redirect('/user/auction_view.php?id=' . $auctionId);
    }

    if ((int)$auction['highest_bidder_id'] !== $userId) {
        set_flash('error', 'Only the winner can checkout this auction.');
        redirect('/user/auction_view.php?id=' . $auctionId);
    }

    $existingStmt = $pdo->prepare('SELECT id, status, created_at FROM orders WHERE auction_id = ? AND user_id = ? LIMIT 1');
    $existingStmt->execute([$auctionId, $userId]);
    $existingOrder = $existingStmt->fetch();
} catch (Throwable $e) {
    app_log('CHECKOUT LOAD ERROR: ' . $e->getMessage());
    set_flash('error', 'Could not load checkout.');
    redirect('/user/my_orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($existingOrder) {
        set_flash('error', 'Order already confirmed for this auction.');
        redirect('/user/my_orders.php');
    }

    try {
        $pdo->beginTransaction();

        $lockAuction = $pdo->prepare('SELECT id, status, highest_bidder_id, current_price FROM auctions WHERE id = ? FOR UPDATE');
        $lockAuction->execute([$auctionId]);
        $lockedAuction = $lockAuction->fetch();

        if (!$lockedAuction || $lockedAuction['status'] !== 'Ended' || (int)$lockedAuction['highest_bidder_id'] !== $userId) {
            $pdo->rollBack();
            set_flash('error', 'Auction is not eligible for checkout.');
            redirect('/user/auction_view.php?id=' . $auctionId);
        }

        $lockOrder = $pdo->prepare('SELECT id FROM orders WHERE auction_id = ? AND user_id = ? FOR UPDATE');
        $lockOrder->execute([$auctionId, $userId]);
        if ($lockOrder->fetch()) {
            $pdo->rollBack();
            set_flash('error', 'Order already exists for this auction.');
            redirect('/user/my_orders.php');
        }

        $insert = $pdo->prepare("INSERT INTO orders (auction_id, user_id, winning_amount, status, created_at) VALUES (?, ?, ?, 'Confirmed', NOW())");
        $insert->execute([$auctionId, $userId, (float)$lockedAuction['current_price']]);

        $pdo->commit();

        set_flash('success', 'Order confirmed successfully.');
        redirect('/user/my_orders.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('CHECKOUT CONFIRM ERROR: ' . $e->getMessage());
        set_flash('error', 'Could not confirm order.');
        redirect('/user/checkout.php?auction_id=' . $auctionId);
    }
}

$pageTitle = 'Checkout';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <h1>Checkout</h1>
    <?php if (!empty($auction['image_path'])): ?>
        <a href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">
            <img src="<?php echo e($auction['image_path']); ?>" alt="Auction image" class="detail-image">
        </a>
    <?php else: ?>
        <div class="detail-image detail-image-empty">No image available</div>
    <?php endif; ?>
    <p><strong>Auction:</strong> <?php echo e($auction['title']); ?></p>
    <p><strong>Winning Amount:</strong> NPR <?php echo format_money((float)$auction['current_price']); ?></p>
    <p><strong>Status:</strong> <?php echo e($auction['status']); ?></p>

    <?php if ($existingOrder): ?>
        <div class="alert alert-success">Order already confirmed on <?php echo e(format_dt($existingOrder['created_at'])); ?>.</div>
        <a class="btn-link" href="/user/my_orders.php">Go to My Orders</a>
    <?php else: ?>
        <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="auction_id" value="<?php echo (int)$auction['id']; ?>">
            <button type="submit">Confirm Order</button>
        </form>
    <?php endif; ?>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
