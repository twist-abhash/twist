<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_user.php';

close_due_auctions($pdo);

$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);

try {
    $catStmt = $pdo->prepare('SELECT id, name FROM categories ORDER BY name');
    $catStmt->execute();
    $categories = $catStmt->fetchAll();

    $sql = "SELECT a.id, a.title, a.description, a.image_path, a.current_price, a.starting_price, a.bid_increment, a.end_time, c.name AS category_name
            FROM auctions a
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'Live' AND NOW() BETWEEN a.start_time AND a.end_time";
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (a.title LIKE ? OR a.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($categoryId > 0) {
        $sql .= ' AND a.category_id = ?';
        $params[] = $categoryId;
    }

    $sql .= ' ORDER BY a.end_time ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $auctions = $stmt->fetchAll();
} catch (Throwable $e) {
    app_log('LIVE AUCTIONS ERROR: ' . $e->getMessage());
    $categories = [];
    $auctions = [];
}

$pageTitle = 'Live Auctions';
$portal = 'user';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<p class="eyebrow">Abhash Bids</p>
<h1>Live Auctions</h1>
<p class="muted-text">Browse active lots, open each listing, and place bids in real time.</p>
<form method="get" class="inline-form">
    <input type="text" name="search" placeholder="Search auctions" value="<?php echo e($search); ?>">
    <select name="category_id">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?php echo (int)$category['id']; ?>" <?php echo $categoryId === (int)$category['id'] ? 'selected' : ''; ?>>
                <?php echo e($category['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>

<div class="auction-grid">
    <?php foreach ($auctions as $auction): ?>
        <article class="auction-card">
            <?php if (!empty($auction['image_path'])): ?>
                <a href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo e($auction['image_path']); ?>" alt="Auction image" class="auction-thumb">
                </a>
            <?php else: ?>
                <div class="auction-thumb auction-thumb-empty">No image</div>
            <?php endif; ?>
            <h3><?php echo e($auction['title']); ?></h3>
            <p><?php echo e($auction['description']); ?></p>
            <p><strong>Category:</strong> <?php echo e($auction['category_name'] ?? 'N/A'); ?></p>
            <p><strong>Current Price:</strong> NPR <?php echo format_money((float)$auction['current_price']); ?></p>
            <p><strong>Minimum Increment:</strong> NPR <?php echo format_money((float)$auction['bid_increment']); ?></p>
            <p><strong>Ends:</strong> <?php echo e(format_dt($auction['end_time'])); ?></p>
            <a class="btn-link" href="/user/auction_view.php?id=<?php echo (int)$auction['id']; ?>">View & Bid</a>
        </article>
    <?php endforeach; ?>
    <?php if (!$auctions): ?>
        <p class="empty-state">No live auctions available.</p>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
