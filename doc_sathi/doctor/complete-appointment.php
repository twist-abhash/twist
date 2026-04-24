<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";

session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

function appointment_complete_redirect_url($returnUrl, $notice)
{
    $returnUrl = trim((string)$returnUrl);
    $notice = trim((string)$notice);

    if ($returnUrl === '' || !preg_match('/^appointment\.php(?:\?.*)?$/', $returnUrl)) {
        $returnUrl = 'appointment.php';
    }

    if ($notice === '') {
        return $returnUrl;
    }

    return $returnUrl . (strpos($returnUrl, '?') === false ? '?' : '&') . 'notice=' . urlencode($notice);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("location: appointment.php");
    exit();
}

date_default_timezone_set('Asia/Kathmandu');

$redirectUrl = appointment_complete_redirect_url($_POST['return'] ?? 'appointment.php', '');
$appointmentId = (int)($_POST['appoid'] ?? 0);
$checkupResult = doc_sathi_normalize_checkup_result($_POST['checkup_result'] ?? '');
$useremail = $_SESSION["user"];

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

if (!$doctor || $appointmentId <= 0) {
    header("location: " . appointment_complete_redirect_url($redirectUrl, 'invalid-appointment'));
    exit();
}

if ($checkupResult === '') {
    header("location: " . appointment_complete_redirect_url($redirectUrl, 'appointment-result-required'));
    exit();
}

if (strlen($checkupResult) > doc_sathi_checkup_result_max_length()) {
    header("location: " . appointment_complete_redirect_url($redirectUrl, 'appointment-result-too-long'));
    exit();
}

$docid = (int)$doctor["docid"];
$appointmentLookup = doc_sathi_prepare(
    $database,
    "SELECT a.appoid,
            a.status,
            a.completed_at,
            s.scheduledate,
            s.scheduletime,
            s.duration_minutes,
            s.end_time
     FROM appointment a
     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
     WHERE a.appoid = ?
       AND s.docid = ?
     LIMIT 1"
);
$appointmentLookup->bind_param("ii", $appointmentId, $docid);
doc_sathi_execute($appointmentLookup);
$appointment = $appointmentLookup->get_result()->fetch_assoc();

if (!$appointment) {
    header("location: " . appointment_complete_redirect_url($redirectUrl, 'invalid-appointment'));
    exit();
}

$appointment['duration_minutes'] = doc_sathi_schedule_duration_minutes($appointment);
$appointment['end_time'] = doc_sathi_schedule_end_time($appointment);
$workflow = doc_sathi_appointment_status_details($appointment);

if (!$workflow['can_finish']) {
    header("location: " . appointment_complete_redirect_url($redirectUrl, 'appointment-not-ready'));
    exit();
}

$completedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kathmandu')))->format('Y-m-d H:i:s');

try {
    $updateStmt = doc_sathi_prepare(
        $database,
        "UPDATE appointment
         SET status = 'completed',
             completed_at = ?,
             completed_by = ?,
             checkup_result = ?
         WHERE appoid = ?
           AND COALESCE(status, 'confirmed') <> 'completed'
           AND completed_at IS NULL"
    );
    $updateStmt->bind_param("sisi", $completedAt, $docid, $checkupResult, $appointmentId);
    doc_sathi_execute($updateStmt);

    if ($updateStmt->affected_rows < 1) {
        header("location: " . appointment_complete_redirect_url($redirectUrl, 'appointment-locked'));
        exit();
    }

    header("location: " . appointment_complete_redirect_url($redirectUrl, 'appointment-completed'));
    exit();
} catch (Throwable $exception) {
    header("location: " . appointment_complete_redirect_url($redirectUrl, 'appointment-error'));
    exit();
}
