<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_log(string $message): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/app.log';
    if (!file_exists($logFile)) {
        touch($logFile);
    }
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $logFile);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_valid_phone(?string $phone): bool
{
    if ($phone === null || $phone === '') {
        return true;
    }
    return (bool)preg_match('/^\d{10}$/', $phone);
}

function is_valid_email(?string $email): bool
{
    if ($email === null || $email === '') {
        return true;
    }
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function require_contact(string $phone, string $email): bool
{
    return $phone !== '' || $email !== '';
}

function format_money(float $value): string
{
    return number_format($value, 2, '.', '');
}

function format_dt(?string $value): string
{
    if (!$value) {
        return '-';
    }
    return date('Y-m-d H:i:s', strtotime($value));
}

function auction_status_for_row(array $auction, ?string $now = null): string
{
    $nowTs = strtotime($now ?? date('Y-m-d H:i:s'));
    $startTs = strtotime($auction['start_time']);
    $endTs = strtotime($auction['end_time']);

    if ($auction['status'] === 'Cancelled') {
        return 'Cancelled';
    }
    if ($auction['status'] === 'Ended' || $nowTs >= $endTs) {
        return 'Ended';
    }
    if ($nowTs < $startTs) {
        return 'Scheduled';
    }
    return 'Live';
}

function close_auction_if_due(PDO $pdo, int $auctionId): void
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, status, end_time FROM auctions WHERE id = ? FOR UPDATE');
        $stmt->execute([$auctionId]);
        $auction = $stmt->fetch();

        if (!$auction) {
            $pdo->rollBack();
            return;
        }

        if ($auction['status'] !== 'Cancelled' && $auction['status'] !== 'Ended' && strtotime($auction['end_time']) <= time()) {
            $update = $pdo->prepare("UPDATE auctions SET status = 'Ended' WHERE id = ?");
            $update->execute([$auctionId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('CLOSE AUCTION ERROR: ' . $e->getMessage());
    }
}

function sync_auction_status(PDO $pdo, int $auctionId): void
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, status, start_time, end_time FROM auctions WHERE id = ? FOR UPDATE');
        $stmt->execute([$auctionId]);
        $auction = $stmt->fetch();

        if (!$auction) {
            $pdo->rollBack();
            return;
        }

        $status = $auction['status'];
        if ($status !== 'Cancelled' && $status !== 'Ended') {
            $now = time();
            $start = strtotime($auction['start_time']);
            $end = strtotime($auction['end_time']);

            if ($now >= $end) {
                $status = 'Ended';
            } elseif ($now >= $start) {
                $status = 'Live';
            } else {
                $status = 'Scheduled';
            }

            $update = $pdo->prepare('UPDATE auctions SET status = ? WHERE id = ?');
            $update->execute([$status, $auctionId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('SYNC AUCTION STATUS ERROR: ' . $e->getMessage());
    }
}

function close_due_auctions(PDO $pdo): int
{
    $closed = 0;

    try {
        $pdo->beginTransaction();
        $liveStmt = $pdo->prepare("SELECT id FROM auctions WHERE status = 'Scheduled' AND start_time <= NOW() AND end_time > NOW() FOR UPDATE");
        $liveStmt->execute();
        $liveIds = $liveStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($liveIds) {
            $markLive = $pdo->prepare("UPDATE auctions SET status = 'Live' WHERE id = ?");
            foreach ($liveIds as $id) {
                $markLive->execute([(int)$id]);
            }
        }

        $endStmt = $pdo->prepare("SELECT id FROM auctions WHERE status IN ('Scheduled','Live') AND end_time <= NOW() FOR UPDATE");
        $endStmt->execute();
        $endIds = $endStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($endIds) {
            $markEnded = $pdo->prepare("UPDATE auctions SET status = 'Ended' WHERE id = ?");
            foreach ($endIds as $id) {
                $markEnded->execute([(int)$id]);
                $closed++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log('CLOSE DUE AUCTIONS ERROR: ' . $e->getMessage());
    }

    return $closed;
}

function admin_log(PDO $pdo, int $adminId, string $action, string $details): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$adminId, $action, $details]);
    } catch (Throwable $e) {
        app_log('ADMIN LOG ERROR: ' . $e->getMessage());
    }
}

function auction_min_next_bid(array $auction): float
{
    if ((int)$auction['bids_count'] === 0) {
        return (float)$auction['starting_price'];
    }
    return (float)$auction['current_price'] + (float)$auction['bid_increment'];
}

function json_response(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function destroy_session_and_redirect(string $path, string $type, string $message): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    set_flash($type, $message);
    redirect($path);
}

function handle_auction_image_upload(string $field = 'image'): ?string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Upload failed: file exceeds PHP upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE => 'Upload failed: file exceeds form upload size limit.',
            UPLOAD_ERR_PARTIAL => 'Upload failed: file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Upload failed: missing temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Upload failed: server could not write file to disk.',
            UPLOAD_ERR_EXTENSION => 'Upload failed: blocked by a PHP extension.',
        ];
        $message = $uploadErrors[$errorCode] ?? 'Upload failed with error code ' . $errorCode . '.';
        throw new RuntimeException($message);
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be 5MB or smaller.');
    }

    if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || $file['tmp_name'] === '') {
        throw new RuntimeException('Upload failed: temporary file is missing.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload failed: invalid uploaded file.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('Server configuration error: PHP fileinfo extension is not enabled.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!is_string($mime) || $mime === '') {
        throw new RuntimeException('Upload failed: could not detect image type.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }

    $uploadDir = dirname(__DIR__) . '/assets/uploads/auctions';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }
    if (!is_writable($uploadDir)) {
        throw new RuntimeException('Upload directory is not writable by the web server.');
    }

    $filename = 'auction_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded image.');
    }
    @chmod($destination, 0644);

    return '/assets/uploads/auctions/' . $filename;
}

function delete_uploaded_image(?string $path): void
{
    if (!$path || strpos($path, '/assets/uploads/auctions/') !== 0) {
        return;
    }

    $fullPath = dirname(__DIR__) . $path;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function get_app_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    static $cache = [];
    static $settingsTableChecked = false;
    static $settingsTableExists = false;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!$settingsTableChecked) {
        try {
            $checkStmt = $pdo->query("SHOW TABLES LIKE 'app_settings'");
            $settingsTableExists = (bool)$checkStmt->fetchColumn();
        } catch (Throwable $e) {
            app_log('APP SETTINGS TABLE CHECK ERROR: ' . $e->getMessage());
            $settingsTableExists = false;
        }
        $settingsTableChecked = true;
    }

    if (!$settingsTableExists) {
        $cache[$key] = $default;
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value === false ? $default : (string)$value;
    } catch (Throwable $e) {
        app_log('APP SETTINGS READ ERROR: ' . $e->getMessage());
        $cache[$key] = $default;
    }

    return $cache[$key];
}
