<?php
declare(strict_types=1);

define('API_REQUEST', true);

require_once dirname(__DIR__) . '/includes/auth_user.php';

$auctionId = (int)($_GET['auction_id'] ?? 0);
if ($auctionId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid auction ID.'], 400);
}

close_auction_if_due($pdo, $auctionId);
sync_auction_status($pdo, $auctionId);

try {
    $stmt = $pdo->prepare("SELECT b.bid_amount, b.created_at, u.full_name AS bidder_name
                           FROM bids b
                           INNER JOIN users u ON u.id = b.bidder_id
                           WHERE b.auction_id = ?
                           ORDER BY b.id DESC
                           LIMIT 20");
    $stmt->execute([$auctionId]);
    $rows = $stmt->fetchAll();

    $history = [];
    foreach ($rows as $row) {
        $history[] = [
            'bidder_name' => $row['bidder_name'],
            'bid_amount' => (float)$row['bid_amount'],
            'created_at' => $row['created_at'],
        ];
    }

    json_response([
        'success' => true,
        'history' => $history,
    ]);
} catch (Throwable $e) {
    app_log('API BID HISTORY ERROR: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to fetch bid history.'], 500);
}
