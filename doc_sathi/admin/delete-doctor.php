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
        header("location: doctors.php?action=invalid-id");
        exit();
    }

    $doctorStmt = $database->prepare("SELECT docemail FROM doctor WHERE docid = ?");
    $doctorStmt->bind_param("i", $id);
    $doctorStmt->execute();
    $result001 = $doctorStmt->get_result();

    // Check if the doctor exists
    if ($result001->num_rows > 0) {
        $email = ($result001->fetch_assoc())["docemail"];

        $webuserStmt = $database->prepare("DELETE FROM webuser WHERE email = ?");
        $webuserStmt->bind_param("s", $email);

        $doctorDeleteStmt = $database->prepare("DELETE FROM doctor WHERE docid = ?");
        $doctorDeleteStmt->bind_param("i", $id);

        $sql1 = $webuserStmt->execute();
        $sql2 = $doctorDeleteStmt->execute();

        if ($sql1 && $sql2) {
            header("location: doctors.php?action=doctor-deleted");
        } else {
            header("location: doctors.php?action=error-deleting-doctor");
        }
    } else {
        // No doctor found with the given ID
        header("location: doctors.php?action=doctor-not-found");
    }

    $database->close();
} else {
    // If no ID was provided
    header("location: doctors.php?action=no-id-provided");
    exit();
}
?>
