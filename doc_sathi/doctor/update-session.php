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
    "SELECT docid
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

$scheduleId = (int)($_POST["scheduleid"] ?? 0);
$title = trim($_POST["title"] ?? "");
$nop = doc_sathi_parse_natural_number($_POST["nop"] ?? null, 1);
$date = trim($_POST["date"] ?? "");
$time = trim($_POST["time"] ?? "");
$durationMinutes = (int)($_POST["duration_minutes"] ?? 0);
$docid = (int)$doctor["docid"];

if ($nop === null) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-capacity");
    exit();
}

if ($scheduleId <= 0 || $title === '' || $date === '' || $time === '' || $durationMinutes <= 0) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-input");
    exit();
}

if (!preg_match("/^(?=.*[A-Za-z])[A-Za-z0-9 .'-]+$/", $title)) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-title");
    exit();
}

if (doc_sathi_parse_date($date) === null) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-date");
    exit();
}

if (doc_sathi_parse_time($time) === null) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-time");
    exit();
}

if (!doc_sathi_session_duration_is_valid($durationMinutes)) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-duration");
    exit();
}

$sessionLookup = doc_sathi_prepare(
    $database,
    "SELECT s.scheduleid,
            s.scheduledate,
            s.scheduletime,
            s.duration_minutes,
            s.end_time,
            COUNT(a.appoid) AS booked
     FROM schedule s
     LEFT JOIN appointment a ON a.scheduleid = s.scheduleid
      WHERE s.scheduleid = ?
        AND s.docid = ?
     GROUP BY s.scheduleid, s.scheduledate, s.scheduletime, s.duration_minutes, s.end_time
     LIMIT 1"
);
$sessionLookup->bind_param("ii", $scheduleId, $docid);
doc_sathi_execute($sessionLookup);
$session = $sessionLookup->get_result()->fetch_assoc();

if (!$session) {
    header("location: schedule.php?notice=invalid-session");
    exit();
}

if (!doc_sathi_session_can_be_managed($session)) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-datetime");
    exit();
}

if ($nop < (int)$session["booked"]) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=capacity-below-booked");
    exit();
}

if (doc_sathi_session_datetime_is_in_past($date, $time)) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-datetime");
    exit();
}

$endTime = doc_sathi_session_end_time_from_inputs($date, $time, $durationMinutes);
if ($endTime === null) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=invalid-duration-window");
    exit();
}

try {
    if (doc_sathi_schedule_overlap_exists($database, $docid, $date, $time, $endTime, $scheduleId)) {
        header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=overlapping-session");
        exit();
    }

    $updateStmt = doc_sathi_prepare(
        $database,
        "UPDATE schedule
         SET title = ?,
             scheduledate = ?,
             scheduletime = ?,
             duration_minutes = ?,
             end_time = ?,
             nop = ?
         WHERE scheduleid = ?
           AND docid = ?"
    );
    $updateStmt->bind_param("sssisiii", $title, $date, $time, $durationMinutes, $endTime, $nop, $scheduleId, $docid);
    doc_sathi_execute($updateStmt);

    header("location: schedule.php?notice=session-updated&title=" . urlencode($title));
    exit();
} catch (Throwable $exception) {
    header("location: schedule.php?action=edit&id=" . $scheduleId . "&error=save-failed");
    exit();
}
