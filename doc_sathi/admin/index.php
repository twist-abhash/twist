<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css?v=20260421-admin-pages">

    <title>Admin Dashboard</title>
    <style>
        .admin-dashboard {
            animation: transitionIn-Y-over 0.5s;
        }
    </style>
</head>
<body class="admin-dashboard-page">
<?php
require_once __DIR__ . "/../session_config.php";
session_start();

if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_count(mysqli $database, string $sql): int
{
    $result = $database->query($sql);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)($row["total"] ?? 0);
}

$useremail = $_SESSION["user"];

date_default_timezone_set('Asia/Kathmandu');
$today = date('Y-m-d');
$nextweek = date("Y-m-d", strtotime("+1 week"));
$nextWeekLabel = date("M d", strtotime("+1 week"));

$totalDoctors = admin_count($database, "SELECT COUNT(*) AS total FROM doctor");
$totalPatients = admin_count($database, "SELECT COUNT(*) AS total FROM patient");
$newBookings = admin_count($database, "SELECT COUNT(*) AS total FROM appointment WHERE appodate >= '$today'");
$todaysSessions = admin_count($database, "SELECT COUNT(*) AS total FROM schedule WHERE scheduledate = '$today'");
$pendingVerifications = admin_count($database, "SELECT COUNT(*) AS total FROM doctor WHERE verification_status = 'pending'");
$todaysAppointments = admin_count(
    $database,
    "SELECT COUNT(*) AS total
     FROM appointment
     INNER JOIN schedule ON schedule.scheduleid = appointment.scheduleid
     WHERE schedule.scheduledate = '$today'"
);

$doctorOptions = $database->query("SELECT docname, docemail FROM doctor ORDER BY docname ASC");

$upcomingAppointments = $database->query(
    "SELECT appointment.appoid,
            schedule.scheduleid,
            schedule.title,
            doctor.docname,
            patient.pname,
            schedule.scheduledate,
            schedule.scheduletime,
            appointment.apponum,
            appointment.appodate
     FROM schedule
     INNER JOIN appointment ON schedule.scheduleid = appointment.scheduleid
     INNER JOIN patient ON patient.pid = appointment.pid
     INNER JOIN doctor ON schedule.docid = doctor.docid
     WHERE schedule.scheduledate >= '$today'
       AND schedule.scheduledate <= '$nextweek'
     ORDER BY schedule.scheduledate ASC, schedule.scheduletime ASC"
);

$upcomingSessions = $database->query(
    "SELECT schedule.scheduleid,
            schedule.title,
            doctor.docname,
            schedule.scheduledate,
            schedule.scheduletime,
            schedule.nop
     FROM schedule
     INNER JOIN doctor ON schedule.docid = doctor.docid
     WHERE schedule.scheduledate >= '$today'
       AND schedule.scheduledate <= '$nextweek'
     ORDER BY schedule.scheduledate ASC, schedule.scheduletime ASC"
);
?>

<div class="container admin-dashboard-layout">
    <aside class="menu">
        <table class="menu-container" border="0">
            <tr>
                <td class="admin-profile-cell" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" class="admin-avatar-cell">
                                <img src="../img/user.png" alt="Admin profile" width="100%">
                            </td>
                            <td class="admin-profile-copy">
                                <p class="profile-title">Administrator</p>
                                <p class="profile-subtitle"><?php echo h(substr($useremail, 0, 28)); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php?role=a"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-dashbord menu-active menu-icon-dashbord-active">
                    <a href="index.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Dashboard</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctor-verifications.php" class="non-style-link-menu"><div><p class="menu-text">Verifications</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-schedule">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Sessions</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointments</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-patient">
                    <a href="patient.php" class="non-style-link-menu"><div><p class="menu-text">Patients</p></div></a>
                </td>
            </tr>
        </table>
    </aside>

    <main class="dash-body admin-dashboard-body">
        <div class="admin-dashboard">
            <header class="admin-page-header">
                <div class="admin-title-block">
                    <span class="admin-eyebrow">Control Panel</span>
                    <h1>Admin Dashboard</h1>
                    <p>Monitor doctors, patients, verifications, appointments, and sessions.</p>
                </div>

                <form action="doctors.php" method="post" class="admin-header-search">
                    <input type="search" name="search" class="input-text admin-search-input" placeholder="Search doctor name or email" list="doctors">
                    <?php
                    echo '<datalist id="doctors">';
                    if ($doctorOptions) {
                        while ($doctor = $doctorOptions->fetch_assoc()) {
                            echo '<option value="' . h($doctor["docname"]) . '">';
                            echo '<option value="' . h($doctor["docemail"]) . '">';
                        }
                    }
                    echo '</datalist>';
                    ?>
                    <button type="submit" class="admin-btn">Search</button>
                </form>

                <aside class="admin-date-card" aria-label="Today">
                    <span>Today's Date</span>
                    <strong><?php echo h(date('M d, Y')); ?></strong>
                </aside>
            </header>

            <section class="admin-stats-grid" aria-label="Dashboard summary">
                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Total Doctors</span>
                        <strong><?php echo $totalDoctors; ?></strong>
                        <p>Doctor profiles registered in the system.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-doctors" aria-hidden="true"></span>
                </article>

                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Total Patients</span>
                        <strong><?php echo $totalPatients; ?></strong>
                        <p>Patient accounts available for appointment booking.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-patients" aria-hidden="true"></span>
                </article>

                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">New Bookings</span>
                        <strong><?php echo $newBookings; ?></strong>
                        <p>Appointments booked from today onward.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-bookings" aria-hidden="true"></span>
                </article>

                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Today&rsquo;s Sessions</span>
                        <strong><?php echo $todaysSessions; ?></strong>
                        <p>Sessions scheduled for the current day.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-sessions" aria-hidden="true"></span>
                </article>

                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Pending Verifications</span>
                        <strong><?php echo $pendingVerifications; ?></strong>
                        <p>Doctor registrations awaiting admin review.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-verifications" aria-hidden="true"></span>
                </article>

                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Today&rsquo;s Appointments</span>
                        <strong><?php echo $todaysAppointments; ?></strong>
                        <p>Patient visits attached to today&rsquo;s sessions.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-calendar" aria-hidden="true"></span>
                </article>
            </section>

            <section class="admin-quick-actions" aria-label="Quick actions">
                <a href="doctors.php" class="admin-action-card">
                    <strong>Manage Doctors</strong>
                    <span>Review doctor records and verification status.</span>
                </a>
                <a href="doctor-verifications.php" class="admin-action-card">
                    <strong>Review Verifications</strong>
                    <span>Approve or reject submitted doctor documents.</span>
                </a>
                <a href="appointment.php" class="admin-action-card">
                    <strong>Manage Appointments</strong>
                    <span>View appointment numbers, patients, and sessions.</span>
                </a>
                <a href="schedule.php" class="admin-action-card">
                    <strong>Manage Sessions</strong>
                    <span>Create, review, and remove doctor sessions.</span>
                </a>
            </section>

            <section class="admin-panel-grid">
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span class="admin-panel-kicker">Next 7 days</span>
                            <h2>Upcoming Appointments</h2>
                            <p>Patient bookings scheduled through <?php echo h($nextWeekLabel); ?>.</p>
                        </div>
                        <a href="appointment.php" class="admin-link-button">Show all Appointments</a>
                    </div>

                    <div class="admin-table-wrap">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>Appointment No.</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$upcomingAppointments || $upcomingAppointments->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="admin-empty-state">
                                                <strong>No upcoming appointments found.</strong>
                                                <span>Appointments scheduled in the next 7 days will appear here.</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($appointment = $upcomingAppointments->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="admin-number-pill"><?php echo h($appointment["apponum"]); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo h(substr($appointment["pname"], 0, 25)); ?></strong>
                                                <small><?php echo h($appointment["scheduledate"]); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo h(substr($appointment["docname"], 0, 25)); ?></strong>
                                                <small><?php echo h(substr($appointment["scheduletime"], 0, 5)); ?></small>
                                            </td>
                                            <td><?php echo h(substr($appointment["title"], 0, 24)); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span class="admin-panel-kicker">Next 7 days</span>
                            <h2>Upcoming Sessions</h2>
                            <p>Doctor sessions scheduled through <?php echo h($nextWeekLabel); ?>.</p>
                        </div>
                        <a href="schedule.php" class="admin-link-button">Show all Sessions</a>
                    </div>

                    <div class="admin-table-wrap">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Doctor</th>
                                    <th>Date &amp; Time</th>
                                    <th>Capacity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$upcomingSessions || $upcomingSessions->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="admin-empty-state">
                                                <strong>No upcoming sessions found.</strong>
                                                <span>Sessions scheduled in the next 7 days will appear here.</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php while ($session = $upcomingSessions->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo h(substr($session["title"], 0, 30)); ?></strong>
                                            </td>
                                            <td><?php echo h(substr($session["docname"], 0, 24)); ?></td>
                                            <td>
                                                <strong><?php echo h($session["scheduledate"]); ?></strong>
                                                <small><?php echo h(substr($session["scheduletime"], 0, 5)); ?></small>
                                            </td>
                                            <td>
                                                <span class="admin-capacity-pill"><?php echo h($session["nop"]); ?> slots</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </main>
</div>
</body>
</html>
