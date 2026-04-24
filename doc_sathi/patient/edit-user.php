<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'p') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$error = '3';
$id = 0;
$useremail = $_SESSION["user"];

$patientStmt = $database->prepare("SELECT pid FROM patient WHERE pemail = ?");
$patientStmt->bind_param("s", $useremail);
$patientStmt->execute();
$patient = $patientStmt->get_result()->fetch_assoc();

if (!$patient) {
    header("location: ../login.php");
    exit();
}

$loggedPatientId = (int)$patient["pid"];

if ($_POST) {
    $formAction = $_POST['form_action'] ?? 'legacy';
    $name = trim($_POST['name'] ?? '');
    $oldemail = trim($_POST["oldemail"] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tele = trim($_POST['Tele'] ?? '');
    $gender = doc_sathi_normalize_gender($_POST['gender'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $id = (int)($_POST['id00'] ?? 0);

    if ($formAction === 'profile') {
        if ($id !== $loggedPatientId) {
            $error = '3';
        } elseif (!preg_match("/^[a-zA-Z ]+$/", $name) || !preg_match("/[a-zA-Z]/", $name)) {
            $error = '3';
        } elseif (!doc_sathi_email_is_valid($email)) {
            $error = '3';
        } elseif (!doc_sathi_phone_is_valid($tele)) {
            $error = '11';
        } elseif (!doc_sathi_gender_is_valid($gender)) {
            $error = '12';
        } elseif ($email !== $oldemail && doc_sathi_email_exists($database, $email, $oldemail)) {
            $error = '1';
        } elseif (doc_sathi_patient_phone_exists($database, $tele, $loggedPatientId)) {
            $error = '9';
        } elseif ($address === '') {
            $error = '3';
        } else {
            try {
                $database->begin_transaction();

                $updatePatient = $database->prepare(
                    "UPDATE patient SET pemail=?, pname=?, pnum=?, gender=?, paddress=? WHERE pid=?"
                );
                $updatePatient->bind_param("sssssi", $email, $name, $tele, $gender, $address, $loggedPatientId);
                $updatePatient->execute();

                if ($email !== $oldemail) {
                    $updateWebuser = $database->prepare("UPDATE webuser SET email=? WHERE email=?");
                    $updateWebuser->bind_param("ss", $email, $oldemail);
                    $updateWebuser->execute();
                    $_SESSION["user"] = $email;
                }

                $database->commit();
                header("location: settings.php?status=profile-updated#profile-details");
                exit();
            } catch (Throwable $exception) {
                $database->rollback();
                $error = '3';
            }
        }

        header("location: settings.php?error=" . $error . "#profile-details");
        exit();
    }

    if ($formAction === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $passwordStmt = $database->prepare("SELECT ppassword FROM patient WHERE pid=? LIMIT 1");
        $passwordStmt->bind_param("i", $loggedPatientId);
        $passwordStmt->execute();
        $passwordRow = $passwordStmt->get_result()->fetch_assoc();
        $storedPassword = (string)($passwordRow['ppassword'] ?? '');
        $currentMatches = password_verify($currentPassword, $storedPassword);

        if (!$currentMatches && password_get_info($storedPassword)['algo'] === 0) {
            $currentMatches = hash_equals($storedPassword, $currentPassword);
        }

        if ($id !== $loggedPatientId) {
            $error = '3';
        } elseif (!$currentMatches) {
            $error = '10';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '2';
        } elseif (!doc_sathi_password_is_strong($newPassword)) {
            $error = '8';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePassword = $database->prepare("UPDATE patient SET ppassword=? WHERE pid=?");
            $updatePassword->bind_param("si", $hashedPassword, $loggedPatientId);
            $updatePassword->execute();

            header("location: settings.php?status=password-updated#password");
            exit();
        }

        header("location: settings.php?error=" . $error . "#password");
        exit();
    }

    if ($id !== $loggedPatientId) {
        $error = '3';
    } elseif (!preg_match("/^[a-zA-Z ]+$/", $name) || !preg_match("/[a-zA-Z]/", $name)) {
        $error = '3';
    } elseif (!doc_sathi_email_is_valid($email)) {
        $error = '3';
    } elseif (!doc_sathi_phone_is_valid($tele)) {
        $error = '11';
    } elseif (!doc_sathi_gender_is_valid($gender)) {
        $error = '12';
    } elseif ($password !== $cpassword) {
        $error = '2';
    } elseif (!doc_sathi_password_is_strong($password)) {
        $error = '8';
    } elseif ($email !== $oldemail && doc_sathi_email_exists($database, $email, $oldemail)) {
        $error = '1';
    } elseif (doc_sathi_patient_phone_exists($database, $tele, $loggedPatientId)) {
        $error = '9';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $database->begin_transaction();

            $updatePatient = $database->prepare(
                "UPDATE patient SET pemail=?, pname=?, ppassword=?, pnum=?, gender=?, paddress=? WHERE pid=?"
            );
            $updatePatient->bind_param("ssssssi", $email, $name, $hashedPassword, $tele, $gender, $address, $loggedPatientId);
            $updatePatient->execute();

            if ($email !== $oldemail) {
                $updateWebuser = $database->prepare("UPDATE webuser SET email=? WHERE email=?");
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

header("location: settings.php?action=edit&error=" . $error . "&id=" . $loggedPatientId);
exit();
?>
