<?php
declare(strict_types=1);

define('API_REQUEST', true);

require_once dirname(__DIR__) . '/includes/auth_user.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!csrf_validate()) {
    json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
}

$userId = (int)$_SESSION['user_id'];
$auctionId = (int)($_POST['auction_id'] ?? 0);
$bidAmount = round((float)($_POST['bid_amount'] ?? 0), 2);

if ($auctionId <= 0 || $bidAmount <= 0) {
    json_response(['success' => false, 'message' => 'Invalid bid data.'], 400);
}

try {
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare('SELECT id, is_blocked, session_token FROM users WHERE id = ? FOR UPDATE');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'User not found.'], 401);
    }

    if ((int)$user['is_blocked'] === 1) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Your account is blocked.'], 403);
    }

    if (!hash_equals((string)$user['session_token'], (string)$_SESSION['user_session_token'])) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'You were logged out because your account was logged in elsewhere.'], 401);
    }

    $auctionStmt = $pdo->prepare('SELECT * FROM auctions WHERE id = ? FOR UPDATE');
    $auctionStmt->execute([$auctionId]);
    $auction = $auctionStmt->fetch();

    if (!$auction) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Auction not found.'], 404);
    }

    $now = date('Y-m-d H:i:s');
    $nowTs = strtotime($now);
    $startTs = strtotime($auction['start_time']);
    $endTs = strtotime($auction['end_time']);

    if ($auction['status'] === 'Cancelled') {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Auction is cancelled.'], 400);
    }

    if ($nowTs >= $endTs) {
        $endUpdate = $pdo->prepare("UPDATE auctions SET status = 'Ended' WHERE id = ?");
        $endUpdate->execute([$auctionId]);
        $pdo->commit();
        json_response(['success' => false, 'message' => 'Auction has ended.'], 400);
    }

    if ($nowTs < $startTs) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Auction is not live yet.'], 400);
    }

    if ($auction['status'] !== 'Live') {
        $statusUpdate = $pdo->prepare("UPDATE auctions SET status = 'Live' WHERE id = ?");
        $statusUpdate->execute([$auctionId]);
        $auction['status'] = 'Live';
    }

    $minNextBid = (int)$auction['bids_count'] === 0
        ? (float)$auction['starting_price']
        : (float)$auction['current_price'] + (float)$auction['bid_increment'];

    if ($bidAmount + 0.00001 < $minNextBid) {
        $pdo->rollBack();
        json_response([
            'success' => false,
            'message' => 'Bid must be at least NPR ' . format_money($minNextBid),
            'min_next_bid' => $minNextBid,
        ], 400);
    }

    $insertBid = $pdo->prepare('INSERT INTO bids (auction_id, bidder_id, bid_amount, created_at) VALUES (?, ?, ?, NOW())');
    $insertBid->execute([$auctionId, $userId, $bidAmount]);

    $updateAuction = $pdo->prepare('UPDATE auctions SET current_price = ?, highest_bidder_id = ?, bids_count = bids_count + 1, status = ? WHERE id = ?');
    $updateAuction->execute([$bidAmount, $userId, 'Live', $auctionId]);

    $pdo->commit();

    json_response([
        'success' => true,
        'message' => 'Bid placed successfully.',
        'current_price' => $bidAmount,
        'min_next_bid' => $bidAmount + (float)$auction['bid_increment'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('PLACE BID ERROR: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to place bid right now.'], 500);
}
