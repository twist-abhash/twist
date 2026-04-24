<?php

    require_once __DIR__ . "/../session_config.php";
    require_once __DIR__ . "/../algorithms.php";

    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
            header("location: appointment.php");
            exit();
        } else {
            $useremail = $_SESSION["user"];
        }
    }else{
        header("location: appointment.php");
        exit();
    }
    
    
    if($_GET && isset($_GET["id"])){
        //import database
        include("../connection.php");

        $id = (int) $_GET["id"];
        $patientLookup = $database->prepare("SELECT pid FROM patient WHERE pemail = ?");
        $patientLookup->bind_param("s", $useremail);
        $patientLookup->execute();
        $patient = $patientLookup->get_result()->fetch_assoc();

        if ($patient && $id > 0) {
            $pid = (int) $patient["pid"];
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
                   AND a.pid = ?
                 LIMIT 1"
            );
            $appointmentLookup->bind_param("ii", $id, $pid);
            doc_sathi_execute($appointmentLookup);
            $appointment = $appointmentLookup->get_result()->fetch_assoc();

            if ($appointment) {
                $workflow = doc_sathi_appointment_status_details($appointment);

                if ($workflow["can_cancel"] ?? false) {
                    $delete = $database->prepare(
                        "DELETE FROM appointment
                         WHERE appoid = ?
                           AND pid = ?
                           AND COALESCE(status, 'confirmed') <> 'completed'
                           AND completed_at IS NULL"
                    );
                    $delete->bind_param("ii", $id, $pid);
                    $delete->execute();
                } else {
                    header("location: appointment.php?action=cancel-locked");
                    exit();
                }
            }
        }

        header("location: appointment.php");
        exit();
    }


?>
