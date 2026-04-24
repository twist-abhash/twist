<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION['usertype'] !== 'd') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$useremail = $_SESSION["user"];
$doctor = doc_sathi_get_doctor_by_email($database, $useremail);

if (!$doctor) {
    header("location: ../login.php");
    exit();
}

$docid = (int)$doctor["docid"];

if (($doctor["verification_status"] ?? "pending") !== "rejected") {
    header("location: account.php");
    exit();
}

$uploadResult = doc_sathi_upload_verification_document($_FILES["certification"] ?? null, dirname(__DIR__));

if (!$uploadResult["ok"]) {
    header("location: verification.php");
    exit();
}

$newPath = $uploadResult["path"];
$oldPath = doc_sathi_doctor_verification_document_path($doctor);

try {
    $stmt = doc_sathi_prepare(
        $database,
        "UPDATE doctor
         SET verification_document = ?,
             certification_file = ?,
             verification_status = 'pending',
             verification_submitted_at = NOW(),
             verification_reviewed_at = NULL,
             verified_by = NULL,
             verified_at = NULL,
             admin_remarks = NULL,
             rejection_reason = NULL
         WHERE docid = ?"
    );
    $stmt->bind_param("ssi", $newPath, $newPath, $docid);
    doc_sathi_execute($stmt);

    if ($oldPath !== "" && strpos($oldPath, "uploads/verifications/") === 0 && $oldPath !== $newPath) {
        $absoluteOldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
        if (is_file($absoluteOldPath)) {
            unlink($absoluteOldPath);
        }
    }

    header("location: verification.php");
    exit();
} catch (Throwable $exception) {
    $absoluteNewPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newPath);
    if (is_file($absoluteNewPath)) {
        unlink($absoluteNewPath);
    }

    header("location: verification.php");
    exit();
}
?>
