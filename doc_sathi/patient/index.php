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
    <link rel="stylesheet" href="../css/patient-dashboard.css">

    <title>Doc Sathi | Patient Dashboard</title>
</head>
<body class="patient-dashboard-page">
    <?php
    require_once __DIR__ . "/../session_config.php";
    require_once __DIR__ . "/../algorithms.php";
    require_once __DIR__ . "/patient-ui.php";

    session_start();

    if (isset($_SESSION["user"])) {
        if (($_SESSION["user"]) == "" || $_SESSION["usertype"] != "p") {
            header("location: ../login.php");
            exit();
        }
        $useremail = $_SESSION["user"];
    } else {
        header("location: ../login.php");
        exit();
    }

    include("../connection.php");

    function patient_dashboard_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }

    function patient_dashboard_format_date($value, $format = "M d, Y")
    {
        $timestamp = strtotime((string)$value);
        return $timestamp ? date($format, $timestamp) : "Not scheduled";
    }

    function patient_dashboard_format_time($value)
    {
        $timestamp = strtotime((string)$value);
        return $timestamp ? date("h:i A", $timestamp) : "Time pending";
    }

    function patient_dashboard_count_from_sql($database, $sql, $types = "", array $params = [])
    {
        if ($types === "") {
            $result = $database->query($sql);
            if (!$result) {
                return 0;
            }
            $row = $result->fetch_assoc();
            return (int)($row["total"] ?? 0);
        }

        $stmt = $database->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $bindParams = [$types];
        foreach ($params as $key => &$value) {
            $bindParams[] = &$value;
        }
        call_user_func_array([$stmt, "bind_param"], $bindParams);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row["total"] ?? 0);
    }

    function patient_dashboard_booking_badge($appointment, $today)
    {
        $statusDetails = doc_sathi_appointment_status_details($appointment);

        if (($statusDetails['code'] ?? '') === 'completed') {
            return ["label" => "Ended by Doctor", "class" => "past"];
        }

        if (($statusDetails['code'] ?? '') === 'pending_completion') {
            return ["label" => "Session Ended", "class" => "past"];
        }

        if (in_array($statusDetails['code'], ['today', 'in_progress'], true)) {
            return ["label" => $statusDetails['label'], "class" => "today"];
        }

        return ["label" => $statusDetails['label'], "class" => "upcoming"];
    }

    function patient_dashboard_compare_appointments(array $left, array $right)
    {
        $priorityMap = [
            "in_progress" => 0,
            "today" => 1,
            "upcoming" => 2,
            "pending_completion" => 3,
            "completed" => 4,
            "cancelled" => 5,
            "unscheduled" => 6,
        ];

        $leftCode = $left["workflow"]["code"] ?? "unscheduled";
        $rightCode = $right["workflow"]["code"] ?? "unscheduled";
        $leftPriority = $priorityMap[$leftCode] ?? 99;
        $rightPriority = $priorityMap[$rightCode] ?? 99;

        if ($leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }

        if ($leftCode === "completed" && $rightCode === "completed") {
            return strcmp((string)($right["completed_at"] ?? ""), (string)($left["completed_at"] ?? ""));
        }

        $leftTimestamp = doc_sathi_session_timestamp($left["scheduledate"] ?? "", $left["scheduletime"] ?? "") ?? PHP_INT_MAX;
        $rightTimestamp = doc_sathi_session_timestamp($right["scheduledate"] ?? "", $right["scheduletime"] ?? "") ?? PHP_INT_MAX;

        if ($leftTimestamp !== $rightTimestamp) {
            return $leftTimestamp <=> $rightTimestamp;
        }

        return ((int)($left["appoid"] ?? 0)) <=> ((int)($right["appoid"] ?? 0));
    }

    $sqlmain = "select * from patient where pemail=?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("s", $useremail);
    $stmt->execute();
    $userrow = $stmt->get_result();
    $userfetch = $userrow->fetch_assoc();

    if (!$userfetch) {
        header("location: ../logout.php?role=p");
        exit();
    }

    $userid = (int)$userfetch["pid"];
    $username = $userfetch["pname"];

    date_default_timezone_set("Asia/Kathmandu");
    $today = date("Y-m-d");

    $doctorCount = patient_dashboard_count_from_sql(
        $database,
        "SELECT COUNT(*) AS total FROM doctor WHERE verification_status='approved'"
    );
    $specialtyCount = patient_dashboard_count_from_sql(
        $database,
        "SELECT COUNT(*) AS total FROM specialties"
    );
    $upcomingBookingCount = patient_dashboard_count_from_sql(
        $database,
         "SELECT COUNT(*) AS total
          FROM appointment
          INNER JOIN schedule ON schedule.scheduleid=appointment.scheduleid
          WHERE appointment.pid=?
            AND schedule.scheduledate>=?
            AND COALESCE(appointment.status, 'confirmed') <> 'completed'",
         "is",
         [$userid, $today]
     );
    $todaySessionCount = patient_dashboard_count_from_sql(
        $database,
        "SELECT COUNT(*) AS total
         FROM schedule
         INNER JOIN doctor ON schedule.docid=doctor.docid
         WHERE schedule.scheduledate=? AND doctor.verification_status='approved'",
        "s",
        [$today]
    );

    $dashboardAppointmentStmt = $database->prepare(
         "SELECT appointment.appoid,
                 appointment.apponum,
                 appointment.appodate,
                 appointment.status,
                 appointment.completed_at,
                 appointment.checkup_result,
                 schedule.scheduleid,
                 schedule.title,
                 schedule.scheduledate,
                 schedule.scheduletime,
                 schedule.duration_minutes,
                 schedule.end_time,
                 doctor.docname
          FROM schedule
          INNER JOIN appointment ON schedule.scheduleid=appointment.scheduleid
          INNER JOIN patient ON patient.pid=appointment.pid
          INNER JOIN doctor ON schedule.docid=doctor.docid
          WHERE patient.pid=?
            AND COALESCE(appointment.status, 'confirmed') <> 'cancelled'"
    );
    $dashboardAppointmentStmt->bind_param("i", $userid);
    $dashboardAppointmentStmt->execute();
    $dashboardAppointmentResult = $dashboardAppointmentStmt->get_result();
    $dashboardAppointments = [];
    while ($appointment = $dashboardAppointmentResult->fetch_assoc()) {
        $appointment["workflow"] = doc_sathi_appointment_status_details($appointment);
        $dashboardAppointments[] = $appointment;
    }
    usort($dashboardAppointments, "patient_dashboard_compare_appointments");
    $dashboardAppointments = array_slice($dashboardAppointments, 0, 6);

    $recommendedDoctors = doc_sathi_get_recommended_doctors_for_patient($database, $userid, 5);
    $hasRecommendationHistory = false;
    if (!empty($recommendedDoctors)) {
        $hasRecommendationHistory = (bool)($recommendedDoctors[0]["recommendation_metrics"]["has_patient_history"] ?? false);
    }
    ?>
    <div class="container patient-dashboard-layout">
        <aside class="menu patient-sidebar" aria-label="Patient navigation">
            <table class="menu-container" border="0">
                <tr>
                    <td class="patient-profile-cell" colspan="2">
                        <table border="0" class="profile-container patient-profile-card">
                            <tr>
                                <td class="patient-avatar-cell">
                                    <img src="../img/user.png" alt="Patient avatar">
                                </td>
                                <td class="patient-profile-copy">
                                    <p class="profile-title" title="<?php echo patient_dashboard_h($username); ?>">
                                        <?php echo patient_dashboard_h($username); ?>
                                    </p>
                                    <p class="profile-subtitle" title="<?php echo patient_dashboard_h($useremail); ?>">
                                        <?php echo patient_dashboard_h($useremail); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php?role=p">
                                        <input type="button" value="Log out" class="logout-btn btn-primary-soft btn">
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-home menu-active menu-icon-home-active">
                        <a href="index.php" class="non-style-link-menu non-style-link-menu-active" aria-current="page"><div><p class="menu-text">Home</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="department.php" class="non-style-link-menu"><div><p class="menu-text">Specialties</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Sessions</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Bookings</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Account</p></div></a>
                    </td>
                </tr>
            </table>
        </aside>

        <main class="dash-body patient-dashboard-body">
            <div class="patient-dashboard-shell">
                <header class="patient-topbar">
                    <div>
                        <span class="patient-eyebrow">Patient Portal</span>
                        <h1>Dashboard</h1>
                    </div>
                    <div class="patient-date-card" aria-label="Today date">
                        <span>Today's Date</span>
                        <strong><?php echo patient_dashboard_h(patient_dashboard_format_date($today)); ?></strong>
                    </div>
                </header>

                <section class="patient-hero-card">
                    <div class="patient-hero-copy">
                        <span class="patient-eyebrow">Welcome back</span>
                        <h2><?php echo patient_dashboard_h($username); ?></h2>
                        <p>
                            Manage your care from one place. Find approved doctors, book available sessions, and keep track of your upcoming appointments.
                        </p>
                        <div class="patient-hero-actions" aria-label="Quick actions">
                            <a href="doctors.php" class="patient-btn primary">Find Doctors</a>
                            <a href="schedule.php" class="patient-btn secondary">Book a Session</a>
                            <a href="appointment.php" class="patient-btn ghost">View My Bookings</a>
                        </div>
                    </div>
                </section>

                <section class="patient-summary-section" aria-labelledby="summary-title">
                    <div class="patient-section-heading">
                        <div>
                            <span class="patient-eyebrow">Overview</span>
                            <h2 id="summary-title">Your Care Summary</h2>
                        </div>
                    </div>

                    <div class="patient-summary-grid">
                        <a href="doctors.php" class="patient-summary-card">
                            <span class="patient-card-icon doctors" aria-hidden="true"></span>
                            <strong><?php echo (int)$doctorCount; ?></strong>
                            <span>Available Doctors</span>
                            <p>Approved doctors ready for patient bookings.</p>
                        </a>
                        <a href="department.php" class="patient-summary-card">
                            <span class="patient-card-icon specialties" aria-hidden="true"></span>
                            <strong><?php echo (int)$specialtyCount; ?></strong>
                            <span>Specialties</span>
                            <p>Browse care categories and matching doctors.</p>
                        </a>
                        <a href="appointment.php" class="patient-summary-card">
                            <span class="patient-card-icon bookings" aria-hidden="true"></span>
                            <strong><?php echo (int)$upcomingBookingCount; ?></strong>
                            <span>Upcoming Bookings</span>
                            <p>Your scheduled appointments from today onward.</p>
                        </a>
                        <a href="schedule.php" class="patient-summary-card">
                            <span class="patient-card-icon sessions" aria-hidden="true"></span>
                            <strong><?php echo (int)$todaySessionCount; ?></strong>
                            <span>Today's Sessions</span>
                            <p>Doctor sessions available on today's date.</p>
                        </a>
                    </div>
                </section>

                <?php if (count($recommendedDoctors) > 0): ?>
                    <section class="patient-booking-panel patient-recommendation-panel" aria-labelledby="recommendation-title">
                        <div class="patient-section-heading">
                            <div>
                                <span class="patient-eyebrow">Recommended Care</span>
                                <h2 id="recommendation-title">Recommended Doctors</h2>
                                <p>
                                    <?php if ($hasRecommendationHistory): ?>
                                        Ranked from your repeated doctor and specialty booking history, then upcoming availability.
                                    <?php else: ?>
                                        You do not have enough booking history yet, so these are popular and available doctors to start with.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <a href="doctors.php" class="patient-link-button">Browse All</a>
                        </div>

                        <?php
                        echo patient_portal_doctor_list_html($recommendedDoctors, [
                            "source_href" => "doctors.php",
                            "reset_href" => "doctors.php",
                            "show_recommendation_reason" => true,
                            "show_reset" => false,
                        ]);
                        ?>
                    </section>
                <?php endif; ?>

                <section class="patient-booking-panel" aria-labelledby="booking-title">
                    <div class="patient-section-heading">
                        <div>
                            <span class="patient-eyebrow">Appointments</span>
                            <h2 id="booking-title">Recent &amp; Upcoming Appointments</h2>
                        </div>
                        <a href="appointment.php" class="patient-link-button">View All</a>
                    </div>

                    <?php if (count($dashboardAppointments) === 0): ?>
                        <div class="patient-empty-state">
                            <div class="patient-empty-icon" aria-hidden="true"></div>
                            <h3>No appointments yet</h3>
                            <p>You do not have an appointment yet. Browse available sessions and book a time that works for you.</p>
                            <a href="schedule.php" class="patient-btn primary">Book a Session</a>
                        </div>
                    <?php else: ?>
                        <div class="patient-table-wrap">
                            <table class="patient-booking-table">
                                <thead>
                                    <tr>
                                        <th>Appointment</th>
                                        <th>Session</th>
                                        <th>Doctor</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardAppointments as $booking): ?>
                                        <?php $badge = patient_dashboard_booking_badge($booking, $today); ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo patient_dashboard_h($booking["apponum"]); ?></strong>
                                                <span>Booked <?php echo patient_dashboard_h(patient_dashboard_format_date($booking["appodate"])); ?></span>
                                            </td>
                                            <td><?php echo patient_dashboard_h($booking["title"]); ?></td>
                                            <td><?php echo patient_dashboard_h($booking["docname"]); ?></td>
                                            <td>
                                                <strong><?php echo patient_dashboard_h(patient_dashboard_format_date($booking["scheduledate"])); ?></strong>
                                                <span><?php echo patient_dashboard_h(patient_dashboard_format_time($booking["scheduletime"])); ?></span>
                                            </td>
                                            <td>
                                                <span class="patient-status-badge <?php echo patient_dashboard_h($badge["class"]); ?>">
                                                    <?php echo patient_dashboard_h($badge["label"]); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="patient-row-actions">
                                                    <a href="appointment.php" class="patient-action-link">View</a>
                                                    <?php if (($booking["workflow"]["can_cancel"] ?? false)): ?>
                                                        <a
                                                            href="appointment.php?action=drop&id=<?php echo (int)$booking["appoid"]; ?>&title=<?php echo urlencode($booking["title"]); ?>&doc=<?php echo urlencode($booking["docname"]); ?>"
                                                            class="patient-action-link danger"
                                                        >Cancel</a>
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
            </div>
        </main>
    </div>
</body>
</html>
