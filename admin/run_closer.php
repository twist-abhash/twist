<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_admin.php';

$closedCount = close_due_auctions($pdo);
admin_log($pdo, (int)$_SESSION['admin_id'], 'RUN_CLOSER', 'Manual closer executed. Closed: ' . $closedCount);
set_flash('success', 'Closer run completed. Closed auctions: ' . $closedCount);
redirect('/admin/dashboard.php');
