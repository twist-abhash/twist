<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT b.id, b.bid_amount, b.created_at, a.id AS auction_id, a.title, a.image_path, a.status, a.current_price
                           FROM bids b
                           INNER JOIN auctions a ON a.id = b.auction_id
                           WHERE b.bidder_id = ?
                           ORDER BY b.created_at DESC");
    $stmt->execute([$userId]);
    $bids = $stmt->fetchAll();
} catch (Throwable $e) {
    app_log('MY BIDS ERROR: ' . $e->getMessage());
    $bids = [];
}

$pageTitle = 'My Bids';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<h1>My Bids</h1>
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Bid ID</th>
            <th>Image</th>
            <th>Auction</th>
            <th>My Bid</th>
            <th>Auction Current Price</th>
            <th>Status</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bids as $bid): ?>
            <tr>
                <td><?php echo (int)$bid['id']; ?></td>
                <td>
                    <?php if (!empty($bid['image_path'])): ?>
                        <a href="<?php echo e($bid['image_path']); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo e($bid['image_path']); ?>" alt="Auction image" class="table-thumb">
                        </a>
                    <?php else: ?>
                        <span class="muted-text">No image</span>
                    <?php endif; ?>
                </td>
                <td><?php echo e($bid['title']); ?></td>
                <td>NPR <?php echo format_money((float)$bid['bid_amount']); ?></td>
                <td>NPR <?php echo format_money((float)$bid['current_price']); ?></td>
                <td><?php echo e($bid['status']); ?></td>
                <td><?php echo e(format_dt($bid['created_at'])); ?></td>
                <td><a class="action-link" href="/user/auction_view.php?id=<?php echo (int)$bid['auction_id']; ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$bids): ?>
            <tr><td colspan="8">You have not placed any bids yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
