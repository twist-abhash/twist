<?php
declare(strict_types=1);

$siteName = 'Abhash Bids';
$pageTitle = $pageTitle ?? $siteName;
$titleTag = $pageTitle === $siteName ? $siteName : ($pageTitle . ' | ' . $siteName);
$portal = $portal ?? 'public';
$pageBodyClass = trim((string)($pageBodyClass ?? ''));
$bodyClass = trim('portal-' . $portal . ' ' . $pageBodyClass);
$cssFile = dirname(__DIR__) . '/assets/css/style.css';
$cssVersion = is_file($cssFile) ? (string)filemtime($cssFile) : '1';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($titleTag); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo e($cssVersion); ?>">
</head>
<body class="<?php echo e($bodyClass); ?>">
<header class="topbar">
    <div class="container nav-wrap">
        <a class="brand" href="<?php echo $portal === 'admin' ? '/admin/dashboard.php' : ($portal === 'user' ? '/user/dashboard.php' : '/user/login.php'); ?>">
            <span class="brand-name"><?php echo e($siteName); ?></span>
            <span class="brand-tagline">Premium Online Auctions</span>
        </a>
        <nav>
            <?php if ($portal === 'admin'): ?>
                <a href="/admin/dashboard.php">Dashboard</a>
                <a href="/admin/categories_list.php">Categories</a>
                <a href="/admin/auctions_list.php">Auctions</a>
                <a href="/admin/users_list.php">Users</a>
                <a href="/admin/reports.php">Reports</a>
                <a href="/admin/run_closer.php">Run Closer</a>
                <a href="/admin/logout.php">Logout</a>
            <?php elseif ($portal === 'user'): ?>
                <a href="/user/dashboard.php">Dashboard</a>
                <a href="/user/auctions_live.php">Live Auctions</a>
                <a href="/user/my_bids.php">My Bids</a>
                <a href="/user/my_orders.php">My Orders</a>
                <a href="/user/profile.php">Profile</a>
                <a href="/user/logout.php">Logout</a>
            <?php else: ?>
                <a href="/user/login.php">User Login</a>
                <a href="/user/register.php">Register</a>
                <a href="/admin/login.php">Admin Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container main-content">
    <?php if ($flash): ?>
        <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>
