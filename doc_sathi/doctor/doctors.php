<?php
require_once __DIR__ . "/../session_config.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "") {
    header("location: ../login.php");
    exit();
}

if ($_SESSION["usertype"] === "d") {
    header("location: index.php");
    exit();
}

if ($_SESSION["usertype"] === "a") {
    header("location: ../admin/doctors.php");
    exit();
}

if ($_SESSION["usertype"] === "p") {
    header("location: ../patient/doctors.php");
    exit();
}

header("location: ../login.php");
exit();
?>
