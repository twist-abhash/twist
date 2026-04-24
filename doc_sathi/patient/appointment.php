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

    <title>My Bookings</title>
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
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

    $sqlmain = "select * from patient where pemail=?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("s", $useremail);
    $stmt->execute();
    $userfetch = $stmt->get_result()->fetch_assoc();

    if (!$userfetch) {
        header("location: ../logout.php?role=p");
        exit();
    }

    $userid = (int)$userfetch["pid"];
    $username = $userfetch["pname"];

    date_default_timezone_set("Asia/Kathmandu");
    $today = date("Y-m-d");
    $scheduledateFilter = trim($_POST["sheduledate"] ?? "");

    $bookingSql =
        "select appointment.appoid,
                schedule.scheduleid,
                schedule.title,
                doctor.docname,
                patient.pname,
                schedule.scheduledate,
                schedule.scheduletime,
                schedule.duration_minutes,
                schedule.end_time,
                appointment.apponum,
                appointment.appodate,
                appointment.status,
                appointment.completed_at,
                appointment.checkup_result
         from schedule
         inner join appointment on schedule.scheduleid=appointment.scheduleid
         inner join patient on patient.pid=appointment.pid
         inner join doctor on schedule.docid=doctor.docid
         where patient.pid=?";

    if ($scheduledateFilter !== "") {
        $bookingSql .= " and schedule.scheduledate=?";
    }

    $bookingSql .= " order by appointment.appodate asc";
    $bookingStmt = $database->prepare($bookingSql);

    if ($scheduledateFilter !== "") {
        $bookingStmt->bind_param("is", $userid, $scheduledateFilter);
    } else {
        $bookingStmt->bind_param("i", $userid);
    }

    $bookingStmt->execute();
    $result = $bookingStmt->get_result();
    ?>

    <div class="container patient-dashboard-layout">
        <?php patient_portal_sidebar($username, $useremail, "bookings"); ?>

        <main class="dash-body patient-dashboard-body">
            <div class="patient-dashboard-shell">
                <?php
                patient_portal_page_header([
                    "eyebrow" => "Appointments",
                    "title" => "My Booking History",
                    "subtitle" => "Review appointment details, reference numbers, and scheduled visit times.",
                    "today" => $today,
                ]);
                ?>

                <section class="patient-panel" aria-labelledby="booking-filter-title">
                    <div class="patient-panel-header">
                        <div>
                            <span class="patient-eyebrow">Filter</span>
                            <h2 id="booking-filter-title">Find a Booking</h2>
                            <p>Filter your booking history by scheduled appointment date.</p>
                        </div>
                    </div>

                    <form action="" method="post" class="patient-filter-bar">
                        <div class="patient-field">
                            <label for="date">Scheduled Date</label>
                            <input
                                type="date"
                                name="sheduledate"
                                id="date"
                                class="input-text patient-filter-input"
                                value="<?php echo patient_portal_h($scheduledateFilter); ?>"
                            >
                        </div>
                        <button type="submit" name="filter" class="patient-btn primary">Filter</button>
                        <?php if ($scheduledateFilter !== ""): ?>
                            <a href="appointment.php" class="patient-btn secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </section>

                <section class="patient-panel" aria-labelledby="bookings-title">
                    <div class="patient-panel-header">
                        <div>
                            <span class="patient-eyebrow">History</span>
                            <h2 id="bookings-title">My Bookings</h2>
                            <p>
                                <?php if ($scheduledateFilter !== ""): ?>
                                    Showing bookings scheduled on <?php echo patient_portal_h(patient_portal_format_date($scheduledateFilter)); ?>.
                                <?php else: ?>
                                    Showing every booking linked to your patient account.
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="patient-count-pill"><?php echo (int)$result->num_rows; ?> bookings</span>
                    </div>

                    <?php if ($result->num_rows == 0): ?>
                        <div class="patient-empty-state">
                            <div class="patient-empty-icon" aria-hidden="true"></div>
                            <h3>No bookings found</h3>
                            <p>
                                <?php if ($scheduledateFilter !== ""): ?>
                                    You do not have a booking scheduled for that date. Clear the filter to see your full booking history.
                                <?php else: ?>
                                    You have not booked an appointment yet. Browse available sessions to reserve a visit.
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo $scheduledateFilter !== "" ? "appointment.php" : "schedule.php"; ?>" class="patient-btn primary">
                                <?php echo $scheduledateFilter !== "" ? "Show All Bookings" : "Book a Session"; ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="patient-booking-grid">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $scheduleid = (int)$row["scheduleid"];
                                $title = $row["title"];
                                $docname = $row["docname"];
                                $scheduledate = $row["scheduledate"];
                                $scheduletime = $row["scheduletime"];
                                $durationMinutes = $row["duration_minutes"];
                                $endTime = doc_sathi_schedule_end_time($row);
                                $apponum = $row["apponum"];
                                $appodate = $row["appodate"];
                                $appoid = (int)$row["appoid"];
                                $checkupResult = trim((string)($row["checkup_result"] ?? ""));
                                $workflow = doc_sathi_appointment_status_details($row);
                                $status = patient_portal_booking_status(
                                    $scheduledate,
                                    $scheduletime,
                                    $durationMinutes,
                                    $endTime,
                                    $row["status"] ?? '',
                                    $row["completed_at"] ?? ''
                                );
                                ?>
                                <article class="patient-booking-card">
                                    <div class="patient-booking-card-header">
                                        <div class="patient-booking-title">
                                            <strong><?php echo patient_portal_h($title); ?></strong>
                                            <span>Reference OC-000-<?php echo $appoid; ?></span>
                                        </div>
                                        <span class="patient-status-badge <?php echo patient_portal_h($status["class"]); ?>">
                                            <?php echo patient_portal_h($status["label"]); ?>
                                        </span>
                                    </div>

                                    <div class="patient-detail-grid">
                                        <div class="patient-detail-item">
                                            <strong><?php echo patient_portal_h(patient_portal_format_date($appodate)); ?></strong>
                                            <span>Booking date</span>
                                        </div>
                                        <div class="patient-detail-item">
                                            <strong>0<?php echo patient_portal_h($apponum); ?></strong>
                                            <span>Appointment number</span>
                                        </div>
                                        <div class="patient-detail-item">
                                            <strong><?php echo patient_portal_h($docname); ?></strong>
                                            <span>Doctor name</span>
                                        </div>
                                        <div class="patient-detail-item">
                                            <strong><?php echo patient_portal_h(patient_portal_format_date($scheduledate)); ?></strong>
                                            <span>Scheduled date</span>
                                        </div>
                                        <div class="patient-detail-item">
                                            <strong>
                                                <?php
                                                echo patient_portal_h(
                                                    patient_portal_format_time($scheduletime)
                                                    . ' - '
                                                    . patient_portal_format_time($endTime)
                                                );
                                                ?>
                                            </strong>
                                            <span>Consultation window</span>
                                        </div>
                                        <div class="patient-detail-item">
                                            <strong><?php echo patient_portal_h(doc_sathi_session_duration_label($durationMinutes)); ?></strong>
                                            <span>Duration</span>
                                        </div>
                                        <div class="patient-detail-item">
                                            <strong>#<?php echo $scheduleid; ?></strong>
                                            <span>Session ID</span>
                                        </div>
                                        <?php if (($workflow['code'] ?? '') === 'completed'): ?>
                                            <div class="patient-detail-item">
                                                <strong>Ended by Doctor</strong>
                                                <span>Doctor status</span>
                                            </div>
                                            <div class="patient-detail-item">
                                                <strong>
                                                    <?php
                                                    echo patient_portal_h(
                                                        patient_portal_format_date($row["completed_at"] ?? '')
                                                        . ' at '
                                                        . patient_portal_format_time($row["completed_at"] ?? '')
                                                    );
                                                    ?>
                                                </strong>
                                                <span>Completed at</span>
                                            </div>
                                            <div class="patient-detail-item patient-detail-item-wide">
                                                <strong class="patient-checkup-result">
                                                    <?php echo nl2br(patient_portal_h($checkupResult !== '' ? $checkupResult : 'The doctor has not added a checkup result yet.')); ?>
                                                </strong>
                                                <span>Result of the check up</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="patient-booking-actions">
                                        <?php if (!($workflow['can_cancel'] ?? false)): ?>
                                            <button type="button" class="patient-btn secondary patient-btn-disabled" disabled>
                                                <?php echo patient_portal_h(($workflow['code'] ?? '') === 'pending_completion' ? 'Session Ended' : 'Ended by Doctor'); ?>
                                            </button>
                                        <?php else: ?>
                                            <a
                                                href="?action=drop&id=<?php echo $appoid; ?>&title=<?php echo urlencode($title); ?>&doc=<?php echo urlencode($docname); ?>"
                                                class="patient-btn danger"
                                            >Cancel Booking</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <?php
    if ($_GET) {
        $id = (int)($_GET["id"] ?? 0);
        $action = $_GET["action"] ?? "";

        if ($action === "booking-added") {
            echo '
            <div id="popup1" class="overlay patient-modal-overlay">
                <div class="popup patient-modal-card">
                    <a class="close" href="appointment.php">&times;</a>
                    <div class="patient-modal-heading">
                        <span class="patient-eyebrow">Booking Complete</span>
                        <h2>Booking Successful</h2>
                        <p>Your appointment number is ' . $id . '.</p>
                    </div>
                    <div class="patient-modal-actions">
                        <a href="appointment.php" class="patient-btn primary">OK</a>
                    </div>
                </div>
            </div>';
        } elseif ($action === "already-booked") {
            echo '
            <div id="popup1" class="overlay patient-modal-overlay">
                <div class="popup patient-modal-card">
                    <a class="close" href="appointment.php">&times;</a>
                    <div class="patient-modal-heading">
                        <span class="patient-eyebrow">Booking Exists</span>
                        <h2>Already Booked</h2>
                        <p>You already have this session in your booking list. Your appointment number is ' . $id . '.</p>
                    </div>
                    <div class="patient-modal-actions">
                        <a href="appointment.php" class="patient-btn primary">OK</a>
                    </div>
                </div>
            </div>';
        } elseif ($action === "drop") {
            $title = $_GET["title"] ?? "";
            $docname = $_GET["doc"] ?? "";

            echo '
            <div id="popup1" class="overlay patient-modal-overlay">
                <div class="popup patient-modal-card">
                    <a class="close" href="appointment.php">&times;</a>
                    <div class="patient-modal-heading">
                        <span class="patient-eyebrow">Cancel Booking</span>
                        <h2>Cancel this appointment?</h2>
                        <p>This will remove your booking for ' . patient_portal_h(substr($title, 0, 40)) . ' with ' . patient_portal_h(substr($docname, 0, 40)) . '.</p>
                    </div>
                    <div class="patient-modal-actions">
                        <a href="delete-appointment.php?id=' . $id . '" class="patient-btn danger">Yes, Cancel</a>
                        <a href="appointment.php" class="patient-btn secondary">Keep Booking</a>
                    </div>
                </div>
            </div>';
        } elseif ($action === "cancel-locked") {
            echo '
            <div id="popup1" class="overlay patient-modal-overlay">
                <div class="popup patient-modal-card">
                    <a class="close" href="appointment.php">&times;</a>
                    <div class="patient-modal-heading">
                        <span class="patient-eyebrow">Cancellation Blocked</span>
                        <h2>Appointment can no longer be cancelled</h2>
                        <p>This appointment has already ended, so cancellation is no longer available.</p>
                    </div>
                    <div class="patient-modal-actions">
                        <a href="appointment.php" class="patient-btn primary">OK</a>
                    </div>
                </div>
            </div>';
        } elseif ($action === "view") {
            $doctor = doc_sathi_get_doctor_by_id($database, $id, false);
            if ($doctor) {
                patient_portal_doctor_details_modal("appointment.php", $doctor);
            }
        }
    }
    ?>
</body>
</html>
