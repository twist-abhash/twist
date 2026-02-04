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
    $stmt = $pdo->prepare('SELECT a.id, a.status, a.end_time, a.start_time, a.current_price, a.starting_price, a.bid_increment, a.highest_bidder_id, a.bids_count, u.full_name AS highest_bidder_name
                           FROM auctions a
                           LEFT JOIN users u ON u.id = a.highest_bidder_id
                           WHERE a.id = ? LIMIT 1');
    $stmt->execute([$auctionId]);
    $auction = $stmt->fetch();

    if (!$auction) {
        json_response(['success' => false, 'message' => 'Auction not found.'], 404);
    }

    $minNextBid = auction_min_next_bid($auction);

    json_response([
        'success' => true,
        'status' => $auction['status'],
        'server_time' => date('Y-m-d H:i:s'),
        'end_time' => $auction['end_time'],
        'current_price' => (float)$auction['current_price'],
        'highest_bidder' => $auction['highest_bidder_name'],
        'min_next_bid' => (float)$minNextBid,
        'bids_count' => (int)$auction['bids_count'],
    ]);
} catch (Throwable $e) {
    app_log('API GET AUCTION STATE ERROR: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to fetch auction state.'], 500);
}
