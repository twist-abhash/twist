<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT o.id, o.winning_amount, o.status, o.created_at, a.id AS auction_id, a.title, a.image_path
                           FROM orders o
                           INNER JOIN auctions a ON a.id = o.auction_id
                           WHERE o.user_id = ?
                           ORDER BY o.created_at DESC");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    app_log('MY ORDERS ERROR: ' . $e->getMessage());
    $orders = [];
}

$pageTitle = 'My Orders';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<h1>My Orders</h1>
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Order ID</th>
            <th>Image</th>
            <th>Auction</th>
            <th>Winning Amount</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?php echo (int)$order['id']; ?></td>
                <td>
                    <?php if (!empty($order['image_path'])): ?>
                        <a href="<?php echo e($order['image_path']); ?>" target="_blank" rel="noopener">
                            <img src="<?php echo e($order['image_path']); ?>" alt="Auction image" class="table-thumb">
                        </a>
                    <?php else: ?>
                        <span class="muted-text">No image</span>
                    <?php endif; ?>
                </td>
                <td><?php echo e($order['title']); ?></td>
                <td>NPR <?php echo format_money((float)$order['winning_amount']); ?></td>
                <td><?php echo e($order['status']); ?></td>
                <td><?php echo e(format_dt($order['created_at'])); ?></td>
                <td><a class="action-link" href="/user/auction_view.php?id=<?php echo (int)$order['auction_id']; ?>">Auction</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
            <tr><td colspan="7">No orders found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
