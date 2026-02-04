<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid auction ID.');
    redirect('/admin/auctions_list.php');
}

$catStmt = $pdo->prepare('SELECT id, name FROM categories ORDER BY name ASC');
$catStmt->execute();
$categories = $catStmt->fetchAll();
$validCategoryIds = array_map(static fn(array $category): int => (int)$category['id'], $categories);

$stmt = $pdo->prepare('SELECT * FROM auctions WHERE id = ?');
$stmt->execute([$id]);
$auction = $stmt->fetch();

if (!$auction) {
    set_flash('error', 'Auction not found.');
    redirect('/admin/auctions_list.php');
}

$form = [
    'category_id' => (string)(int)$auction['category_id'],
    'title' => (string)$auction['title'],
    'description' => (string)$auction['description'],
    'starting_price' => (string)$auction['starting_price'],
    'bid_increment' => (string)$auction['bid_increment'],
    'go_live_now' => '0',
    'start_time' => '',
    'end_time' => date('Y-m-d\TH:i', strtotime($auction['end_time'])),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($auction['status'] === 'Cancelled' || $auction['status'] === 'Ended') {
        set_flash('error', 'Cancelled or ended auctions cannot be edited.');
        redirect('/admin/auctions_list.php');
    }

    $form['category_id'] = (string)(int)($_POST['category_id'] ?? 0);
    $form['title'] = trim($_POST['title'] ?? '');
    $form['description'] = trim($_POST['description'] ?? '');
    $form['starting_price'] = trim($_POST['starting_price'] ?? '');
    $form['bid_increment'] = trim($_POST['bid_increment'] ?? '');
    $form['go_live_now'] = isset($_POST['go_live_now']) ? '1' : '0';
    $form['start_time'] = trim($_POST['start_time'] ?? '');
    $form['end_time'] = trim($_POST['end_time'] ?? '');

    $categoryId = (int)$form['category_id'];
    $title = $form['title'];
    $description = $form['description'];
    $startingPrice = (float)$form['starting_price'];
    $bidIncrement = (float)$form['bid_increment'];
    $goLiveNow = $form['go_live_now'] === '1';
    $inputStartTime = $form['start_time'];
    $inputEndTime = $form['end_time'];
    $newImagePath = $auction['image_path'];

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

    if ((int)$auction['bids_count'] > 0 && $startingPrice > (float)$auction['current_price']) {
        $errors[] = 'Starting price cannot be higher than current price after bids exist.';
    }

    $startTime = $auction['start_time'];
    if ($goLiveNow) {
        $startTime = date('Y-m-d H:i:s');
    } elseif ($inputStartTime !== '') {
        $parsedStart = strtotime($inputStartTime);
        if ($parsedStart === false) {
            $errors[] = 'Invalid start time.';
        } else {
            $startTime = date('Y-m-d H:i:s', $parsedStart);
        }
    }

    $endTime = $auction['end_time'];
    if ($inputEndTime !== '') {
        $parsedEnd = strtotime($inputEndTime);
        if ($parsedEnd === false) {
            $errors[] = 'Invalid end time.';
        } else {
            $endTime = date('Y-m-d H:i:s', $parsedEnd);
        }
    }

    if (strtotime($endTime) <= strtotime($startTime)) {
        $errors[] = 'End time must be after start time.';
    }

    if (!$errors) {
        try {
            $uploaded = handle_auction_image_upload('image');
            if ($uploaded !== null) {
                $newImagePath = $uploaded;
            }
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

        $currentPrice = (int)$auction['bids_count'] > 0 ? (float)$auction['current_price'] : $startingPrice;

        try {
            $update = $pdo->prepare('UPDATE auctions SET category_id = ?, title = ?, description = ?, image_path = ?, starting_price = ?, bid_increment = ?, start_time = ?, end_time = ?, status = ?, current_price = ? WHERE id = ?');
            $update->execute([
                $categoryId,
                $title,
                $description,
                $newImagePath,
                $startingPrice,
                $bidIncrement,
                $startTime,
                $endTime,
                $status,
                $currentPrice,
                $id,
            ]);

            if ($newImagePath !== $auction['image_path']) {
                delete_uploaded_image($auction['image_path']);
            }

            admin_log($pdo, (int)$_SESSION['admin_id'], 'EDIT_AUCTION', 'Edited auction #' . $id);

            set_flash('success', 'Auction updated successfully.');
            redirect('/admin/auctions_list.php');
        } catch (Throwable $e) {
            if ($newImagePath !== $auction['image_path']) {
                delete_uploaded_image($newImagePath);
            }
            app_log('AUCTION EDIT ERROR: ' . $e->getMessage());
            set_flash('error', 'Could not update auction.');
        }
    }
}

$pageTitle = 'Edit Auction';
$portal = 'admin';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="card form-card">
    <p class="eyebrow">Abhash Bids Admin</p>
    <h1>Edit Auction #<?php echo (int)$auction['id']; ?></h1>
    <p class="muted-text">Updating the image here instantly changes what users see while placing bids.</p>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_input(); ?>

        <label>Category</label>
        <select name="category_id" required>
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

        <label>Current Image</label>
        <?php if (!empty($auction['image_path'])): ?>
            <img src="<?php echo e($auction['image_path']); ?>" alt="Auction image" class="form-preview-image">
            <p><a class="btn-link" href="<?php echo e($auction['image_path']); ?>" target="_blank" rel="noopener">Open full image</a></p>
        <?php else: ?>
            <div class="form-preview-empty">No image uploaded</div>
        <?php endif; ?>

        <label>Replace Image (JPG/PNG/WEBP, max 5MB)</label>
        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.webp">
        <div class="form-preview-empty" id="image-preview-empty">No replacement image selected</div>
        <img src="" alt="Replacement image preview" class="form-preview-image hidden" id="image-preview">

        <label>Starting Price (NPR)</label>
        <input type="number" step="0.01" min="0.01" name="starting_price" required value="<?php echo e($form['starting_price']); ?>">

        <label>Bid Increment (NPR)</label>
        <input type="number" step="0.01" min="0.01" name="bid_increment" required value="<?php echo e($form['bid_increment']); ?>">

        <div class="checkbox-row">
            <input type="checkbox" id="go_live_now" name="go_live_now" value="1" <?php echo $form['go_live_now'] === '1' ? 'checked' : ''; ?>>
            <label for="go_live_now">Go Live Now</label>
        </div>

        <label>Start Time (leave empty to keep current: <?php echo e(date('Y-m-d\TH:i', strtotime($auction['start_time']))); ?>)</label>
        <input type="datetime-local" name="start_time" value="<?php echo e($form['start_time']); ?>">

        <label>End Time</label>
        <input type="datetime-local" name="end_time" required value="<?php echo e($form['end_time']); ?>">

        <button type="submit">Update Auction</button>
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
