<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';

close_due_auctions($pdo);

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bids WHERE bidder_id = ?');
    $stmt->execute([$userId]);
    $myBidsCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $stmt->execute([$userId]);
    $myOrdersCount = (int)$stmt->fetchColumn();

    $liveStmt = $pdo->prepare("SELECT a.id, a.title, a.image_path, a.current_price, a.bid_increment, a.end_time, c.name AS category_name
                               FROM auctions a
                               LEFT JOIN categories c ON c.id = a.category_id
                               WHERE a.status = 'Live' AND NOW() BETWEEN a.start_time AND a.end_time
                               ORDER BY a.end_time ASC
                               LIMIT 8");
    $liveStmt->execute();
    $liveAuctions = $liveStmt->fetchAll();
} catch (Throwable $e) {
    app_log('USER DASHBOARD ERROR: ' . $e->getMessage());
    $myBidsCount = 0;
    $myOrdersCount = 0;
    $liveAuctions = [];
}

$pageTitle = 'User Dashboard';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<p class="eyebrow">Welcome to Abhash Bids</p>
<h1>Welcome, <?php echo e($_SESSION['user_name']); ?></h1>
<div class="stats-grid">
    <div class="stat-card"><h3>My Bids</h3><p><?php echo $myBidsCount; ?></p></div>
    <div class="stat-card"><h3>My Orders</h3><p><?php echo $myOrdersCount; ?></p></div>
</div>

<div class="page-actions">
    <h2>Live Auctions</h2>
    <a class="btn-link" href="/user/auctions_live.php">View All</a>
</div>
<div class="auction-grid">
    <?php foreach ($liveAuctions as $auction): ?>
        <div class="auction-card">
            <?php if (!empty($auction['image_path'])): ?>
                <a href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo e($auction['image_path']); ?>" alt="Auction image" class="auction-thumb">
                </a>
            <?php else: ?>
                <div class="auction-thumb auction-thumb-empty">No image</div>
            <?php endif; ?>
            <h3><?php echo e($auction['title']); ?></h3>
            <p><strong>Category:</strong> <?php echo e($auction['category_name'] ?? 'N/A'); ?></p>
            <p><strong>Current Price:</strong> NPR <?php echo format_money((float)$auction['current_price']); ?></p>
            <p><strong>Ends:</strong> <?php echo e(format_dt($auction['end_time'])); ?></p>
            <a class="btn-link" href="/user/auction_view.php?id=<?php echo (int)$auction['id']; ?>">Bid Now</a>
        </div>
    <?php endforeach; ?>
    <?php if (!$liveAuctions): ?>
        <p class="empty-state">No live auctions right now.</p>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
