<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";

session_start();
if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
        exit();
    }

}else{
    header("location: ../login.php");
    exit();
}



if ($_POST) {
    date_default_timezone_set('Asia/Kathmandu');

    // Import database
    include("../connection.php");

    $title = trim($_POST["title"] ?? "");
    $docid = (int) $_POST["docid"]; // Casting ensures only integers are allowed
    $nop = doc_sathi_parse_natural_number($_POST["nop"] ?? null, 1);
    $date = trim($_POST["date"] ?? "");
    $time = trim($_POST["time"] ?? "");
    $durationMinutes = doc_sathi_default_session_duration_minutes();

    // Validate inputs
    if (empty($title) || empty($date) || empty($time) || $docid <= 0 || $nop === null) {
        header("location: schedule.php?action=invalid-input");
        exit();
    }

    if (doc_sathi_parse_date($date) === null || doc_sathi_parse_time($time) === null) {
        header("location: schedule.php?action=invalid-input");
        exit();
    }

    if (doc_sathi_session_datetime_is_in_past($date, $time)) {
        header("location: schedule.php?action=invalid-datetime");
        exit();
    }

    $endTime = doc_sathi_session_end_time_from_inputs($date, $time, $durationMinutes);
    if ($endTime === null) {
        header("location: schedule.php?action=invalid-input");
        exit();
    }

    $doctorStmt = $database->prepare("SELECT docid FROM doctor WHERE docid = ? AND verification_status = 'approved' LIMIT 1");
    $doctorStmt->bind_param("i", $docid);
    $doctorStmt->execute();
    if ($doctorStmt->get_result()->num_rows === 0) {
        header("location: schedule.php?action=doctor-not-approved");
        exit();
    }

    $stmt = $database->prepare(
        "INSERT INTO schedule (docid, title, scheduledate, scheduletime, duration_minutes, end_time, nop) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isssisi", $docid, $title, $date, $time, $durationMinutes, $endTime, $nop);

    if ($stmt->execute()) {
        header("location: schedule.php?action=session-added&title=" . urlencode($title));
    } else {
        header("location: schedule.php?action=error&message=" . urlencode($stmt->error));
    }

    // Close the database connection
    $database->close();
}
?>
