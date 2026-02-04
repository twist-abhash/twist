<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

close_due_auctions($pdo);

$counts = [
    'users' => 0,
    'auctions' => 0,
    'live' => 0,
    'ended' => 0,
    'blocked_users' => 0,
];

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    $counts['users'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM auctions');
    $stmt->execute();
    $counts['auctions'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auctions WHERE status = 'Live'");
    $stmt->execute();
    $counts['live'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auctions WHERE status = 'Ended'");
    $stmt->execute();
    $counts['ended'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE is_blocked = 1');
    $stmt->execute();
    $counts['blocked_users'] = (int)$stmt->fetchColumn();

    $recentStmt = $pdo->prepare('SELECT a.id, a.title, a.status, a.current_price, a.end_time, a.image_path, c.name AS category_name FROM auctions a LEFT JOIN categories c ON c.id = a.category_id ORDER BY a.created_at DESC LIMIT 10');
    $recentStmt->execute();
    $recentAuctions = $recentStmt->fetchAll();
} catch (Throwable $e) {
    app_log('ADMIN DASHBOARD ERROR: ' . $e->getMessage());
    $recentAuctions = [];
}

$pageTitle = 'Admin Dashboard';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<p class="eyebrow">Abhash Bids Control Center</p>
<h1>Admin Dashboard</h1>
<div class="stats-grid">
    <div class="stat-card"><h3>Total Users</h3><p><?php echo $counts['users']; ?></p></div>
    <div class="stat-card"><h3>Blocked Users</h3><p><?php echo $counts['blocked_users']; ?></p></div>
    <div class="stat-card"><h3>Total Auctions</h3><p><?php echo $counts['auctions']; ?></p></div>
    <div class="stat-card"><h3>Live Auctions</h3><p><?php echo $counts['live']; ?></p></div>
    <div class="stat-card"><h3>Ended Auctions</h3><p><?php echo $counts['ended']; ?></p></div>
</div>

<div class="card">
    <h2>Recent Auctions</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Current Price</th>
                    <th>End Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAuctions as $auction): ?>
                    <tr>
                        <td><?php echo (int)$auction['id']; ?></td>
                        <td>
                            <?php if (!empty($auction['image_path'])): ?>
                                <a href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo e($auction['image_path']); ?>" alt="Auction image" class="table-thumb">
                                </a>
                            <?php else: ?>
                                <span class="muted-text">No image</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($auction['title']); ?></td>
                        <td><?php echo e($auction['category_name'] ?? 'N/A'); ?></td>
                        <td><span class="status status-<?php echo strtolower($auction['status']); ?>"><?php echo e($auction['status']); ?></span></td>
                        <td>NPR <?php echo format_money((float)$auction['current_price']); ?></td>
                        <td><?php echo e(format_dt($auction['end_time'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentAuctions): ?>
                    <tr><td colspan="7">No auctions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
