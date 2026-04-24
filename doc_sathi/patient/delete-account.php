<?php
require_once __DIR__ . "/../session_config.php";
session_start();

if (empty($_SESSION["user"]) || $_SESSION['usertype'] !== 'p') {
    header("location: ../login.php");
    exit();
}

$useremail = $_SESSION["user"];

// Import database connection
include("../connection.php");

// Fetch logged-in patient details
$sqlmain = "SELECT * FROM patient WHERE pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userrow = $stmt->get_result();
$userfetch = $userrow->fetch_assoc();

if (!$userfetch) {
    header("location: ../login.php");
    exit();
}

$userid = $userfetch["pid"];
$username = $userfetch["pname"];

$id = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form_action"] ?? "") === "delete") {
    $id = (int)($_POST["id00"] ?? 0);
    $deletePassword = $_POST["delete_password"] ?? "";
    $confirmed = ($_POST["confirm_delete"] ?? "") === "1" && isset($_POST["delete_confirm_check"]);
    $storedPassword = (string)($userfetch["ppassword"] ?? "");
    $passwordMatches = password_verify($deletePassword, $storedPassword);

    if (!$passwordMatches && password_get_info($storedPassword)["algo"] === 0) {
        $passwordMatches = hash_equals($storedPassword, $deletePassword);
    }

    if ($id !== (int)$userid || !$confirmed) {
        header("location: settings.php?error=3#danger-zone");
        exit();
    }

    if (!$passwordMatches) {
        header("location: settings.php?error=10#danger-zone");
        exit();
    }
} elseif ($_GET) {
    $id = intval($_GET["id"]);

    if ($id !== (int)$userid) {
        header("location: settings.php?error=3#danger-zone");
        exit();
    }

    header("location: settings.php?action=drop#danger-zone");
    exit();
} else {
    header("location: settings.php");
    exit();
}

// Fetch patient's email using their ID
$sqlmain = "SELECT * FROM patient WHERE pid=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("i", $id);
$stmt->execute();
$result001 = $stmt->get_result();
$patientToDelete = $result001->fetch_assoc();

if (!$patientToDelete) {
    header("location: settings.php?error=3#danger-zone");
    exit();
}

$email = $patientToDelete["pemail"];

// Delete from webuser table
$sqlmain = "DELETE FROM webuser WHERE email=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $email);
if (!$stmt->execute()) {
    die("Error deleting user record: " . $stmt->error);
}

// Delete from patient table
$sqlmain = "DELETE FROM patient WHERE pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $email);
if (!$stmt->execute()) {
    die("Error deleting patient record: " . $stmt->error);
}

header("location: ../logout.php?role=p");
exit();
?>
