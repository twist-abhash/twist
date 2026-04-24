<?php
require_once __DIR__ . "/../session_config.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION['usertype'] !== 'a') {
    header("location: ../login.php");
    exit();
}

header("location: doctors.php?action=manual-add-disabled");
exit();
?>
