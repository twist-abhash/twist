<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/doctor-dashboard.css">
    <title>My Sessions</title>
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.45s;
        }
    </style>
</head>
<body>
<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
require_once __DIR__ . "/dashboard_helpers.php";

session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

function schedule_page_status(array $session, $currentMoment)
{
    $scheduledDate = trim((string)($session['scheduledate'] ?? ''));
    $scheduledTime = trim((string)($session['scheduletime'] ?? ''));
    $booked = (int)($session['booked'] ?? 0);
    $capacity = (int)($session['nop'] ?? 0);

    if ($scheduledDate === '' || $scheduledTime === '') {
        return ['code' => 'draft', 'label' => 'Draft', 'tone' => 'warning'];
    }

    $endTime = doc_sathi_schedule_end_time($session);
    if ($endTime !== '' && doc_sathi_session_has_ended($scheduledDate, $endTime, $currentMoment)) {
        return ['code' => 'completed', 'label' => 'Completed', 'tone' => 'neutral'];
    }

    if (doc_sathi_session_has_started($scheduledDate, $scheduledTime, $currentMoment)) {
        return ['code' => 'in_progress', 'label' => 'In Progress', 'tone' => 'info'];
    }

    if ($booked >= $capacity && $capacity > 0) {
        return ['code' => 'full', 'label' => 'Full', 'tone' => 'warning'];
    }

    if ($scheduledDate === $currentMoment->format('Y-m-d')) {
        return ['code' => 'today', 'label' => 'Today', 'tone' => 'info'];
    }

    return ['code' => 'active', 'label' => 'Active', 'tone' => 'success'];
}

function schedule_matches_filter(array $session, $statusFilter, $today)
{
    if ($statusFilter === 'all') {
        return true;
    }

    if ($statusFilter === 'today') {
        return ($session['scheduledate'] ?? '') === $today;
    }

    if ($statusFilter === 'full') {
        return ($session['status']['code'] ?? '') === 'full';
    }

    if ($statusFilter === 'completed') {
        return ($session['status']['code'] ?? '') === 'completed';
    }

    return ($session['status']['code'] ?? '') !== 'completed';
}

function schedule_page_notice($notice, $message = '')
{
    $map = [
        'session-added' => [
            'type' => 'success',
            'message' => 'Session created successfully.',
        ],
        'session-updated' => [
            'type' => 'success',
            'message' => 'Session updated successfully.',
        ],
        'session-cancelled' => [
            'type' => 'success',
            'message' => 'Session cancelled successfully.',
        ],
        'verification-required' => [
            'type' => 'warning',
            'message' => 'Your account must be approved before you can create new sessions.',
        ],
        'invalid-input' => [
            'type' => 'error',
            'message' => 'The submitted session details were incomplete or invalid.',
        ],
        'invalid-doctor' => [
            'type' => 'error',
            'message' => 'Your doctor account could not be verified for that request.',
        ],
        'invalid-session' => [
            'type' => 'warning',
            'message' => 'That session could not be found or does not belong to your account.',
        ],
        'error' => [
            'type' => 'error',
            'message' => $message !== '' ? $message : 'Something went wrong while saving the session.',
        ],
    ];

    return $map[$notice] ?? null;
}

function schedule_form_error($error)
{
    $map = [
        'invalid-input' => 'Please complete every field with a valid title, date, start time, duration, and capacity.',
        'invalid-capacity' => 'Capacity must be a natural number using digits only, with no symbols, letters, decimals, or fractions.',
        'invalid-title' => 'Session title must contain at least one letter and use only letters, numbers, spaces, apostrophes, periods, or hyphens.',
        'invalid-date' => 'Please enter a valid session date.',
        'invalid-time' => 'Please enter a valid session time.',
        'invalid-duration' => 'Please select one of the available session durations: 15, 20, 30, 45, 60, or 90 minutes.',
        'invalid-duration-window' => 'That start time and duration would create an invalid session window. Please choose a valid combination.',
        'invalid-datetime' => 'Session date and time cannot be in the past.',
        'overlapping-session' => 'This session overlaps with another session already scheduled for that date. Please choose a different time or duration.',
        'capacity-below-booked' => 'Capacity cannot be lower than the number of booked appointments for this session.',
        'save-failed' => 'The session could not be saved. Please try again.',
    ];

    return $map[$error] ?? '';
}

date_default_timezone_set('Asia/Kathmandu');

$useremail = $_SESSION["user"];
$doctor = doc_sathi_get_doctor_by_email($database, $useremail);

if (!$doctor) {
    header("location: ../login.php");
    exit();
}

$userid = (int)$doctor["docid"];
$username = $doctor["docname"];
$today = date('Y-m-d');
$currentTime = date('H:i:s');
$currentMoment = new DateTimeImmutable('now', new DateTimeZone('Asia/Kathmandu'));
$isApproved = doc_sathi_doctor_is_approved($doctor);
$verificationMessage = doc_sathi_doctor_status_message($doctor);
$durationOptions = doc_sathi_allowed_session_durations();

$allowedStatuses = ['all', 'active', 'today', 'full', 'completed'];
$searchTerm = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$action = trim($_GET['action'] ?? '');
$recordId = (int)($_GET['id'] ?? 0);
$notice = trim($_GET['notice'] ?? '');
$error = trim($_GET['error'] ?? '');
$message = trim($_GET['message'] ?? '');

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$modalActions = ['view', 'edit', 'drop', 'add-session'];
if ($notice === '' && $action !== '' && !in_array($action, $modalActions, true)) {
    $notice = $action;
    $action = '';
}

$filterState = [
    'search' => $searchTerm,
    'date' => $dateFilter,
    'status' => $statusFilter !== 'all' ? $statusFilter : '',
];
$baseReturnUrl = doctor_dashboard_build_url('schedule.php', $filterState);
$filtersActive = $searchTerm !== '' || $dateFilter !== '' || $statusFilter !== 'all';
$flashNotice = schedule_page_notice($notice, $message);

$summaryStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total_sessions,
            COALESCE(
                SUM(
                    CASE
                        WHEN scheduledate > ? THEN 1
                        WHEN scheduledate = ?
                             AND COALESCE(
                                    end_time,
                                    ADDTIME(
                                        scheduletime,
                                        SEC_TO_TIME(COALESCE(duration_minutes, " . doc_sathi_default_session_duration_minutes() . ") * 60)
                                    )
                                 ) > ? THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS active_sessions
     FROM schedule
     WHERE docid = ?"
);
$summaryStmt->bind_param("sssi", $today, $today, $currentTime, $userid);
doc_sathi_execute($summaryStmt);
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];

$bookedSlotsStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(a.appoid) AS booked_slots
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?"
);
$bookedSlotsStmt->bind_param("i", $userid);
doc_sathi_execute($bookedSlotsStmt);
$bookedSummary = $bookedSlotsStmt->get_result()->fetch_assoc() ?: [];

$capacityStmt = doc_sathi_prepare(
    $database,
    "SELECT COALESCE(SUM(GREATEST(s.nop - COALESCE(bookings.booked, 0), 0)), 0) AS available_capacity
     FROM schedule s
     LEFT JOIN (
         SELECT scheduleid, COUNT(*) AS booked
         FROM appointment
         GROUP BY scheduleid
     ) AS bookings ON bookings.scheduleid = s.scheduleid
     WHERE s.docid = ?
       AND (
            s.scheduledate > ?
            OR (
                s.scheduledate = ?
                AND COALESCE(
                        s.end_time,
                        ADDTIME(
                            s.scheduletime,
                            SEC_TO_TIME(COALESCE(s.duration_minutes, " . doc_sathi_default_session_duration_minutes() . ") * 60)
                        )
                    ) > ?
            )
       )"
);
$capacityStmt->bind_param("isss", $userid, $today, $today, $currentTime);
doc_sathi_execute($capacityStmt);
$capacitySummary = $capacityStmt->get_result()->fetch_assoc() ?: [];

$sessionsSql = "SELECT s.scheduleid,
                       s.title,
                       s.scheduledate,
                       s.scheduletime,
                       s.duration_minutes,
                       s.end_time,
                       s.nop,
                       COUNT(a.appoid) AS booked
                FROM schedule s
                LEFT JOIN appointment a ON a.scheduleid = s.scheduleid
                WHERE s.docid = ?";
$sessionTypes = "i";
$sessionParams = [$userid];

if ($searchTerm !== '') {
    $sessionsSql .= " AND s.title LIKE ?";
    $sessionTypes .= "s";
    $sessionParams[] = doc_sathi_search_pattern($searchTerm);
}

if ($dateFilter !== '') {
    $sessionsSql .= " AND s.scheduledate = ?";
    $sessionTypes .= "s";
    $sessionParams[] = $dateFilter;
}

$sessionsSql .= " GROUP BY s.scheduleid, s.title, s.scheduledate, s.scheduletime, s.duration_minutes, s.end_time, s.nop";

$sessionsSql .= " ORDER BY
                    CASE WHEN s.scheduledate >= ? THEN 0 ELSE 1 END,
                    s.scheduledate ASC,
                    s.scheduletime ASC,
                    s.scheduleid DESC";
$sessionTypes .= "s";
$sessionParams[] = $today;

$sessionsStmt = doc_sathi_prepare($database, $sessionsSql);
doctor_dashboard_bind_params($sessionsStmt, $sessionTypes, $sessionParams);
doc_sathi_execute($sessionsStmt);
$sessionsResult = $sessionsStmt->get_result();
$sessions = [];

while ($row = $sessionsResult->fetch_assoc()) {
    $row['duration_minutes'] = doc_sathi_schedule_duration_minutes($row);
    $row['end_time'] = doc_sathi_schedule_end_time($row);
    $row['status'] = schedule_page_status($row, $currentMoment);
    $row['available'] = max((int)$row['nop'] - (int)$row['booked'], 0);
    $row['can_manage'] = doc_sathi_session_can_be_managed($row, $currentMoment);

    if (schedule_matches_filter($row, $statusFilter, $today)) {
        $sessions[] = $row;
    }
}

$activeSession = null;
$bookedPatients = [];

if ($recordId > 0 && in_array($action, ['view', 'edit', 'drop'], true)) {
    $sessionDialogStmt = doc_sathi_prepare(
        $database,
        "SELECT s.scheduleid,
                s.title,
                s.scheduledate,
                s.scheduletime,
                s.duration_minutes,
                s.end_time,
                s.nop,
                COUNT(a.appoid) AS booked
         FROM schedule s
         LEFT JOIN appointment a ON a.scheduleid = s.scheduleid
         WHERE s.docid = ?
           AND s.scheduleid = ?
         GROUP BY s.scheduleid, s.title, s.scheduledate, s.scheduletime, s.duration_minutes, s.end_time, s.nop
         LIMIT 1"
    );
    $sessionDialogStmt->bind_param("ii", $userid, $recordId);
    doc_sathi_execute($sessionDialogStmt);
    $activeSession = $sessionDialogStmt->get_result()->fetch_assoc();

    if ($activeSession) {
        $activeSession['duration_minutes'] = doc_sathi_schedule_duration_minutes($activeSession);
        $activeSession['end_time'] = doc_sathi_schedule_end_time($activeSession);
        $activeSession['status'] = schedule_page_status($activeSession, $currentMoment);
        $activeSession['available'] = max((int)$activeSession['nop'] - (int)$activeSession['booked'], 0);
        $activeSession['can_manage'] = doc_sathi_session_can_be_managed($activeSession, $currentMoment);

        if ($action === 'view') {
            $patientsStmt = doc_sathi_prepare(
                $database,
                "SELECT a.appoid,
                        a.apponum,
                        a.status,
                        a.completed_at,
                        p.pid,
                        p.pname,
                        p.pemail,
                        p.pnum
                 FROM appointment a
                 INNER JOIN patient p ON p.pid = a.pid
                 WHERE a.scheduleid = ?
                 ORDER BY a.apponum ASC"
            );
            $patientsStmt->bind_param("i", $recordId);
            doc_sathi_execute($patientsStmt);
            $patientsResult = $patientsStmt->get_result();
            while ($patientRow = $patientsResult->fetch_assoc()) {
                $patientRow['scheduledate'] = $activeSession['scheduledate'];
                $patientRow['scheduletime'] = $activeSession['scheduletime'];
                $patientRow['duration_minutes'] = $activeSession['duration_minutes'];
                $patientRow['end_time'] = $activeSession['end_time'];
                $patientRow['status_details'] = doc_sathi_appointment_status_details($patientRow, $currentMoment);
                $bookedPatients[] = $patientRow;
            }
        }
    }
}

if ($recordId > 0 && in_array($action, ['view', 'edit', 'drop'], true) && !$activeSession) {
    $flashNotice = schedule_page_notice('invalid-session');
    $action = '';
}

$formErrorMessage = schedule_form_error($error);
?>

<div class="container doctor-dashboard-layout">
    <?php doctor_dashboard_sidebar('sessions', $username, $useremail); ?>

    <div class="dash-body doctor-dashboard-body">
        <main class="doctor-page-shell">
            <?php if ($flashNotice): ?>
                <div class="doctor-alert <?php echo doctor_dashboard_h($flashNotice['type']); ?>">
                    <?php echo doctor_dashboard_h($flashNotice['message']); ?>
                </div>
            <?php endif; ?>

            <header class="doctor-page-header">
                <div class="doctor-page-header-main">
                    <a href="index.php" class="doctor-back-link">Back</a>
                    <div class="doctor-page-title-block">
                        <h1>My Sessions</h1>
                        <p>Manage your available sessions and bookings</p>
                    </div>
                </div>
                <aside class="doctor-date-card">
                    <span>Today's Date</span>
                    <strong><?php echo doctor_dashboard_h(date('M d, Y')); ?></strong>
                </aside>
            </header>

            <?php if (!$isApproved): ?>
                <div class="doctor-alert warning">
                    <?php echo doctor_dashboard_h($verificationMessage); ?>
                    <a href="verification.php">Review verification details</a>
                </div>
            <?php endif; ?>

            <section class="doctor-stats-grid doctor-page-stats" aria-label="Session summary">
                <article class="doctor-stat-card">
                    <span>Total Sessions</span>
                    <strong><?php echo (int)($summary['total_sessions'] ?? 0); ?></strong>
                    <p>All sessions created under your doctor profile.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Active Sessions</span>
                    <strong><?php echo (int)($summary['active_sessions'] ?? 0); ?></strong>
                    <p>Sessions scheduled for today and future dates.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Booked Slots</span>
                    <strong><?php echo (int)($bookedSummary['booked_slots'] ?? 0); ?></strong>
                    <p>Total patient bookings across all sessions.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Available Capacity</span>
                    <strong><?php echo (int)($capacitySummary['available_capacity'] ?? 0); ?></strong>
                    <p>Remaining future capacity across your current sessions.</p>
                </article>
            </section>

            <section class="doctor-page-card">
                <div class="doctor-card-header">
                    <div>
                        <h2>Manage Sessions</h2>
                        <p>Search, filter, and update your session windows from one focused control area.</p>
                    </div>
                    <div class="doctor-toolbar-actions">
                        <?php if ($isApproved): ?>
                            <a href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('schedule.php', $filterState, ['action' => 'add-session'])); ?>" class="doctor-btn">
                                Add Session
                            </a>
                        <?php else: ?>
                            <span class="doctor-btn disabled">Verification Required</span>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="schedule.php" method="get" class="doctor-toolbar sessions-toolbar">
                    <div class="doctor-field">
                        <label for="session-search">Session Title</label>
                        <input
                            id="session-search"
                            type="search"
                            name="search"
                            value="<?php echo doctor_dashboard_h($searchTerm); ?>"
                            class="doctor-input"
                            placeholder="Search by session title"
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="session-date">Date</label>
                        <input
                            id="session-date"
                            type="date"
                            name="date"
                            value="<?php echo doctor_dashboard_h($dateFilter); ?>"
                            class="doctor-input"
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="session-status">Status</label>
                        <select id="session-status" name="status" class="doctor-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="today" <?php echo $statusFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="full" <?php echo $statusFilter === 'full' ? 'selected' : ''; ?>>Full</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="doctor-toolbar-actions">
                        <button type="submit" class="doctor-btn">Filter</button>
                    </div>

                    <div class="doctor-toolbar-actions">
                        <a href="schedule.php" class="doctor-btn secondary">Reset</a>
                    </div>
                </form>
            </section>

            <section class="doctor-page-card compact">
                <div class="doctor-table-card-header">
                    <div class="doctor-card-header">
                        <div>
                            <h2>Session List</h2>
                            <p><?php echo count($sessions); ?> session<?php echo count($sessions) === 1 ? '' : 's'; ?> shown.</p>
                        </div>
                    </div>
                </div>

                <?php if (empty($sessions)): ?>
                    <div class="doctor-empty-state">
                        <div class="doctor-empty-icon">S</div>
                        <h3>No sessions found</h3>
                        <p>
                            <?php echo $filtersActive
                                ? 'No sessions matched the current search or filter combination. Reset the toolbar to review your full session list.'
                                : 'Create a new session to start taking bookings and managing capacity from this dashboard.'; ?>
                        </p>
                        <?php if ($filtersActive): ?>
                            <a href="schedule.php" class="doctor-btn secondary">Clear Filters</a>
                        <?php elseif ($isApproved): ?>
                            <a href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('schedule.php', [], ['action' => 'add-session'])); ?>" class="doctor-btn">Add Session</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="doctor-table-wrap">
                        <table class="doctor-data-table">
                            <thead>
                                <tr>
                                    <th>Session Title</th>
                                    <th>Scheduled Date &amp; Time</th>
                                    <th>Capacity</th>
                                    <th>Booked</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td>
                                            <div class="doctor-primary-cell">
                                                <div class="doctor-avatar"><?php echo doctor_dashboard_h(doctor_dashboard_initials($session['title'])); ?></div>
                                                <div class="doctor-primary-text">
                                                    <strong><?php echo doctor_dashboard_h($session['title']); ?></strong>
                                                    <span>Session ID S-<?php echo (int)$session['scheduleid']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($session['scheduledate'])); ?></strong>
                                                <span>
                                                    <?php
                                                    echo doctor_dashboard_h(
                                                        doctor_dashboard_format_time($session['scheduletime'])
                                                        . ' - '
                                                        . doctor_dashboard_format_time($session['end_time'])
                                                    );
                                                    ?>
                                                </span>
                                                <small><?php echo doctor_dashboard_h(doc_sathi_session_duration_label($session['duration_minutes'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong><?php echo (int)$session['nop']; ?> slots</strong>
                                                <span><?php echo (int)$session['available']; ?> available</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong><?php echo (int)$session['booked']; ?> booked</strong>
                                                <span>Patient reservations</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="doctor-status-badge <?php echo doctor_dashboard_h($session['status']['tone']); ?>">
                                                <?php echo doctor_dashboard_h($session['status']['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="doctor-table-actions">
                                                <a
                                                    href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('schedule.php', $filterState, ['action' => 'view', 'id' => (int)$session['scheduleid']])); ?>"
                                                    class="doctor-btn small ghost"
                                                >
                                                    View
                                                </a>
                                                <?php if ($session['can_manage']): ?>
                                                    <a
                                                        href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('schedule.php', $filterState, ['action' => 'edit', 'id' => (int)$session['scheduleid']])); ?>"
                                                        class="doctor-btn small secondary"
                                                    >
                                                        Edit
                                                    </a>
                                                    <a
                                                        href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('schedule.php', $filterState, ['action' => 'drop', 'id' => (int)$session['scheduleid']])); ?>"
                                                        class="doctor-btn small danger"
                                                    >
                                                        Cancel Session
                                                    </a>
                                                <?php else: ?>
                                                    <span class="doctor-btn small disabled">Locked</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php if ($action === 'add-session'): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2><?php echo $isApproved ? 'Add Session' : 'Verification Required'; ?></h2>
                <p>
                    <?php echo $isApproved
                        ? 'Create a new session with a clear title, date, start time, duration, and booking capacity.'
                        : doctor_dashboard_h($verificationMessage); ?>
                </p>
            </div>
            <div class="doctor-dialog-body">
                <?php if (!$isApproved): ?>
                    <div class="doctor-dialog-actions">
                        <a href="verification.php" class="doctor-btn">Go to Verification</a>
                        <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Close</a>
                    </div>
                <?php else: ?>
                    <?php if ($formErrorMessage !== ''): ?>
                        <div class="doctor-alert error" style="margin-bottom:18px;">
                            <?php echo doctor_dashboard_h($formErrorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form action="add-session.php" method="post" class="doctor-form-grid">
                        <div class="doctor-field full">
                            <label for="new-title">Session Title</label>
                            <input
                                id="new-title"
                                type="text"
                                name="title"
                                class="doctor-input"
                                placeholder="Enter the session title"
                                pattern=".*[A-Za-z].*"
                                required
                            >
                            <p class="doctor-helper-text">Use a concise title patients can understand easily.</p>
                        </div>

                        <div class="doctor-field">
                            <label for="new-capacity">Capacity</label>
                            <input
                                id="new-capacity"
                                type="text"
                                name="nop"
                                class="doctor-input"
                                inputmode="numeric"
                                pattern="[0-9]+"
                                autocomplete="off"
                                placeholder="e.g. 15"
                                required
                            >
                            <p class="doctor-helper-text">Use digits only, such as 10 or 25.</p>
                        </div>

                        <div class="doctor-field">
                            <label for="new-date">Session Date</label>
                            <input
                                id="new-date"
                                type="date"
                                name="date"
                                class="doctor-input"
                                min="<?php echo doctor_dashboard_h($today); ?>"
                                required
                            >
                        </div>

                        <div class="doctor-field">
                            <label for="new-time">Schedule Time</label>
                            <input
                                id="new-time"
                                type="time"
                                name="time"
                                class="doctor-input"
                                step="60"
                                required
                            >
                        </div>

                        <div class="doctor-field">
                            <label for="new-duration">Duration</label>
                            <select id="new-duration" name="duration_minutes" class="doctor-select" required>
                                <?php foreach ($durationOptions as $durationOption): ?>
                                    <option value="<?php echo (int)$durationOption; ?>" <?php echo (int)$durationOption === doc_sathi_default_session_duration_minutes() ? 'selected' : ''; ?>>
                                        <?php echo doctor_dashboard_h(doc_sathi_session_duration_label($durationOption)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="doctor-helper-text">End time is calculated automatically from the selected duration.</p>
                        </div>

                        <div class="doctor-dialog-actions full">
                            <button type="submit" class="doctor-btn" name="shedulesubmit">Place Session</button>
                            <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif ($action === 'view' && $activeSession): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog wide">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Session Details</h2>
                <p>Review the full session window, capacity, and every booked patient attached to this session.</p>
            </div>
            <div class="doctor-dialog-body">
                <div class="doctor-dialog-grid">
                    <div class="doctor-dialog-item">
                        <span>Session Title</span>
                        <strong><?php echo doctor_dashboard_h($activeSession['title']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Status</span>
                        <strong><?php echo doctor_dashboard_h($activeSession['status']['label']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Session Date</span>
                        <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($activeSession['scheduledate'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Time Window</span>
                        <strong>
                            <?php
                            echo doctor_dashboard_h(
                                doctor_dashboard_format_time($activeSession['scheduletime'])
                                . ' - '
                                . doctor_dashboard_format_time($activeSession['end_time'])
                            );
                            ?>
                        </strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Duration</span>
                        <strong><?php echo doctor_dashboard_h(doc_sathi_session_duration_label($activeSession['duration_minutes'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Capacity</span>
                        <strong><?php echo (int)$activeSession['nop']; ?> slots</strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Booked</span>
                        <strong><?php echo (int)$activeSession['booked']; ?> patients</strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Available</span>
                        <strong><?php echo (int)$activeSession['available']; ?> slots</strong>
                    </div>
                </div>

                <div class="doctor-dialog-table">
                    <?php if (empty($bookedPatients)): ?>
                        <div class="doctor-empty-state" style="padding:34px 18px;">
                            <div class="doctor-empty-icon">B</div>
                            <h3>No bookings yet</h3>
                            <p>This session is still open and has no patient bookings at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="doctor-table-wrap">
                            <table class="doctor-data-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Appointment No.</th>
                                        <th>Status</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookedPatients as $patient): ?>
                                        <tr>
                                            <td>
                                                <div class="doctor-primary-cell">
                                                    <div class="doctor-avatar"><?php echo doctor_dashboard_h(doctor_dashboard_initials($patient['pname'])); ?></div>
                                                    <div class="doctor-primary-text">
                                                        <strong><?php echo doctor_dashboard_h($patient['pname']); ?></strong>
                                                        <span>Patient ID P-<?php echo (int)$patient['pid']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="doctor-metric">
                                                    <strong>#<?php echo (int)$patient['apponum']; ?></strong>
                                                    <span>Queue number</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="doctor-status-badge <?php echo doctor_dashboard_h($patient['status_details']['tone']); ?>">
                                                    <?php echo doctor_dashboard_h($patient['status_details']['label']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo doctor_dashboard_h($patient['pnum']); ?></td>
                                            <td><?php echo doctor_dashboard_h($patient['pemail']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="doctor-dialog-actions">
                    <?php if ($activeSession['can_manage']): ?>
                        <a
                            href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('schedule.php', $filterState, ['action' => 'edit', 'id' => (int)$activeSession['scheduleid']])); ?>"
                            class="doctor-btn secondary"
                        >
                            Edit Session
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn">Close</a>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($action === 'edit' && $activeSession): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Edit Session</h2>
                <p>Update the session window while keeping booked appointments within the available capacity.</p>
            </div>
            <div class="doctor-dialog-body">
                <?php if (!$activeSession['can_manage']): ?>
                    <div class="doctor-alert warning" style="margin-bottom:18px;">
                        Past sessions are locked and cannot be edited.
                    </div>
                <?php elseif ($formErrorMessage !== ''): ?>
                    <div class="doctor-alert error" style="margin-bottom:18px;">
                        <?php echo doctor_dashboard_h($formErrorMessage); ?>
                    </div>
                <?php endif; ?>

                <form action="update-session.php" method="post" class="doctor-form-grid">
                    <input type="hidden" name="scheduleid" value="<?php echo (int)$activeSession['scheduleid']; ?>">

                    <div class="doctor-field full">
                        <label for="edit-title">Session Title</label>
                        <input
                            id="edit-title"
                            type="text"
                            name="title"
                            class="doctor-input"
                            value="<?php echo doctor_dashboard_h($activeSession['title']); ?>"
                            pattern=".*[A-Za-z].*"
                            required
                            <?php echo !$activeSession['can_manage'] ? 'disabled' : ''; ?>
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="edit-capacity">Capacity</label>
                        <input
                            id="edit-capacity"
                            type="text"
                            name="nop"
                            class="doctor-input"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            autocomplete="off"
                            value="<?php echo (int)$activeSession['nop']; ?>"
                            required
                            <?php echo !$activeSession['can_manage'] ? 'disabled' : ''; ?>
                        >
                        <p class="doctor-helper-text"><?php echo (int)$activeSession['booked']; ?> booking(s) already attached to this session.</p>
                    </div>

                    <div class="doctor-field">
                        <label for="edit-date">Session Date</label>
                        <input
                            id="edit-date"
                            type="date"
                            name="date"
                            class="doctor-input"
                            min="<?php echo doctor_dashboard_h($today); ?>"
                            value="<?php echo doctor_dashboard_h($activeSession['scheduledate']); ?>"
                            required
                            <?php echo !$activeSession['can_manage'] ? 'disabled' : ''; ?>
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="edit-time">Schedule Time</label>
                        <input
                            id="edit-time"
                            type="time"
                            name="time"
                            class="doctor-input"
                            step="60"
                            value="<?php echo doctor_dashboard_h(substr((string)$activeSession['scheduletime'], 0, 5)); ?>"
                            required
                            <?php echo !$activeSession['can_manage'] ? 'disabled' : ''; ?>
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="edit-duration">Duration</label>
                        <select
                            id="edit-duration"
                            name="duration_minutes"
                            class="doctor-select"
                            required
                            <?php echo !$activeSession['can_manage'] ? 'disabled' : ''; ?>
                        >
                            <?php foreach ($durationOptions as $durationOption): ?>
                                <option value="<?php echo (int)$durationOption; ?>" <?php echo (int)$activeSession['duration_minutes'] === (int)$durationOption ? 'selected' : ''; ?>>
                                    <?php echo doctor_dashboard_h(doc_sathi_session_duration_label($durationOption)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="doctor-helper-text">End time is recalculated automatically when you save the updated session.</p>
                    </div>

                    <div class="doctor-dialog-actions full">
                        <?php if ($activeSession['can_manage']): ?>
                            <button type="submit" class="doctor-btn">Save Changes</button>
                        <?php endif; ?>
                        <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Close</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php elseif ($action === 'drop' && $activeSession): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Cancel Session</h2>
                <p>Confirm session cancellation. Any booked appointments linked to this session will also be removed.</p>
            </div>
            <div class="doctor-dialog-body">
                <div class="doctor-dialog-grid">
                    <div class="doctor-dialog-item">
                        <span>Session Title</span>
                        <strong><?php echo doctor_dashboard_h($activeSession['title']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Session Date</span>
                        <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($activeSession['scheduledate'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Time Window</span>
                        <strong>
                            <?php
                            echo doctor_dashboard_h(
                                doctor_dashboard_format_time($activeSession['scheduletime'])
                                . ' - '
                                . doctor_dashboard_format_time($activeSession['end_time'])
                            );
                            ?>
                        </strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Duration</span>
                        <strong><?php echo doctor_dashboard_h(doc_sathi_session_duration_label($activeSession['duration_minutes'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Capacity</span>
                        <strong><?php echo (int)$activeSession['nop']; ?> slots</strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Booked Appointments</span>
                        <strong><?php echo (int)$activeSession['booked']; ?></strong>
                    </div>
                </div>

                <div class="doctor-dialog-actions">
                    <a
                        href="<?php echo doctor_dashboard_h('delete-session.php?id=' . (int)$activeSession['scheduleid'] . '&return=' . urlencode($baseReturnUrl)); ?>"
                        class="doctor-btn danger"
                    >
                        Confirm Cancellation
                    </a>
                    <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Keep Session</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<script>
    (function () {
        const today = <?php echo json_encode($today); ?>;
        const editCapacityMinimum = <?php echo json_encode($activeSession ? max(1, (int)$activeSession['booked']) : 1); ?>;

        function currentTimeValue() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            return hours + ':' + minutes;
        }

        function bindNaturalNumberInput(inputId, minimumValue) {
            const input = document.getElementById(inputId);

            if (!input) {
                return;
            }

            function syncCapacityValue() {
                if (input.disabled) {
                    input.setCustomValidity('');
                    return;
                }

                const digitsOnly = input.value.replace(/[^0-9]/g, '');

                if (input.value !== digitsOnly) {
                    input.value = digitsOnly;
                }

                if (digitsOnly === '') {
                    input.setCustomValidity('Capacity must be a natural number.');
                    return;
                }

                if (!/^[0-9]+$/.test(digitsOnly)) {
                    input.setCustomValidity('Capacity must be a natural number using digits only.');
                    return;
                }

                if (Number(digitsOnly) < minimumValue) {
                    input.setCustomValidity(minimumValue > 1 ? 'Capacity cannot be lower than existing bookings.' : 'Capacity must be at least 1.');
                    return;
                }

                input.setCustomValidity('');
            }

            input.addEventListener('input', syncCapacityValue);
            input.addEventListener('paste', function () {
                window.setTimeout(syncCapacityValue, 0);
            });
            syncCapacityValue();
        }

        function bindSessionTimeConstraint(dateInputId, timeInputId) {
            const dateInput = document.getElementById(dateInputId);
            const timeInput = document.getElementById(timeInputId);

            if (!dateInput || !timeInput) {
                return;
            }

            function syncTimeConstraint() {
                if (timeInput.disabled) {
                    return;
                }

                if (dateInput.value === today) {
                    const minTime = currentTimeValue();
                    timeInput.min = minTime;

                    if (timeInput.value && timeInput.value < minTime) {
                        timeInput.setCustomValidity('Session time cannot be in the past.');
                    } else {
                        timeInput.setCustomValidity('');
                    }
                } else {
                    timeInput.removeAttribute('min');
                    timeInput.setCustomValidity('');
                }
            }

            dateInput.addEventListener('input', syncTimeConstraint);
            timeInput.addEventListener('input', syncTimeConstraint);
            syncTimeConstraint();
        }

        bindNaturalNumberInput('new-capacity', 1);
        bindNaturalNumberInput('edit-capacity', editCapacityMinimum);
        bindSessionTimeConstraint('new-date', 'new-time');
        bindSessionTimeConstraint('edit-date', 'edit-time');
    })();
</script>
</body>
</html>
