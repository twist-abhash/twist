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
    <title>My Appointments</title>
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

function appointment_page_notice($notice)
{
    $map = [
        'appointment-cancelled' => [
            'type' => 'success',
            'message' => 'Appointment cancelled successfully.',
        ],
        'appointment-completed' => [
            'type' => 'success',
            'message' => 'Appointment marked as completed and the checkup result was saved.',
        ],
        'appointment-result-required' => [
            'type' => 'warning',
            'message' => 'Add the result of the check up before finishing the appointment.',
        ],
        'appointment-result-too-long' => [
            'type' => 'warning',
            'message' => 'The checkup result is too long. Shorten it and try again.',
        ],
        'appointment-not-ready' => [
            'type' => 'warning',
            'message' => 'That appointment cannot be completed yet. It must have already started and still belong to your schedule.',
        ],
        'appointment-locked' => [
            'type' => 'warning',
            'message' => 'Completed appointments are locked and can no longer be changed from the doctor panel.',
        ],
        'invalid-appointment' => [
            'type' => 'warning',
            'message' => 'That appointment could not be found or does not belong to your account.',
        ],
        'appointment-error' => [
            'type' => 'error',
            'message' => 'The appointment action could not be completed. Please try again.',
        ],
    ];

    return $map[$notice] ?? null;
}

function appointment_matches_filter(array $appointment, $statusFilter, $today)
{
    if ($statusFilter === 'all') {
        return true;
    }

    if ($statusFilter === 'today') {
        return ($appointment['scheduledate'] ?? '') === $today;
    }

    if ($statusFilter === 'upcoming') {
        return ($appointment['workflow']['code'] ?? '') === 'upcoming';
    }

    return ($appointment['workflow']['code'] ?? '') === 'completed';
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
$currentMoment = new DateTimeImmutable('now', new DateTimeZone('Asia/Kathmandu'));

$allowedStatuses = ['all', 'today', 'upcoming', 'completed'];
$searchTerm = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$patientId = (int)($_GET['patient_id'] ?? 0);
$action = trim($_GET['action'] ?? '');
$recordId = (int)($_GET['id'] ?? 0);
$notice = trim($_GET['notice'] ?? '');

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

if ($patientId > 0 && $searchTerm === '') {
    $patientLookupStmt = doc_sathi_prepare(
        $database,
        "SELECT pname
         FROM patient
         WHERE pid = ?
         LIMIT 1"
    );
    $patientLookupStmt->bind_param("i", $patientId);
    doc_sathi_execute($patientLookupStmt);
    $patientLookup = $patientLookupStmt->get_result()->fetch_assoc();
    if ($patientLookup) {
        $searchTerm = trim((string)$patientLookup['pname']);
    }
}

$filterState = [
    'search' => $searchTerm,
    'date' => $dateFilter,
    'status' => $statusFilter !== 'all' ? $statusFilter : '',
    'patient_id' => $patientId > 0 ? $patientId : '',
];
$baseReturnUrl = doctor_dashboard_build_url('appointment.php', $filterState);
$filtersActive = $searchTerm !== '' || $dateFilter !== '' || $statusFilter !== 'all' || $patientId > 0;
$flashNotice = appointment_page_notice($notice);

$statsStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN s.scheduledate = ? AND COALESCE(a.status, 'confirmed') <> 'completed' THEN 1 ELSE 0 END), 0) AS today_total,
            COALESCE(SUM(CASE WHEN s.scheduledate > ? AND COALESCE(a.status, 'confirmed') <> 'completed' THEN 1 ELSE 0 END), 0) AS upcoming_total,
            COALESCE(SUM(CASE WHEN COALESCE(a.status, 'confirmed') = 'completed' THEN 1 ELSE 0 END), 0) AS completed_total
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?"
);
$statsStmt->bind_param("ssi", $today, $today, $userid);
doc_sathi_execute($statsStmt);
$stats = $statsStmt->get_result()->fetch_assoc() ?: [];

$appointmentsSql = "SELECT a.appoid,
                           a.apponum,
                           a.appodate,
                           a.status,
                           a.completed_at,
                           a.completed_by,
                           a.checkup_result,
                           s.scheduleid,
                           s.title,
                           s.scheduledate,
                           s.scheduletime,
                           s.duration_minutes,
                           s.end_time,
                           p.pid,
                           p.pname,
                           p.pemail,
                           p.pnum
                    FROM appointment a
                    INNER JOIN schedule s ON s.scheduleid = a.scheduleid
                    INNER JOIN patient p ON p.pid = a.pid
                    WHERE s.docid = ?";
$appointmentTypes = "i";
$appointmentParams = [$userid];

if ($patientId > 0) {
    $appointmentsSql .= " AND p.pid = ?";
    $appointmentTypes .= "i";
    $appointmentParams[] = $patientId;
}

if ($searchTerm !== '') {
    $appointmentsSql .= " AND p.pname LIKE ?";
    $appointmentTypes .= "s";
    $appointmentParams[] = doc_sathi_search_pattern($searchTerm);
}

if ($dateFilter !== '') {
    $appointmentsSql .= " AND s.scheduledate = ?";
    $appointmentTypes .= "s";
    $appointmentParams[] = $dateFilter;
}

$appointmentsSql .= " ORDER BY
                        CASE WHEN s.scheduledate >= ? THEN 0 ELSE 1 END,
                        s.scheduledate ASC,
                        s.scheduletime ASC,
                        a.apponum ASC";
$appointmentTypes .= "s";
$appointmentParams[] = $today;

$appointmentsStmt = doc_sathi_prepare($database, $appointmentsSql);
doctor_dashboard_bind_params($appointmentsStmt, $appointmentTypes, $appointmentParams);
doc_sathi_execute($appointmentsStmt);
$appointmentsResult = $appointmentsStmt->get_result();
$appointments = [];

while ($row = $appointmentsResult->fetch_assoc()) {
    $row['duration_minutes'] = doc_sathi_schedule_duration_minutes($row);
    $row['end_time'] = doc_sathi_schedule_end_time($row);
    $row['workflow'] = doc_sathi_appointment_status_details($row, $currentMoment);

    if (appointment_matches_filter($row, $statusFilter, $today)) {
        $appointments[] = $row;
    }
}

$activeDialog = null;
if ($recordId > 0 && in_array($action, ['view', 'drop', 'finish'], true)) {
    $dialogStmt = doc_sathi_prepare(
        $database,
        "SELECT a.appoid,
                a.apponum,
                a.appodate,
                a.status,
                a.completed_at,
                a.completed_by,
                a.checkup_result,
                s.scheduleid,
                s.title,
                s.scheduledate,
                s.scheduletime,
                s.duration_minutes,
                s.end_time,
                p.pid,
                p.pname,
                p.pemail,
                p.pnum,
                p.pdob,
                p.paddress,
                completer.docname AS completed_by_name
         FROM appointment a
         INNER JOIN schedule s ON s.scheduleid = a.scheduleid
         INNER JOIN patient p ON p.pid = a.pid
         LEFT JOIN doctor completer ON completer.docid = a.completed_by
         WHERE s.docid = ?
           AND a.appoid = ?
         LIMIT 1"
    );
    $dialogStmt->bind_param("ii", $userid, $recordId);
    doc_sathi_execute($dialogStmt);
    $activeDialog = $dialogStmt->get_result()->fetch_assoc();

    if ($activeDialog) {
        $activeDialog['duration_minutes'] = doc_sathi_schedule_duration_minutes($activeDialog);
        $activeDialog['end_time'] = doc_sathi_schedule_end_time($activeDialog);
        $activeDialog['workflow'] = doc_sathi_appointment_status_details($activeDialog, $currentMoment);

        if ((int)($activeDialog['completed_by'] ?? 0) === $userid && trim((string)($activeDialog['completed_by_name'] ?? '')) === '') {
            $activeDialog['completed_by_name'] = $username;
        }

        if ($action === 'finish' && !$activeDialog['workflow']['can_finish']) {
            $flashNotice = appointment_page_notice('appointment-not-ready');
            $action = '';
        }

        if ($action === 'drop' && !$activeDialog['workflow']['can_cancel']) {
            $flashNotice = appointment_page_notice('appointment-locked');
            $action = '';
        }
    }
}

if ($recordId > 0 && in_array($action, ['view', 'drop', 'finish'], true) && !$activeDialog) {
    $flashNotice = appointment_page_notice('invalid-appointment');
    $action = '';
}
?>

<div class="container doctor-dashboard-layout">
    <?php doctor_dashboard_sidebar('appointments', $username, $useremail); ?>

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
                        <h1>My Appointments</h1>
                        <p>Manage booked patient appointments and close visits once consultations are complete.</p>
                    </div>
                </div>
                <aside class="doctor-date-card">
                    <span>Today's Date</span>
                    <strong><?php echo doctor_dashboard_h(date('M d, Y')); ?></strong>
                </aside>
            </header>

            <section class="doctor-stats-grid doctor-page-stats" aria-label="Appointment summary">
                <article class="doctor-stat-card">
                    <span>Total Appointments</span>
                    <strong><?php echo (int)($stats['total'] ?? 0); ?></strong>
                    <p>All booked appointments linked to your sessions.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Today's Appointments</span>
                    <strong><?php echo (int)($stats['today_total'] ?? 0); ?></strong>
                    <p>Appointments still active on today's schedule.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Upcoming</span>
                    <strong><?php echo (int)($stats['upcoming_total'] ?? 0); ?></strong>
                    <p>Future appointments that have not been completed yet.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Completed</span>
                    <strong><?php echo (int)($stats['completed_total'] ?? 0); ?></strong>
                    <p>Appointments finished from the doctor panel.</p>
                </article>
            </section>

            <section class="doctor-page-card">
                <div class="doctor-card-header">
                    <div>
                        <h2>Filter Appointments</h2>
                        <p>Search appointments by patient, session date, or workflow status.</p>
                    </div>
                </div>

                <?php if ($patientId > 0): ?>
                    <div class="doctor-inline-note" style="margin-bottom:14px;">
                        Viewing exact history for patient ID P-<?php echo $patientId; ?>.
                    </div>
                <?php endif; ?>

                <form action="appointment.php" method="get" class="doctor-toolbar appointments-toolbar">
                    <div class="doctor-field">
                        <label for="appointment-date">Date</label>
                        <input
                            id="appointment-date"
                            type="date"
                            name="date"
                            value="<?php echo doctor_dashboard_h($dateFilter); ?>"
                            class="doctor-input"
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="appointment-search">Patient Name</label>
                        <input
                            id="appointment-search"
                            type="search"
                            name="search"
                            value="<?php echo doctor_dashboard_h($searchTerm); ?>"
                            class="doctor-input"
                            placeholder="Search by patient name"
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="appointment-status">Status</label>
                        <select id="appointment-status" name="status" class="doctor-select">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                            <option value="today" <?php echo $statusFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="upcoming" <?php echo $statusFilter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div class="doctor-toolbar-actions">
                        <button type="submit" class="doctor-btn">Filter</button>
                    </div>

                    <div class="doctor-toolbar-actions">
                        <a href="appointment.php" class="doctor-btn secondary">Reset</a>
                    </div>
                </form>
            </section>

            <section class="doctor-page-card compact">
                <div class="doctor-table-card-header">
                    <div class="doctor-card-header">
                        <div>
                            <h2>Appointment List</h2>
                            <p><?php echo count($appointments); ?> appointment<?php echo count($appointments) === 1 ? '' : 's'; ?> shown.</p>
                        </div>
                    </div>
                </div>

                <?php if (empty($appointments)): ?>
                    <div class="doctor-empty-state">
                        <div class="doctor-empty-icon">A</div>
                        <h3>No appointments found</h3>
                        <p>
                            <?php echo $filtersActive
                                ? 'No appointments matched the current filters. Adjust the date, patient, or status filters and try again.'
                                : 'Booked patient appointments will appear here once patients reserve one of your sessions.'; ?>
                        </p>
                        <?php if ($filtersActive): ?>
                            <a href="appointment.php" class="doctor-btn secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="doctor-table-wrap">
                        <table class="doctor-data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Appointment No.</th>
                                    <th>Session Title</th>
                                    <th>Session Date &amp; Time</th>
                                    <th>Appointment Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <div class="doctor-primary-cell">
                                                <div class="doctor-avatar"><?php echo doctor_dashboard_h(doctor_dashboard_initials($appointment['pname'])); ?></div>
                                                <div class="doctor-primary-text">
                                                    <strong><?php echo doctor_dashboard_h($appointment['pname']); ?></strong>
                                                    <span><?php echo doctor_dashboard_h($appointment['pemail']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong>#<?php echo (int)$appointment['apponum']; ?></strong>
                                                <span>Booking reference</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong><?php echo doctor_dashboard_h($appointment['title']); ?></strong>
                                                <span>Session ID S-<?php echo (int)$appointment['scheduleid']; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($appointment['scheduledate'])); ?></strong>
                                                <span>
                                                    <?php
                                                    echo doctor_dashboard_h(
                                                        doctor_dashboard_format_time($appointment['scheduletime'])
                                                        . ' - '
                                                        . doctor_dashboard_format_time($appointment['end_time'])
                                                    );
                                                    ?>
                                                </span>
                                                <small><?php echo doctor_dashboard_h(doc_sathi_session_duration_label($appointment['duration_minutes'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="doctor-metric">
                                                <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($appointment['appodate'])); ?></strong>
                                                <span>Booked on</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="doctor-status-badge <?php echo doctor_dashboard_h($appointment['workflow']['tone']); ?>">
                                                <?php echo doctor_dashboard_h($appointment['workflow']['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="doctor-table-actions">
                                                <a
                                                    href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('appointment.php', $filterState, ['action' => 'view', 'id' => (int)$appointment['appoid']])); ?>"
                                                    class="doctor-btn small ghost"
                                                >
                                                    View
                                                </a>
                                                <?php if ($appointment['workflow']['can_finish']): ?>
                                                    <a
                                                        href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('appointment.php', $filterState, ['action' => 'finish', 'id' => (int)$appointment['appoid']])); ?>"
                                                        class="doctor-btn small"
                                                    >
                                                        Finish
                                                    </a>
                                                <?php elseif ($appointment['workflow']['code'] === 'completed'): ?>
                                                    <span class="doctor-btn small disabled">Closed</span>
                                                <?php elseif ($appointment['workflow']['can_cancel']): ?>
                                                    <a
                                                        href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('appointment.php', $filterState, ['action' => 'drop', 'id' => (int)$appointment['appoid']])); ?>"
                                                        class="doctor-btn small danger"
                                                    >
                                                        Cancel
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

<?php if ($action === 'view' && $activeDialog): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog wide doctor-appointment-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Appointment Details</h2>
                <p>Review patient details, the consultation window, and completion status for this appointment.</p>
            </div>
            <div class="doctor-dialog-body">
                <div class="doctor-dialog-grid doctor-appointment-dialog-grid">
                    <div class="doctor-dialog-item">
                        <span>Patient</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['pname']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Appointment Number</span>
                        <strong>#<?php echo (int)$activeDialog['apponum']; ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Session Title</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['title']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Session Date</span>
                        <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($activeDialog['scheduledate'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Time Window</span>
                        <strong>
                            <?php
                            echo doctor_dashboard_h(
                                doctor_dashboard_format_time($activeDialog['scheduletime'])
                                . ' - '
                                . doctor_dashboard_format_time($activeDialog['end_time'])
                            );
                            ?>
                        </strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Duration</span>
                        <strong><?php echo doctor_dashboard_h(doc_sathi_session_duration_label($activeDialog['duration_minutes'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Appointment Date</span>
                        <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($activeDialog['appodate'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Status</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['workflow']['label']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Email</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['pemail']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Phone</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['pnum']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Date of Birth</span>
                        <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($activeDialog['pdob'])); ?></strong>
                    </div>
                    <?php if (($activeDialog['workflow']['code'] ?? '') === 'completed'): ?>
                        <div class="doctor-dialog-item">
                            <span>Completed At</span>
                            <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_datetime(substr((string)$activeDialog['completed_at'], 0, 10), substr((string)$activeDialog['completed_at'], 11, 8), 'Recorded')); ?></strong>
                        </div>
                        <div class="doctor-dialog-item">
                            <span>Completed By</span>
                            <strong><?php echo doctor_dashboard_h(trim((string)($activeDialog['completed_by_name'] ?? '')) ?: $username); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if (($activeDialog['workflow']['code'] ?? '') === 'completed'): ?>
                        <div class="doctor-dialog-item full">
                            <span>Result of the Check Up</span>
                            <strong style="white-space:pre-wrap;line-height:1.7;">
                                <?php echo nl2br(doctor_dashboard_h(trim((string)($activeDialog['checkup_result'] ?? '')) ?: 'Not recorded.')); ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                    <div class="doctor-dialog-item full">
                        <span>Address</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['paddress'] ?: 'Not available'); ?></strong>
                    </div>
                </div>

                <div class="doctor-dialog-actions">
                    <?php if ($activeDialog['workflow']['can_finish']): ?>
                        <a
                            href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('appointment.php', $filterState, ['action' => 'finish', 'id' => (int)$activeDialog['appoid']])); ?>"
                            class="doctor-btn"
                        >
                            Mark as Completed
                        </a>
                    <?php elseif ($activeDialog['workflow']['can_cancel']): ?>
                        <a
                            href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('appointment.php', $filterState, ['action' => 'drop', 'id' => (int)$activeDialog['appoid']])); ?>"
                            class="doctor-btn danger"
                        >
                            Cancel Appointment
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($action === 'finish' && $activeDialog): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Finish Appointment</h2>
                <p>Confirm that the consultation is complete. Add the result of the check up before closing the appointment.</p>
            </div>
            <div class="doctor-dialog-body">
                <div class="doctor-dialog-grid">
                    <div class="doctor-dialog-item">
                        <span>Patient</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['pname']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Appointment Number</span>
                        <strong>#<?php echo (int)$activeDialog['apponum']; ?></strong>
                    </div>
                    <div class="doctor-dialog-item full">
                        <span>Consultation Window</span>
                        <strong>
                            <?php
                            echo doctor_dashboard_h(
                                $activeDialog['title']
                                . ' on '
                                . doctor_dashboard_format_date($activeDialog['scheduledate'])
                                . ' from '
                                . doctor_dashboard_format_time($activeDialog['scheduletime'])
                                . ' to '
                                . doctor_dashboard_format_time($activeDialog['end_time'])
                            );
                            ?>
                        </strong>
                    </div>
                </div>

                <form action="complete-appointment.php" method="post">
                    <input type="hidden" name="appoid" value="<?php echo (int)$activeDialog['appoid']; ?>">
                    <input type="hidden" name="return" value="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">
                    <div class="doctor-field" style="margin-top:18px;">
                        <label for="checkup_result">Result of the Check Up</label>
                        <textarea
                            name="checkup_result"
                            id="checkup_result"
                            class="doctor-textarea"
                            rows="6"
                            maxlength="<?php echo (int)doc_sathi_checkup_result_max_length(); ?>"
                            placeholder="Write the consultation summary, findings, advice, medicines, or next steps."
                            required
                        ></textarea>
                    </div>
                    <div class="doctor-dialog-actions">
                        <button type="submit" class="doctor-btn">Confirm Completion</button>
                        <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Keep Open</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php elseif ($action === 'drop' && $activeDialog): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Cancel Appointment</h2>
                <p>Confirm that you want to cancel this booked appointment. Completed appointments are not cancellable from the doctor panel.</p>
            </div>
            <div class="doctor-dialog-body">
                <div class="doctor-dialog-grid">
                    <div class="doctor-dialog-item">
                        <span>Patient</span>
                        <strong><?php echo doctor_dashboard_h($activeDialog['pname']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Appointment Number</span>
                        <strong>#<?php echo (int)$activeDialog['apponum']; ?></strong>
                    </div>
                    <div class="doctor-dialog-item full">
                        <span>Session</span>
                        <strong>
                            <?php
                            echo doctor_dashboard_h(
                                $activeDialog['title']
                                . ' on '
                                . doctor_dashboard_format_date($activeDialog['scheduledate'])
                                . ' from '
                                . doctor_dashboard_format_time($activeDialog['scheduletime'])
                                . ' to '
                                . doctor_dashboard_format_time($activeDialog['end_time'])
                            );
                            ?>
                        </strong>
                    </div>
                </div>

                <div class="doctor-dialog-actions">
                    <a
                        href="<?php echo doctor_dashboard_h('delete-appointment.php?id=' . (int)$activeDialog['appoid'] . '&return=' . urlencode($baseReturnUrl)); ?>"
                        class="doctor-btn danger"
                    >
                        Confirm Cancellation
                    </a>
                    <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn secondary">Keep Appointment</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
