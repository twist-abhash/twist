<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";

session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

function appointment_delete_redirect_url($returnUrl, $notice = 'appointment-cancelled')
{
    $returnUrl = trim((string)$returnUrl);
    $notice = trim((string)$notice);

    if ($returnUrl === '' || !preg_match('/^appointment\.php(?:\?.*)?$/', $returnUrl)) {
        $returnUrl = 'appointment.php';
    }

    return $returnUrl . (strpos($returnUrl, '?') === false ? '?' : '&') . 'notice=' . urlencode($notice);
}

$redirectBaseUrl = trim((string)($_GET['return'] ?? 'appointment.php'));
$redirectUrl = appointment_delete_redirect_url($redirectBaseUrl);

if (!isset($_GET["id"])) {
    header("location: " . appointment_delete_redirect_url($redirectBaseUrl, 'invalid-appointment'));
    exit();
}

$useremail = $_SESSION["user"];
$appointmentId = (int)($_GET["id"] ?? 0);

include("../connection.php");

$doctorLookup = doc_sathi_prepare(
    $database,
    "SELECT docid
     FROM doctor
     WHERE docemail = ?
     LIMIT 1"
);
$doctorLookup->bind_param("s", $useremail);
doc_sathi_execute($doctorLookup);
$doctor = $doctorLookup->get_result()->fetch_assoc();

if ($doctor && $appointmentId > 0) {
    $docid = (int)$doctor["docid"];
    try {
        $deleteStmt = doc_sathi_prepare(
            $database,
            "DELETE appointment
             FROM appointment
             INNER JOIN schedule ON appointment.scheduleid = schedule.scheduleid
             WHERE appointment.appoid = ?
               AND schedule.docid = ?
               AND COALESCE(appointment.status, 'confirmed') <> 'completed'
               AND appointment.completed_at IS NULL"
        );
        $deleteStmt->bind_param("ii", $appointmentId, $docid);
        doc_sathi_execute($deleteStmt);

        if ($deleteStmt->affected_rows < 1) {
            header("location: " . appointment_delete_redirect_url($redirectBaseUrl, 'appointment-locked'));
            exit();
        }
    } catch (Throwable $exception) {
        header("location: " . appointment_delete_redirect_url($redirectBaseUrl, 'appointment-error'));
        exit();
    }
} else {
    header("location: " . appointment_delete_redirect_url($redirectBaseUrl, 'invalid-appointment'));
    exit();
}

header("location: " . $redirectUrl);
exit();
