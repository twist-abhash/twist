<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';

close_due_auctions($pdo);

try {
    $topAuctionsStmt = $pdo->prepare("SELECT a.id, a.title, a.current_price, a.bids_count, a.status, u.full_name AS winner_name
                                      FROM auctions a
                                      LEFT JOIN users u ON u.id = a.highest_bidder_id
                                      ORDER BY a.current_price DESC, a.bids_count DESC
                                      LIMIT 10");
    $topAuctionsStmt->execute();
    $topAuctions = $topAuctionsStmt->fetchAll();

    $recentOrdersStmt = $pdo->prepare("SELECT o.id, o.winning_amount, o.status, o.created_at, a.title, u.full_name
                                       FROM orders o
                                       INNER JOIN auctions a ON a.id = o.auction_id
                                       INNER JOIN users u ON u.id = o.user_id
                                       ORDER BY o.created_at DESC
                                       LIMIT 10");
    $recentOrdersStmt->execute();
    $recentOrders = $recentOrdersStmt->fetchAll();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bids');
    $stmt->execute();
    $totalBids = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders');
    $stmt->execute();
    $totalOrders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT IFNULL(SUM(winning_amount),0) FROM orders');
    $stmt->execute();
    $totalRevenue = (float)$stmt->fetchColumn();

    $summary = [
        'total_bids' => $totalBids,
        'total_orders' => $totalOrders,
        'total_revenue' => $totalRevenue,
    ];
} catch (Throwable $e) {
    app_log('REPORTS ERROR: ' . $e->getMessage());
    $topAuctions = [];
    $recentOrders = [];
    $summary = ['total_bids' => 0, 'total_orders' => 0, 'total_revenue' => 0.0];
}

$pageTitle = 'Reports';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<h1>Reports</h1>
<div class="stats-grid">
    <div class="stat-card"><h3>Total Bids</h3><p><?php echo $summary['total_bids']; ?></p></div>
    <div class="stat-card"><h3>Total Orders</h3><p><?php echo $summary['total_orders']; ?></p></div>
    <div class="stat-card"><h3>Total Confirmed Amount</h3><p>NPR <?php echo format_money($summary['total_revenue']); ?></p></div>
</div>

<div class="card">
    <h2>Top Auctions</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Status</th>
                <th>Bids</th>
                <th>Current Price</th>
                <th>Winner</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($topAuctions as $item): ?>
                <tr>
                    <td><?php echo (int)$item['id']; ?></td>
                    <td><?php echo e($item['title']); ?></td>
                    <td><?php echo e($item['status']); ?></td>
                    <td><?php echo (int)$item['bids_count']; ?></td>
                    <td>NPR <?php echo format_money((float)$item['current_price']); ?></td>
                    <td><?php echo e($item['winner_name'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$topAuctions): ?>
                <tr><td colspan="6">No auction data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Recent Orders</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Auction</th>
                <th>User</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><?php echo (int)$order['id']; ?></td>
                    <td><?php echo e($order['title']); ?></td>
                    <td><?php echo e($order['full_name']); ?></td>
                    <td>NPR <?php echo format_money((float)$order['winning_amount']); ?></td>
                    <td><?php echo e($order['status']); ?></td>
                    <td><?php echo e(format_dt($order['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?>
                <tr><td colspan="6">No orders found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
