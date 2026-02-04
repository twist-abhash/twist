<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$catStmt = $pdo->prepare('SELECT id, name FROM categories ORDER BY name ASC');
$catStmt->execute();
$categories = $catStmt->fetchAll();
$validCategoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);

$form = [
    'category_id' => '',
    'title' => '',
    'description' => '',
    'starting_price' => '',
    'bid_increment' => '',
    'start_time' => '',
    'end_time' => '',
    'go_live_now' => '1',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $form['category_id'] = (string)(int)($_POST['category_id'] ?? 0);
    $form['title'] = trim($_POST['title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['starting_price'] = trim($_POST['starting_price'] ?? '');
    $form['bid_increment'] = trim($_POST['bid_increment'] ?? '');
    $form['end_time'] = trim($_POST['end_time'] ?? '');
    $form['go_live_now'] = isset($_POST['go_live_now']) ? '1' : '0';
    $form['start_time'] = trim($_POST['start_time'] ?? '');

    $categoryId = (int)$form['category_id'];
    $startingPrice = (float)$form['starting_price'];
    $bidIncrement = (float)$form['bid_increment'];
    $title = $form['title'];
    $description = $form['description'];
    $imagePath = null;

    if ($categoryId <= 0) {
        $errors[] = 'Category is required.';
    } elseif (!in_array($categoryId, $validCategoryIds, true)) {
        $errors[] = 'Selected category is invalid.';
    }
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($description === '') {
        $errors[] = 'Description is required.';
    }
    if ($startingPrice <= 0) {
        $errors[] = 'Starting price must be greater than 0.';
    }
    if ($bidIncrement <= 0) {
        $errors[] = 'Bid increment must be greater than 0.';
    }

    $startTime = date('Y-m-d H:i:s');
    if ($form['go_live_now'] === '0') {
        if ($form['start_time'] === '') {
            $errors[] = 'Start time is required when scheduling.';
        } else {
            $parsedStart = strtotime($form['start_time']);
            if ($parsedStart === false) {
                $errors[] = 'Invalid start time format.';
            } else {
                $startTime = date('Y-m-d H:i:s', $parsedStart);
            }
        }
    }

    if ($form['end_time'] === '') {
        $errors[] = 'End time is required.';
        $endTime = '';
    } else {
        $parsedEnd = strtotime($form['end_time']);
        if ($parsedEnd === false) {
            $errors[] = 'Invalid end time format.';
            $endTime = '';
        } else {
            $endTime = date('Y-m-d H:i:s', $parsedEnd);
        }
    }

    if (!empty($startTime) && !empty($endTime) && strtotime($endTime) <= strtotime($startTime)) {
        $errors[] = 'End time must be after start time.';
    }

    if (!$errors) {
        try {
            $imagePath = handle_auction_image_upload('image');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
    } else {
        $now = time();
        $startTs = strtotime($startTime);
        $endTs = strtotime($endTime);

        if ($now >= $endTs) {
            $status = 'Ended';
        } elseif ($now >= $startTs) {
            $status = 'Live';
        } else {
            $status = 'Scheduled';
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO auctions (category_id, title, description, image_path, starting_price, bid_increment, start_time, end_time, status, current_price, highest_bidder_id, bids_count, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 0, NOW())');
            $stmt->execute([
                $categoryId,
                $title,
                $description,
                $imagePath,
                $startingPrice,
                $bidIncrement,
                $startTime,
                $endTime,
                $status,
                $startingPrice,
            ]);

            $newId = (int)$pdo->lastInsertId();
            admin_log($pdo, (int)$_SESSION['admin_id'], 'CREATE_AUCTION', 'Created auction #' . $newId . ' (' . $title . ')');

            set_flash('success', 'Auction created successfully.');
            redirect('/admin/auctions_list.php');
        } catch (Throwable $e) {
            app_log('AUCTION CREATE ERROR: ' . $e->getMessage());
            set_flash('error', 'Could not create auction.');
        }
    }
}

$pageTitle = 'Create Auction';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <p class="eyebrow">Abhash Bids Admin</p>
    <h1>Create Auction</h1>
    <p class="muted-text">Upload a clear image; the same image appears to users on all bidding screens.</p>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>

        <label>Category</label>
        <select name="category_id" required>
            <option value="">Select Category</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo (int)$category['id']; ?>" <?php echo (int)$form['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>>
                    <?php echo e($category['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Title</label>
        <input type="text" name="title" required value="<?php echo e($form['title']); ?>">

        <label>Description</label>
        <textarea name="description" rows="4" required><?php echo e($form['description']); ?></textarea>

        <label>Auction Image (JPG/PNG/WEBP, max 5MB)</label>
        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp">
        <div class="form-preview-empty" id="image-preview-empty">No image selected</div>
        <img src="" alt="Selected auction image preview" class="form-preview-image hidden" id="image-preview">

        <label>Starting Price (NPR)</label>
        <input type="number" step="0.01" min="0.01" name="starting_price" required value="<?php echo e($form['starting_price']); ?>">

        <label>Bid Increment (NPR)</label>
        <input type="number" step="0.01" min="0.01" name="bid_increment" required value="<?php echo e($form['bid_increment']); ?>">

        <div class="checkbox-row">
            <input type="checkbox" id="go_live_now" name="go_live_now" value="1" <?php echo $form['go_live_now'] === '1' ? 'checked' : ''; ?>>
            <label for="go_live_now">Go Live Now</label>
        </div>

        <label>Schedule Start Time (used when Go Live Now is unchecked)</label>
        <input type="datetime-local" name="start_time" value="<?php echo e($form['start_time']); ?>">

        <label>End Time</label>
        <input type="datetime-local" name="end_time" required value="<?php echo e($form['end_time']); ?>">

        <button type="submit">Create Auction</button>
    </form>
</section>
<script>
(() => {
    const input = document.getElementById('image');
    const preview = document.getElementById('image-preview');
    const empty = document.getElementById('image-preview-empty');
    if (!input || !preview || !empty) {
        return;
    }
    input.addEventListener('change', () => {
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!file) {
            preview.src = '';
            preview.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
        empty.classList.add('hidden');
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
