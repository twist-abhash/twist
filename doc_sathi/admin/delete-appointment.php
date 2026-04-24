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
        header("location: appointment.php?action=invalid-id");
        exit();
    }

    $stmt = $database->prepare(
        "DELETE FROM appointment
         WHERE appoid = ?
           AND COALESCE(status, 'confirmed') <> 'completed'
           AND completed_at IS NULL"
    );
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        header("location: appointment.php?action=appointment-deleted");
    } else {
        header("location: appointment.php?action=error-deleting-appointment");
    }

    $database->close();
} else {
    // No id passed, redirect to appointment page
    header("location: appointment.php?action=no-id-provided");
    exit();
}
?>
