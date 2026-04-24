<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$error = '3';
$id = 0;
$useremail = $_SESSION["user"];
$doctorMinimumAge = 27;
$doctorMaximumAge = 100;

$doctorStmt = $database->prepare("SELECT docid FROM doctor WHERE docemail = ?");
$doctorStmt->bind_param("s", $useremail);
$doctorStmt->execute();
$doctor = $doctorStmt->get_result()->fetch_assoc();

if (!$doctor) {
    header("location: ../login.php");
    exit();
}

$loggedDoctorId = (int)$doctor["docid"];

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $oldemail = trim($_POST["oldemail"] ?? '');
    $address = trim($_POST['docaddress'] ?? '');
    $dob = trim($_POST['docdob'] ?? '');
    $spec = (int)($_POST['spec'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $tele = trim($_POST['Tele'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $id = (int)($_POST['id00'] ?? 0);

    if ($id !== $loggedDoctorId) {
        $error = '3';
    } elseif (!preg_match("/^[A-Za-z\s]+$/", $name) || !preg_match("/[A-Za-z]/", $name)) {
        $error = '3';
    } elseif (!doc_sathi_email_is_valid($email)) {
        $error = '3';
    } elseif (!doc_sathi_phone_is_valid($tele)) {
        $error = '3';
    } elseif (doc_sathi_doctor_phone_exists($database, $tele, $loggedDoctorId)) {
        $error = '3';
    } elseif (
        $address === ''
        || doc_sathi_parse_date($dob) === null
        || doc_sathi_dob_is_in_future($dob)
        || !doc_sathi_dob_is_within_maximum_age($dob, $doctorMaximumAge)
        || !doc_sathi_dob_meets_minimum_age($dob, $doctorMinimumAge)
    ) {
        $error = '3';
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

            $updateDoctor = $database->prepare(
                "UPDATE doctor
                 SET docemail = ?, docname = ?, docpassword = ?, doctel = ?, docaddress = ?, docdob = ?, specialties = ?
                 WHERE docid = ?"
            );
            $updateDoctor->bind_param("ssssssii", $email, $name, $hashedPassword, $tele, $address, $dob, $spec, $loggedDoctorId);
            $updateDoctor->execute();

            if ($email !== $oldemail) {
                $updateWebuser = $database->prepare("UPDATE webuser SET email = ? WHERE email = ?");
                $updateWebuser->bind_param("ss", $email, $oldemail);
                $updateWebuser->execute();
                $_SESSION["user"] = $email;
            }

            $database->commit();
            $error = '4';
        } catch (Throwable $exception) {
            $database->rollback();
            $error = '3';
        }
    }
}

header("location: account.php");
exit();
?>
