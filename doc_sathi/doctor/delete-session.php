<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";

session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

function schedule_delete_redirect_url($returnUrl)
{
    $returnUrl = trim((string)$returnUrl);

    if ($returnUrl === '' || !preg_match('/^schedule\.php(?:\?.*)?$/', $returnUrl)) {
        $returnUrl = 'schedule.php';
    }

    return $returnUrl . (strpos($returnUrl, '?') === false ? '?' : '&') . 'notice=session-cancelled';
}

$redirectUrl = schedule_delete_redirect_url($_GET['return'] ?? 'schedule.php');

if (!isset($_GET["id"])) {
    header("location: " . $redirectUrl);
    exit();
}

$useremail = $_SESSION["user"];
$scheduleId = (int)($_GET["id"] ?? 0);

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

if ($doctor && $scheduleId > 0) {
    $docid = (int)$doctor["docid"];

    try {
        $database->begin_transaction();

        $deleteAppointments = doc_sathi_prepare(
            $database,
            "DELETE a
             FROM appointment a
             INNER JOIN schedule s ON s.scheduleid = a.scheduleid
             WHERE s.scheduleid = ?
               AND s.docid = ?"
        );
        $deleteAppointments->bind_param("ii", $scheduleId, $docid);
        doc_sathi_execute($deleteAppointments);

        $deleteSession = doc_sathi_prepare(
            $database,
            "DELETE FROM schedule
             WHERE scheduleid = ?
               AND docid = ?"
        );
        $deleteSession->bind_param("ii", $scheduleId, $docid);
        doc_sathi_execute($deleteSession);

        $database->commit();
    } catch (Throwable $exception) {
        $database->rollback();
        header("location: schedule.php?notice=error&message=" . urlencode("Could not cancel the session."));
        exit();
    }
}

header("location: " . $redirectUrl);
exit();
