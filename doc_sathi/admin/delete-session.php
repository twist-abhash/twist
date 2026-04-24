<?php
require_once __DIR__ . "/../session_config.php";
session_start();

// Check if the user is logged in and is an admin
if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

if ($_GET && isset($_GET["id"])) {
    // Import database
    include("../connection.php");

    $id = (int)$_GET["id"];

    if ($id <= 0) {
        header("location: schedule.php?action=invalid-id");
        exit();
    }

    $stmt = $database->prepare("DELETE FROM schedule WHERE scheduleid = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("location: schedule.php?action=schedule-deleted");
    } else {
        header("location: schedule.php?action=error-deleting-schedule");
    }

    $database->close();
} else {
    // No id passed, redirect to schedule page with an error message
    header("location: schedule.php?action=no-id-provided");
    exit();
}
?>
