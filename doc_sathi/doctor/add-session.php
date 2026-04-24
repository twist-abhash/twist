<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";

session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: schedule.php");
    exit();
}

date_default_timezone_set('Asia/Kathmandu');

$useremail = $_SESSION["user"];
include("../connection.php");

$doctorLookup = doc_sathi_prepare(
    $database,
    "SELECT docid, verification_status
     FROM doctor
     WHERE docemail = ?
     LIMIT 1"
);
$doctorLookup->bind_param("s", $useremail);
doc_sathi_execute($doctorLookup);
$doctor = $doctorLookup->get_result()->fetch_assoc();

if (!$doctor) {
    header("location: schedule.php?notice=invalid-doctor");
    exit();
}

if (!doc_sathi_doctor_is_approved($doctor)) {
    header("location: schedule.php?notice=verification-required");
    exit();
}

$title = trim($_POST["title"] ?? "");
$nop = doc_sathi_parse_natural_number($_POST["nop"] ?? null, 1);
$date = trim($_POST["date"] ?? "");
$time = trim($_POST["time"] ?? "");
$durationMinutes = (int)($_POST["duration_minutes"] ?? 0);

if ($nop === null) {
    header("location: schedule.php?action=add-session&error=invalid-capacity");
    exit();
}

if ($title === '' || $date === '' || $time === '' || $durationMinutes <= 0) {
    header("location: schedule.php?action=add-session&error=invalid-input");
    exit();
}

if (!preg_match("/^(?=.*[A-Za-z])[A-Za-z0-9 .'-]+$/", $title)) {
    header("location: schedule.php?action=add-session&error=invalid-title");
    exit();
}

if (doc_sathi_parse_date($date) === null) {
    header("location: schedule.php?action=add-session&error=invalid-date");
    exit();
}

if (doc_sathi_parse_time($time) === null) {
    header("location: schedule.php?action=add-session&error=invalid-time");
    exit();
}

if (!doc_sathi_session_duration_is_valid($durationMinutes)) {
    header("location: schedule.php?action=add-session&error=invalid-duration");
    exit();
}

if (doc_sathi_session_datetime_is_in_past($date, $time)) {
    header("location: schedule.php?action=add-session&error=invalid-datetime");
    exit();
}

$endTime = doc_sathi_session_end_time_from_inputs($date, $time, $durationMinutes);
if ($endTime === null) {
    header("location: schedule.php?action=add-session&error=invalid-duration-window");
    exit();
}

try {
    $docid = (int)$doctor["docid"];

    if (doc_sathi_schedule_overlap_exists($database, $docid, $date, $time, $endTime)) {
        header("location: schedule.php?action=add-session&error=overlapping-session");
        exit();
    }

    $stmt = doc_sathi_prepare(
        $database,
        "INSERT INTO schedule (docid, title, scheduledate, scheduletime, duration_minutes, end_time, nop)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isssisi", $docid, $title, $date, $time, $durationMinutes, $endTime, $nop);
    doc_sathi_execute($stmt);

    header("location: schedule.php?notice=session-added&title=" . urlencode($title));
    exit();
} catch (Throwable $exception) {
    header("location: schedule.php?notice=error&message=" . urlencode("Could not create the session."));
    exit();
}
