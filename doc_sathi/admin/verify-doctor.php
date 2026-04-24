<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION['usertype'] !== 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$docid = (int)($_POST["docid"] ?? 0);
$source = $_POST["source"] ?? "";
$redirectPage = $source === "verifications" ? "doctor-verifications.php" : "doctors.php";

if ($docid <= 0) {
    header("location: " . $redirectPage . "?action=invalid-doctor");
    exit();
}

$adminEmail = $_SESSION["user"];
$adminStmt = doc_sathi_prepare($database, "SELECT aid FROM admin WHERE aemail = ? LIMIT 1");
$adminStmt->bind_param("s", $adminEmail);
doc_sathi_execute($adminStmt);
$admin = $adminStmt->get_result()->fetch_assoc();
$adminId = (int)($admin["aid"] ?? 0);

$stmt = doc_sathi_prepare(
    $database,
    "UPDATE doctor
     SET verification_status = 'approved',
         verified_by = ?,
         verified_at = NOW(),
         verification_reviewed_at = NOW(),
         admin_remarks = NULL,
         rejection_reason = NULL
     WHERE docid = ?"
);
$stmt->bind_param("ii", $adminId, $docid);
doc_sathi_execute($stmt);

header("location: " . $redirectPage . "?action=verified");
exit();
?>
