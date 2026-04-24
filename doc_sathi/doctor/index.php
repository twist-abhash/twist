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
    <link rel="stylesheet" href="../css/doctor-account.css">
    <title>Doctor Dashboard</title>
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

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dashboard_count_from_stmt($stmt)
{
    doc_sathi_execute($stmt);
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row["total"] ?? 0);
}

function display_session_date($date)
{
    if (!$date) {
        return "Not scheduled";
    }

    return date("M d, Y", strtotime($date));
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
$today = date("Y-m-d");
$currentTime = date('H:i:s');
$nextWeek = date("Y-m-d", strtotime("+7 days"));
$currentMoment = new DateTimeImmutable('now', new DateTimeZone('Asia/Kathmandu'));
$isApproved = doc_sathi_doctor_is_approved($doctor);
$verificationStatus = $doctor["verification_status"] ?? "pending";
$verificationLabel = doc_sathi_doctor_status_label($verificationStatus);
$documentPath = doc_sathi_doctor_verification_document_path($doctor);
$hasVerificationDocument = doc_sathi_doctor_has_verification_documents($database, $doctor);
$adminRemarks = trim((string)(($doctor["admin_remarks"] ?? "") ?: ($doctor["rejection_reason"] ?? "")));

if ($verificationStatus === "approved") {
    $verificationMessage = "Your account is verified. You can create sessions and receive appointments.";
    $verificationAction = "View Verification";
} elseif ($verificationStatus === "rejected") {
    $verificationMessage = "Your verification was rejected. Please review the remarks and resubmit your documents.";
    if ($adminRemarks !== "") {
        $verificationMessage .= " Remarks: " . $adminRemarks;
    }
    $verificationAction = "Resubmit Documents";
} elseif ($hasVerificationDocument) {
    $verificationMessage = "Your verification is under review. You can access your dashboard, but patients cannot book you yet.";
    $verificationAction = "Go to Verification";
} else {
    $verificationMessage = "Complete verification to make your sessions visible and bookable by patients.";
    $verificationAction = "Complete Verification";
}

$totalStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?"
);
$totalStmt->bind_param("i", $userid);
$totalAppointments = dashboard_count_from_stmt($totalStmt);

$upcomingSessionStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total
     FROM schedule
     WHERE docid = ?
       AND (
            scheduledate > ?
            OR (
                scheduledate = ?
                AND COALESCE(
                        end_time,
                        ADDTIME(
                            scheduletime,
                            SEC_TO_TIME(COALESCE(duration_minutes, " . doc_sathi_default_session_duration_minutes() . ") * 60)
                        )
                    ) > ?
            )
       )"
);
$upcomingSessionStmt->bind_param("isss", $userid, $today, $today, $currentTime);
$upcomingSessions = dashboard_count_from_stmt($upcomingSessionStmt);

$activePatientStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(DISTINCT a.pid) AS total
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?"
);
$activePatientStmt->bind_param("i", $userid);
$activePatients = dashboard_count_from_stmt($activePatientStmt);

$todayAppointmentStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?
       AND s.scheduledate = ?
       AND COALESCE(a.status, 'confirmed') <> 'completed'"
);
$todayAppointmentStmt->bind_param("is", $userid, $today);
$todayAppointments = dashboard_count_from_stmt($todayAppointmentStmt);

$completedAppointmentStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?
       AND COALESCE(a.status, 'confirmed') = 'completed'"
);
$completedAppointmentStmt->bind_param("i", $userid);
$completedAppointments = dashboard_count_from_stmt($completedAppointmentStmt);

$upcomingBookingStmt = doc_sathi_prepare(
    $database,
    "SELECT COUNT(*) AS total
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE s.docid = ?
       AND s.scheduledate >= ?
       AND COALESCE(a.status, 'confirmed') <> 'completed'"
);
$upcomingBookingStmt->bind_param("is", $userid, $today);
$upcomingAppointments = dashboard_count_from_stmt($upcomingBookingStmt);

$sessionsStmt = doc_sathi_prepare(
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
       AND s.scheduledate BETWEEN ? AND ?
     GROUP BY s.scheduleid, s.title, s.scheduledate, s.scheduletime, s.duration_minutes, s.end_time, s.nop
     ORDER BY s.scheduledate ASC, s.scheduletime ASC
     LIMIT 8"
);
$sessionsStmt->bind_param("iss", $userid, $today, $nextWeek);
doc_sathi_execute($sessionsStmt);
$upcomingSessionRows = $sessionsStmt->get_result();
?>

<div class="container doctor-dashboard-layout">
    <?php doctor_dashboard_sidebar('dashboard', $username, $useremail); ?>

    <div class="dash-body doctor-dashboard-body">
        <main class="doctor-page-shell">
            <header class="doctor-page-header">
                <div class="doctor-page-header-main">
                    <div class="doctor-page-title-block">
                        <h1>Dashboard</h1>
                        <p>Welcome back, Dr. <?php echo h($username); ?>. Here is your clinical workspace for today.</p>
                    </div>
                </div>
                <aside class="doctor-date-card">
                    <span>Today's Date</span>
                    <strong><?php echo h(date('M d, Y')); ?></strong>
                </aside>
            </header>

            <section class="verification-banner <?php echo h($verificationStatus); ?>">
                <div>
                    <span class="doctor-badge <?php echo h($verificationStatus); ?>"><?php echo h($verificationLabel); ?></span>
                    <h2>Verification Summary</h2>
                    <p><?php echo h($verificationMessage); ?></p>
                </div>
                <a href="verification.php" class="doctor-action-button"><?php echo h($verificationAction); ?></a>
            </section>

            <?php if ($verificationStatus === "rejected" && $adminRemarks !== ""): ?>
                <div class="doctor-alert error">
                    <strong>Rejected Reason:</strong> <?php echo h($adminRemarks); ?>
                </div>
            <?php endif; ?>

            <section class="doctor-page-stats">
                <div class="doctor-stats-grid" aria-label="Doctor statistics">
                    <article class="doctor-stat-card">
                        <span>Total Appointments</span>
                        <strong><?php echo $totalAppointments; ?></strong>
                        <p>All bookings linked to your sessions.</p>
                    </article>
                    <article class="doctor-stat-card">
                        <span>Upcoming Sessions</span>
                        <strong><?php echo $upcomingSessions; ?></strong>
                        <p>Sessions scheduled from today onward.</p>
                    </article>
                    <article class="doctor-stat-card">
                        <span>Active Patients</span>
                        <strong><?php echo $activePatients; ?></strong>
                        <p>Unique patients who booked your sessions.</p>
                    </article>
                    <article class="doctor-stat-card">
                        <span>Today's Appointments</span>
                        <strong><?php echo $todayAppointments; ?></strong>
                        <p>Bookings for sessions scheduled today.</p>
                    </article>
                    <article class="doctor-stat-card">
                        <span>Upcoming Appointments</span>
                        <strong><?php echo $upcomingAppointments; ?></strong>
                        <p>Patient bookings for today and future sessions.</p>
                    </article>
                    <article class="doctor-stat-card">
                        <span>Completed Appointments</span>
                        <strong><?php echo $completedAppointments; ?></strong>
                        <p>Bookings that were explicitly finished from the doctor panel.</p>
                    </article>
                </div>
            </section>

            <section class="doctor-content-grid">
                <article class="doctor-page-card">
                    <div class="doctor-card-header">
                        <div>
                            <h2>Sessions for the Next 7 Days</h2>
                            <p>Review upcoming availability, booking volume, and patient-facing status.</p>
                        </div>
                        <?php if ($isApproved): ?>
                            <a href="schedule.php?action=add-session&id=none&error=0" class="doctor-btn">Add Session</a>
                        <?php else: ?>
                            <span class="doctor-btn disabled">Verification Required</span>
                        <?php endif; ?>
                    </div>

                    <div class="doctor-table-wrap">
                        <table class="doctor-data-table">
                            <thead>
                                <tr>
                                    <th>Session Title</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Bookings</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($upcomingSessionRows->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                No sessions are scheduled for the next 7 days.
                                                <?php if ($isApproved): ?>
                                                    <br><a href="schedule.php?action=add-session&id=none&error=0">Create your next session</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($session = $upcomingSessionRows->fetch_assoc()): ?>
                                        <?php
                                        $session["duration_minutes"] = doc_sathi_schedule_duration_minutes($session);
                                        $session["end_time"] = doc_sathi_schedule_end_time($session);
                                        $booked = (int)$session["booked"];
                                        $capacity = (int)$session["nop"];
                                        if (!$isApproved) {
                                            $sessionStatusClass = "locked";
                                            $sessionStatusLabel = "Not bookable";
                                        } elseif ($session["end_time"] !== "" && doc_sathi_session_has_ended($session["scheduledate"], $session["end_time"], $currentMoment)) {
                                            $sessionStatusClass = "completed";
                                            $sessionStatusLabel = "Completed";
                                        } elseif (doc_sathi_session_has_started($session["scheduledate"], $session["scheduletime"], $currentMoment)) {
                                            $sessionStatusClass = "today";
                                            $sessionStatusLabel = "In Progress";
                                        } elseif ($session["scheduledate"] === $today) {
                                            $sessionStatusClass = "today";
                                            $sessionStatusLabel = "Today";
                                        } elseif ($capacity > 0 && $booked >= $capacity) {
                                            $sessionStatusClass = "full";
                                            $sessionStatusLabel = "Full";
                                        } else {
                                            $sessionStatusClass = "open";
                                            $sessionStatusLabel = "Open";
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo h($session["title"]); ?></td>
                                            <td><?php echo h(display_session_date($session["scheduledate"])); ?></td>
                                            <td>
                                                <?php
                                                echo h(
                                                    doctor_dashboard_format_time($session["scheduletime"])
                                                    . " - "
                                                    . doctor_dashboard_format_time($session["end_time"])
                                                );
                                                ?>
                                                <br><small><?php echo h(doc_sathi_session_duration_label($session["duration_minutes"])); ?></small>
                                            </td>
                                            <td><?php echo $booked; ?> / <?php echo $capacity; ?></td>
                                            <td><span class="session-status <?php echo h($sessionStatusClass); ?>"><?php echo h($sessionStatusLabel); ?></span></td>
                                            <td><a href="schedule.php?action=view&id=<?php echo (int)$session["scheduleid"]; ?>">View</a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <aside class="doctor-page-card">
                    <div class="doctor-card-header">
                        <div>
                            <h2>Quick Actions</h2>
                            <p>Common tasks for managing your Doc Sathi practice.</p>
                        </div>
                    </div>

                    <div class="quick-actions">
                        <a href="appointment.php" class="quick-action-card">
                            <strong>View My Appointments</strong>
                            <span>Review booked patients and appointment numbers.</span>
                        </a>
                        <a href="schedule.php" class="quick-action-card">
                            <strong>Manage Sessions</strong>
                            <span><?php echo $isApproved ? "Create and review your availability." : "Session creation unlocks after verification."; ?></span>
                        </a>
                        <a href="verification.php" class="quick-action-card">
                            <strong><?php echo h($verificationAction); ?></strong>
                            <span>Check status, upload documents, or review admin remarks.</span>
                        </a>
                        <a href="account.php" class="quick-action-card">
                            <strong>Update Profile</strong>
                            <span>Keep your contact, specialty, and account details current.</span>
                        </a>
                    </div>
                </aside>
            </section>
        </main>
    </div>
</div>
</body>
</html>
