<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (isset($_SESSION["user"])) {
    if (empty($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
        header("location: ../login.php");
        exit();
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: ../login.php");
    exit();
}

// Import database
include("../connection.php");

$sqlmain = "SELECT * FROM patient WHERE pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userrow = $stmt->get_result();
$userfetch = $userrow->fetch_assoc();

if (!$userfetch) {
    header("location: ../logout.php?role=p");
    exit();
}

$userid = $userfetch["pid"];
$username = $userfetch["pname"];

if ($_POST) {
    if (isset($_POST["booknow"])) {
        $scheduleid = intval($_POST["scheduleid"]);
        $date = date('Y-m-d');
        $bookingResult = doc_sathi_book_appointment($database, (int)$userid, $scheduleid, $date);

        if ($bookingResult['ok']) {
            header("location: appointment.php?action=booking-added&id=" . $bookingResult['apponum'] . "&titleget=none");
            exit;
        }

        if ($bookingResult['reason'] === 'already-booked') {
            header("location: appointment.php?action=already-booked&id=" . ($bookingResult['apponum'] ?? 0));
            exit;
        }

        if ($bookingResult['reason'] === 'session-full') {
            header("location: schedule.php?action=session-full&id=" . $scheduleid);
            exit;
        }

        if ($bookingResult['reason'] === 'session-not-found' || $bookingResult['reason'] === 'invalid-session') {
            header("location: schedule.php?action=invalid-session&id=" . $scheduleid);
            exit;
        }

        if ($bookingResult['reason'] === 'session-expired') {
            header("location: schedule.php?action=session-expired&id=" . $scheduleid);
            exit;
        }

        if ($bookingResult['reason'] === 'time-conflict') {
            header("location: schedule.php?action=time-conflict&id=" . $scheduleid);
            exit;
        }

        if ($bookingResult['reason'] === 'lock-timeout') {
            header("location: schedule.php?action=booking-busy&id=" . $scheduleid);
            exit;
        }

        header("location: schedule.php?action=booking-error&id=" . $scheduleid);
        exit;
    }
}

header("location: schedule.php");
exit;
?>
