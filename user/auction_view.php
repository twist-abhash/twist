<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$auctionId = (int)($_GET['id'] ?? 0);
if ($auctionId <= 0) {
    set_flash('error', 'Invalid auction ID.');
    redirect('/user/auctions_live.php');
}

close_auction_if_due($pdo, $auctionId);
sync_auction_status($pdo, $auctionId);

try {
    $stmt = $pdo->prepare("SELECT a.*, c.name AS category_name, u.full_name AS highest_bidder_name
                           FROM auctions a
                           LEFT JOIN categories c ON c.id = a.category_id
                           LEFT JOIN users u ON u.id = a.highest_bidder_id
                           WHERE a.id = ? LIMIT 1");
    $stmt->execute([$auctionId]);
    $auction = $stmt->fetch();

    if (!$auction) {
        set_flash('error', 'Auction not found.');
        redirect('/user/auctions_live.php');
    }

    $minNextBid = auction_min_next_bid($auction);

    $orderStmt = $pdo->prepare('SELECT id FROM orders WHERE auction_id = ? AND user_id = ? LIMIT 1');
    $orderStmt->execute([$auctionId, (int)$_SESSION['user_id']]);
    $myOrder = $orderStmt->fetch();
} catch (Throwable $e) {
    app_log('AUCTION VIEW ERROR: ' . $e->getMessage());
    set_flash('error', 'Unable to load auction.');
    redirect('/user/auctions_live.php');
}

$pageTitle = 'Auction Details';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card" id="auctionApp" data-auction-id="<?php echo (int)$auction['id']; ?>" data-csrf-token="<?php echo e(csrf_token()); ?>">
    <div class="detail-top">
        <div>
            <p class="eyebrow">Live Auction</p>
            <h1><?php echo e($auction['title']); ?></h1>
            <p><?php echo e($auction['description']); ?></p>
            <p><strong>Category:</strong> <?php echo e($auction['category_name'] ?? 'N/A'); ?></p>
        </div>
        <?php if (!empty($auction['image_path'])): ?>
            <a href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">
                <img src="<?php echo e($auction['image_path']); ?>" alt="Auction image" class="detail-image">
            </a>
        <?php else: ?>
            <div class="detail-image detail-image-empty">No image available</div>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><h3>Status</h3><p id="auctionStatus"><?php echo e($auction['status']); ?></p></div>
        <div class="stat-card"><h3>Current Price</h3><p id="currentPrice">NPR <?php echo format_money((float)$auction['current_price']); ?></p></div>
        <div class="stat-card"><h3>Min Next Bid</h3><p id="minNextBid">NPR <?php echo format_money($minNextBid); ?></p></div>
        <div class="stat-card"><h3>Ends In</h3><p id="countdown" data-end-time="<?php echo e($auction['end_time']); ?>">-</p></div>
    </div>

    <div id="bidMessages"></div>

    <?php if ($auction['status'] !== 'Cancelled'): ?>
        <form id="bidForm" class="inline-form bid-form" <?php echo ((int)$GLOBALS['user_auth_user']['is_blocked'] === 1) ? 'style="display:none"' : ''; ?>>
            <input type="number" step="0.01" min="0" id="bidAmount" placeholder="Enter bid amount" required>
            <button type="submit">Place Bid</button>
        </form>
    <?php endif; ?>

    <?php if ($auction['status'] === 'Ended'): ?>
        <?php if ((int)$auction['highest_bidder_id'] === (int)$_SESSION['user_id']): ?>
            <?php if ($myOrder): ?>
                <div class="alert alert-success">You won this auction and already confirmed your order.</div>
            <?php else: ?>
                <a class="btn-link" href="/user/checkout.php?auction_id=<?php echo (int)$auction['id']; ?>">Go to Checkout</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-error">Auction ended - you did not win.</div>
        <?php endif; ?>
    <?php elseif ($auction['status'] === 'Cancelled'): ?>
        <div class="alert alert-error">This auction was cancelled by admin.</div>
    <?php endif; ?>

    <h2>Live Bid History</h2>
    <div class="table-wrap">
        <table id="bidHistoryTable">
            <thead>
            <tr>
                <th>Bidder</th>
                <th>Amount</th>
                <th>Time</th>
            </tr>
            </thead>
            <tbody id="bidHistoryBody">
                <tr><td colspan="3">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</section>
<script src="/assets/js/live_bidding.js"></script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
