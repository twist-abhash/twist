<?php
require_once __DIR__ . "/../algorithms.php";
include("../connection.php");

$aemail = 'admin1@doc.com';
$password = 'Admin@1239';

if (!doc_sathi_password_is_strong($password)) {
    die(doc_sathi_password_policy_message());
}

if (!doc_sathi_email_is_valid($aemail)) {
    die(doc_sathi_email_policy_message());
}

if (doc_sathi_email_exists($database, $aemail)) {
    die("Admin email already exists.");
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$usertype = 'a';

try {
    $database->begin_transaction();

    $queryAdmin = $database->prepare("INSERT INTO admin (aemail, apassword) VALUES (?, ?)");
    $queryAdmin->bind_param("ss", $aemail, $hashedPassword);
    $queryAdmin->execute();

    $queryWebuser = $database->prepare("INSERT INTO webuser (email, usertype) VALUES (?, ?)");
    $queryWebuser->bind_param("ss", $aemail, $usertype);
    $queryWebuser->execute();

    $database->commit();
    echo "Admin registered successfully.";
} catch (Throwable $exception) {
    $database->rollback();
    echo "Error registering admin.";
}
?>
