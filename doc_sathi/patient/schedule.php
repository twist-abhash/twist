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

    <title>Sessions</title>
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
    $currentTime = date("H:i:s");
    $currentMoment = new DateTimeImmutable("now", new DateTimeZone("Asia/Kathmandu"));

    $insertkey = trim($_POST["search"] ?? "");
    $action = trim($_GET["action"] ?? "");

    $sessionSql =
        "select schedule.scheduleid,
                schedule.docid,
                schedule.title,
                schedule.scheduledate,
                schedule.scheduletime,
                schedule.duration_minutes,
                schedule.end_time,
                schedule.nop,
                doctor.docname,
                doctor.docemail,
                doctor.gender,
                doctor.clinic_name,
                doctor.specialties,
                specialties.sname as specialty_name,
                count(appointment.appoid) as booked_count
         from schedule
         inner join doctor on schedule.docid=doctor.docid
         left join specialties on doctor.specialties=specialties.id
         left join appointment on appointment.scheduleid=schedule.scheduleid
         where (
             schedule.scheduledate>?
             or (
                 schedule.scheduledate=?
                 and COALESCE(
                     schedule.end_time,
                     ADDTIME(
                         schedule.scheduletime,
                         SEC_TO_TIME(COALESCE(schedule.duration_minutes, " . doc_sathi_default_session_duration_minutes() . ") * 60)
                     )
                 )>?
             )
         )
           and doctor.verification_status='approved'";
    $sessionTypes = "sss";
    $sessionParams = [$today, $today, $currentTime];

    if ($insertkey !== "") {
        $likeKeyword = doc_sathi_search_pattern($insertkey);
        $sessionSql .=
            " and (
                doctor.docname=? or doctor.docemail=? or specialties.sname=? or
                doctor.docname like ? or doctor.docemail like ? or specialties.sname like ? or
                schedule.title=? or schedule.title like ? or
                schedule.scheduledate=? or schedule.scheduledate like ?
            )";
        $sessionTypes .= "ssssssssss";
        $sessionParams[] = $insertkey;
        $sessionParams[] = $insertkey;
        $sessionParams[] = $insertkey;
        $sessionParams[] = $likeKeyword;
        $sessionParams[] = $likeKeyword;
        $sessionParams[] = $likeKeyword;
        $sessionParams[] = $insertkey;
        $sessionParams[] = $likeKeyword;
        $sessionParams[] = $insertkey;
        $sessionParams[] = $likeKeyword;
    }

    $sessionSql .=
        " group by schedule.scheduleid,
                  schedule.docid,
                  schedule.title,
                  schedule.scheduledate,
                  schedule.scheduletime,
                  schedule.duration_minutes,
                  schedule.end_time,
                  schedule.nop,
                  doctor.docname,
                  doctor.docemail,
                  doctor.gender,
                  doctor.clinic_name,
                  doctor.specialties,
                  specialties.sname
          order by schedule.scheduledate asc, schedule.scheduletime asc";

    $stmt = $database->prepare($sessionSql);
    doc_sathi_bind_dynamic_params($stmt, $sessionTypes, $sessionParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $sessions = [];
    $scheduleIds = [];
    while ($row = $result->fetch_assoc()) {
        if (doc_sathi_session_datetime($row["scheduledate"], $row["scheduletime"]) === null) {
            continue;
        }

        $sessions[] = $row;
        $scheduleIds[] = (int)$row["scheduleid"];
    }

    $sessions = doc_sathi_rank_sessions($sessions, $insertkey);
    $patientBookingMap = doc_sathi_patient_booking_map($database, $userid, $scheduleIds);
    $patientConflictMap = doc_sathi_patient_booking_conflict_map($database, $userid, $sessions);

    $doctorOptionsStmt = $database->prepare(
        "select distinct docname, docemail from doctor where verification_status='approved' order by docname"
    );
    $doctorOptionsStmt->execute();
    $doctorOptions = $doctorOptionsStmt->get_result();

    $sessionOptionsStmt = $database->prepare(
        "select distinct schedule.title
         from schedule
         inner join doctor on schedule.docid=doctor.docid
         where doctor.verification_status='approved'
         order by schedule.title"
    );
    $sessionOptionsStmt->execute();
    $sessionOptions = $sessionOptionsStmt->get_result();

    $specialtyOptionsStmt = $database->prepare("select sname from specialties order by sname");
    $specialtyOptionsStmt->execute();
    $specialtyOptions = $specialtyOptionsStmt->get_result();

    ob_start();
    ?>
    <form action="" method="post" class="patient-search-form">
        <input
            type="search"
            name="search"
            class="input-text patient-search-input"
            placeholder="Search doctor name, email, date, or specialty"
            list="session-search-options"
            value="<?php echo patient_portal_h($insertkey); ?>"
        >
        <datalist id="session-search-options">
            <?php if ($doctorOptions): ?>
                <?php while ($option = $doctorOptions->fetch_assoc()): ?>
                    <option value="<?php echo patient_portal_h($option["docname"]); ?>"></option>
                    <option value="<?php echo patient_portal_h($option["docemail"]); ?>"></option>
                <?php endwhile; ?>
            <?php endif; ?>
            <?php if ($sessionOptions): ?>
                <?php while ($option = $sessionOptions->fetch_assoc()): ?>
                    <option value="<?php echo patient_portal_h($option["title"]); ?>"></option>
                <?php endwhile; ?>
            <?php endif; ?>
            <?php if ($specialtyOptions): ?>
                <?php while ($option = $specialtyOptions->fetch_assoc()): ?>
                    <option value="<?php echo patient_portal_h($option["sname"]); ?>"></option>
                <?php endwhile; ?>
            <?php endif; ?>
        </datalist>
        <button type="submit" class="patient-btn primary">Search</button>
    </form>
    <?php
    $searchHtml = ob_get_clean();
    $listTitle = $insertkey !== "" ? "Search Results (" . count($sessions) . ")" : "All Sessions (" . count($sessions) . ")";
    $flashNoticeMap = [
        "session-full" => [
            "type" => "error",
            "message" => "That session filled up before your booking was completed.",
        ],
        "invalid-session" => [
            "type" => "error",
            "message" => "That session is no longer available for booking.",
        ],
        "session-not-found" => [
            "type" => "error",
            "message" => "The requested session could not be found.",
        ],
        "session-expired" => [
            "type" => "error",
            "message" => "That session has already ended, so it can no longer be booked.",
        ],
        "booking-busy" => [
            "type" => "error",
            "message" => "Another booking is being processed for that session. Please try again.",
        ],
        "time-conflict" => [
            "type" => "error",
            "message" => "You already have another appointment booked during that time. Choose a session that does not overlap with your existing booking.",
        ],
        "booking-error" => [
            "type" => "error",
            "message" => "We could not complete the booking right now. Please try again.",
        ],
    ];
    $flashNotice = $flashNoticeMap[$action] ?? null;
    ?>

    <div class="container patient-dashboard-layout">
        <?php patient_portal_sidebar($username, $useremail, "sessions"); ?>

        <main class="dash-body patient-dashboard-body">
            <div class="patient-dashboard-shell">
                <?php
                patient_portal_page_header([
                    "eyebrow" => "Book Care",
                    "title" => "Sessions",
                    "subtitle" => "Find active and upcoming doctor sessions and reserve a visit.",
                    "today" => $today,
                    "search_html" => $searchHtml,
                ]);
                ?>

                <?php if ($flashNotice): ?>
                    <div class="patient-alert <?php echo patient_portal_h($flashNotice["type"]); ?>">
                        <?php echo patient_portal_h($flashNotice["message"]); ?>
                    </div>
                <?php endif; ?>

                <section class="patient-panel" aria-labelledby="sessions-title">
                    <div class="patient-panel-header">
                        <div>
                            <span class="patient-eyebrow">Availability</span>
                            <h2 id="sessions-title"><?php echo patient_portal_h($listTitle); ?></h2>
                            <p>
                                <?php if ($insertkey !== ""): ?>
                                    Results for "<?php echo patient_portal_h($insertkey); ?>".
                                <?php else: ?>
                                    Sessions are shown until they end, so you can still book one that is already in progress if seats remain.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($insertkey !== ""): ?>
                            <a href="schedule.php" class="patient-link-button">Clear Search</a>
                        <?php endif; ?>
                    </div>

                    <?php if (count($sessions) === 0): ?>
                        <div class="patient-empty-state">
                            <div class="patient-empty-icon" aria-hidden="true"></div>
                            <h3><?php echo $insertkey !== "" ? "No sessions found" : "No sessions available"; ?></h3>
                            <p>
                                <?php if ($insertkey !== ""): ?>
                                    We could not find an active or upcoming session matching your search. Try another doctor, email, session title, date, or specialty.
                                <?php else: ?>
                                    There are no active or upcoming sessions available right now. Check back later or browse approved doctors.
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo $insertkey !== "" ? "schedule.php" : "doctors.php"; ?>" class="patient-btn primary">
                                <?php echo $insertkey !== "" ? "Show All Sessions" : "Find Doctors"; ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="patient-session-grid">
                            <?php foreach ($sessions as $row): ?>
                                <?php
                                $scheduleid = (int)$row["scheduleid"];
                                $title = $row["title"];
                                $docname = $row["docname"];
                                $docemail = $row["docemail"] ?? "";
                                $doctorGender = doc_sathi_gender_label($row["gender"] ?? "");
                                $doctorGender = $doctorGender === "" ? "Not specified" : $doctorGender;
                                $clinicName = trim((string)($row["clinic_name"] ?? ""));
                                $clinicName = $clinicName === "" ? "Clinic / Hospital not provided" : $clinicName;
                                $scheduledate = $row["scheduledate"];
                                $scheduletime = $row["scheduletime"];
                                $sessionEndTime = doc_sathi_schedule_end_time($row);
                                $sessionInProgress =
                                    $sessionEndTime !== ""
                                    && doc_sathi_session_has_started($scheduledate, $scheduletime, $currentMoment)
                                    && !doc_sathi_session_has_ended($scheduledate, $sessionEndTime, $currentMoment);
                                $capacity = max(0, (int)($row["nop"] ?? 0));
                                $bookedCount = max(0, (int)($row["booked_count"] ?? 0));
                                $remainingSlots = max(0, (int)($row["remaining_slots"] ?? ($capacity - $bookedCount)));
                                $alreadyBooked = isset($patientBookingMap[$scheduleid]);
                                $timeConflict = $patientConflictMap[$scheduleid] ?? null;
                                $sessionFull = $capacity <= 0 || $bookedCount >= $capacity || $remainingSlots <= 0;
                                ?>
                                <article class="patient-session-card">
                                    <div>
                                        <div class="patient-session-card-header">
                                            <span class="patient-session-icon" aria-hidden="true"></span>
                                            <div>
                                                <strong class="patient-session-title"><?php echo patient_portal_h($title); ?></strong>
                                                <div class="patient-session-meta">
                                                    <div>
                                                        <strong><?php echo patient_portal_h($docname); ?></strong>
                                                        <?php if ($docemail !== ""): ?>
                                                            <span><?php echo patient_portal_h($docemail); ?></span>
                                                        <?php endif; ?>
                                                        <span><?php echo patient_portal_h($doctorGender); ?> | <?php echo patient_portal_h($clinicName); ?></span>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo patient_portal_h(patient_portal_format_date($scheduledate)); ?></strong>
                                                        <?php if ($sessionInProgress): ?>
                                                            <span>In progress until <?php echo patient_portal_h(patient_portal_format_time($sessionEndTime)); ?></span>
                                                        <?php else: ?>
                                                            <span>Starts at <?php echo patient_portal_h(patient_portal_format_time($scheduletime)); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $remainingSlots; ?> seats available</strong>
                                                        <span><?php echo $bookedCount; ?> of <?php echo $capacity; ?> booked</span>
                                                    </div>
                                                    <?php if ($timeConflict): ?>
                                                        <div>
                                                            <strong>Time conflict</strong>
                                                            <span>
                                                                Overlaps with #<?php echo (int)($timeConflict["apponum"] ?? 0); ?>
                                                                at <?php echo patient_portal_h(patient_portal_format_time($timeConflict["scheduletime"] ?? "")); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="patient-session-actions">
                                        <?php if ($alreadyBooked): ?>
                                            <button type="button" class="patient-btn secondary patient-btn-disabled" disabled>Already Booked</button>
                                        <?php elseif ($timeConflict): ?>
                                            <button type="button" class="patient-btn secondary patient-btn-disabled" disabled>Time Conflict</button>
                                        <?php elseif ($sessionFull): ?>
                                            <button type="button" class="patient-btn secondary patient-btn-disabled" disabled>Session Full</button>
                                        <?php else: ?>
                                            <a href="booking.php?id=<?php echo $scheduleid; ?>" class="patient-btn primary">Book Now</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
