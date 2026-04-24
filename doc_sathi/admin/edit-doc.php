<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$error = '3';
$id = 0;

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $oldemail = trim($_POST["oldemail"] ?? '');
    $spec = (int)($_POST['spec'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $tele = trim($_POST['tele'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $id = (int)($_POST['id00'] ?? 0);

    if (!doc_sathi_email_is_valid($email)) {
        $error = '5';
    } elseif (!doc_sathi_phone_is_valid($tele)) {
        $error = '6';
    } elseif (doc_sathi_doctor_phone_exists($database, $tele, $id)) {
        $error = '6';
    } elseif ($password !== $cpassword) {
        $error = '2';
    } elseif (!doc_sathi_password_is_strong($password)) {
        $error = '8';
    } elseif ($email !== $oldemail && doc_sathi_email_exists($database, $email, $oldemail)) {
        $error = '1';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $database->begin_transaction();

            $doctorStmt = $database->prepare(
                "UPDATE doctor
                 SET docemail = ?, docname = ?, docpassword = ?, doctel = ?, specialties = ?
                 WHERE docid = ?"
            );
            $doctorStmt->bind_param("ssssii", $email, $name, $hashedPassword, $tele, $spec, $id);
            $doctorStmt->execute();

            if ($email !== $oldemail) {
                $webuserStmt = $database->prepare("UPDATE webuser SET email = ? WHERE email = ?");
                $webuserStmt->bind_param("ss", $email, $oldemail);
                $webuserStmt->execute();
            }

            $database->commit();
            $error = '4';
        } catch (Throwable $exception) {
            $database->rollback();
            $error = '7';
        }
    }
}

header("Location: doctors.php?action=edit&error=" . $error . "&id=" . $id);
exit();
?>
