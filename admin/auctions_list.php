<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';

close_due_auctions($pdo);

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = 'SELECT a.*, c.name AS category_name, u.full_name AS highest_bidder_name
        FROM auctions a
        LEFT JOIN categories c ON c.id = a.category_id
        LEFT JOIN users u ON u.id = a.highest_bidder_id
        WHERE 1=1';
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['Scheduled', 'Live', 'Ended', 'Cancelled'], true)) {
    $sql .= ' AND a.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND a.title LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY a.created_at DESC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $auctions = $stmt->fetchAll();
} catch (Throwable $e) {
    app_log('AUCTIONS LIST ERROR: ' . $e->getMessage());
    $auctions = [];
}

$pageTitle = 'Manage Auctions';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions">
    <div>
        <p class="eyebrow">Abhash Bids Admin</p>
        <h1>Auctions</h1>
    </div>
    <a class="btn-link" href="/admin/auctions_create.php">Create Auction</a>
</div>

<form method="get" class="inline-form">
    <input type="text" name="search" placeholder="Search by title" value="<?php echo e($search); ?>">
    <select name="status">
        <option value="">All Statuses</option>
        <?php foreach (['Scheduled', 'Live', 'Ended', 'Cancelled'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>

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
            <th>Increment</th>
            <th>Highest Bidder</th>
            <th>Start</th>
            <th>End</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($auctions as $auction): ?>
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
                <td>NPR <?php echo format_money((float)$auction['bid_increment']); ?></td>
                <td><?php echo e($auction['highest_bidder_name'] ?? '-'); ?></td>
                <td><?php echo e(format_dt($auction['start_time'])); ?></td>
                <td><?php echo e(format_dt($auction['end_time'])); ?></td>
                <td class="table-actions">
                    <a class="action-link" href="/admin/auctions_edit.php?id=<?php echo (int)$auction['id']; ?>">Edit</a>
                    <?php if (!empty($auction['image_path'])): ?>
                        <a class="action-link" href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">View Image</a>
                    <?php endif; ?>
                    <?php if ($auction['status'] !== 'Cancelled' && $auction['status'] !== 'Ended'): ?>
                        <a class="action-link action-danger" href="/admin/auctions_cancel.php?id=<?php echo (int)$auction['id']; ?>" onclick="return confirm('Cancel this auction?')">Cancel</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$auctions): ?>
            <tr><td colspan="11">No auctions found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
