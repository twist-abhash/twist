<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css?v=20260421-admin-pages">
        
    <title>Patients</title>
    <style>
        .popup{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
</style>
</head>
<body class="admin-subpage admin-patients-page">
    <?php


    require_once __DIR__ . "/../session_config.php";
    require_once __DIR__ . "/../algorithms.php";


    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
            header("location: ../login.php");
            exit();
        }

    }else{
        header("location: ../login.php");
        exit();
    }
    
    

    //import database
    include("../connection.php");

    
    ?>
    <div class="container">
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px" >
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle"><?php echo htmlspecialchars(substr($_SESSION["user"], 0, 28), ENT_QUOTES, 'UTF-8'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                <a href="../logout.php?role=a" ><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                                </td>
                            </tr>
                    </table>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-dashbord" >
                        <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor ">
                        <a href="doctors.php" class="non-style-link-menu "><div><p class="menu-text">Doctors</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctor-verifications.php" class="non-style-link-menu"><div><p class="menu-text">Verifications</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Sessions</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointments</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-patient  menu-active menu-icon-patient-active">
                        <a href="patient.php" class="non-style-link-menu  non-style-link-menu-active"><div><p class="menu-text">Patients</p></div></a>
                    </td>
                </tr>

            </table>
        </div>
        <div class="dash-body">
            <main class="admin-page-shell">
                <?php
                    date_default_timezone_set('Asia/Kathmandu');
                    $date = date('Y-m-d');
                    $patientTotalResult = $database->query("select * from patient;");
                    $patientTotal = $patientTotalResult ? $patientTotalResult->num_rows : 0;
                ?>
                <header class="admin-page-header admin-subpage-header">
                    <a href="index.php" class="admin-back-button">Back</a>
                    <div class="admin-title-block">
                        <span class="admin-eyebrow">Patient Directory</span>
                        <h1>Patients</h1>
                        <p>Search patient profiles and review contact, birth date, and address details.</p>
                    </div>
                    <form action="" method="post" class="admin-header-search">
                        <input type="search" name="search" class="input-text admin-search-input" placeholder="Search patient name or email" list="patient-search">
                        <datalist id="patient-search">
                            <?php
                                $headerPatientList = $database->query("select pname,pemail from patient order by pname asc;");
                                if ($headerPatientList) {
                                    while ($patient = $headerPatientList->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($patient["pname"], ENT_QUOTES, 'UTF-8') . '">';
                                        echo '<option value="' . htmlspecialchars($patient["pemail"], ENT_QUOTES, 'UTF-8') . '">';
                                    }
                                }
                            ?>
                        </datalist>
                        <button type="submit" class="admin-btn">Search</button>
                    </form>
                    <aside class="admin-date-card" aria-label="Today">
                        <span>Today's Date</span>
                        <strong><?php echo htmlspecialchars(date('M d, Y', strtotime($date)), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </aside>
                </header>

                <section class="admin-panel admin-list-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span class="admin-panel-kicker">Patient Records</span>
                            <h2>All Patients</h2>
                            <p><?php echo (int)$patientTotal; ?> patient profiles are currently stored.</p>
                        </div>
                    </div>

            <table class="admin-shell-table" border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;margin-top:25px; ">
               
                
                <tr>
                    <td colspan="4" style="padding-top:10px;">
                        <p class="heading-main12" style="margin-left: 45px;font-size:18px;color:rgb(49, 49, 49)">All Patients (<?php echo $patientTotal; ?>)</p>
                    </td>
                     
                </tr>
                <?php
                    if($_POST && trim($_POST["search"] ?? "") !== ""){
                        $keyword=trim($_POST["search"]);
                        $likeKeyword=doc_sathi_search_pattern($keyword);
                        $stmt = $database->prepare(
                            "select * from patient
                             where pemail=? or pname=?
                                or pemail like ? or pname like ?
                             order by pid desc"
                        );
                        $stmt->bind_param("ssss", $keyword, $keyword, $likeKeyword, $likeKeyword);
                        $stmt->execute();
                        $result = $stmt->get_result();
                    }else{
                        $result= $database->query("select * from patient order by pid desc");
                    }



                ?>
                  
                <tr class="admin-table-row">
                   <td colspan="4">
                       <center>
                        <div class="abc scroll">
                        <table width="93%" class="sub-table scrolldown"  style="border-spacing:0;">
                        <thead>
                        <tr>
                                <th class="table-headin">
                                    
                                
                                Name
                                
                                </th>
                                
                                <th class="table-headin">
                                
                            
                                Telephone
                                
                                </th>
                                <th class="table-headin">
                                    Email
                                </th>
                                <th class="table-headin">
                                    
                                    Date of Birth
                                    
                                </th>
                                <th class="table-headin">
                                    
                                    Events
                                    
                                </tr>
                        </thead>
                        <tbody>
                        
                            <?php

                                
                                if($result->num_rows==0){
                                    echo '<tr>
                                    <td colspan="4">
                                    <br><br><br><br>
                                    <center>
                                    <img src="../img/notfound.svg" width="25%">
                                    
                                    <br>
                                    <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">No matching patients found.</p>
                                    <a class="non-style-link" href="patient.php"><button  class="login-btn btn-primary-soft btn"  style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; Show all Patients &nbsp;</font></button>
                                    </a>
                                    </center>
                                    <br><br><br><br>
                                    </td>
                                    </tr>';
                                    
                                }
                                else{
                                for ( $x=0; $x<$result->num_rows;$x++){
                                    $row=$result->fetch_assoc();
                                    $pid=$row["pid"];
                                    $name=$row["pname"];
                                    $email=$row["pemail"];
                                    $dob=$row["pdob"];
                                    $tel=$row["pnum"];
                                    
                                    echo '<tr>
                                        <td> &nbsp;'.
                                        substr($name,0,35)
                                        .'</td>
                                        <td>
                                            '.substr($tel,0,10).'
                                        </td>
                                        <td>
                                        '.substr($email,0,20).'
                                         </td>
                                        <td>
                                        '.substr($dob,0,10).'
                                        </td>
                                        <td >
                                        <div style="display:flex;justify-content: center;">
                                        
                                        <a href="?action=view&id='.$pid.'" class="non-style-link"><button  class="btn-primary-soft btn button-icon btn-view"  style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">View</font></button></a>
                                       
                                        </div>
                                        </td>
                                    </tr>';
                                    
                                }
                            }
                                 
                            ?>
 
                            </tbody>

                        </table>
                        </div>
                        </center>
                   </td> 
                </tr>
                       
                        
                        
            </table>
                </section>
            </main>
        </div>
    </div>
    <?php 
    if($_GET){
        
        $id=isset($_GET["id"]) ? (int)$_GET["id"] : 0;
        $action=$_GET["action"] ?? "";
            $stmt = $database->prepare("select * from patient where pid=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result= $stmt->get_result();
            $row=$result->fetch_assoc();
            if (!$row) {
                header("location: patient.php");
                exit();
            }
            $name=$row["pname"];
            $email=$row["pemail"];
            $dob=$row["pdob"];
            $tele=$row["pnum"];
            $address=$row["paddress"];
            $gender = doc_sathi_gender_label($row["gender"] ?? "");
            $gender = $gender === "" ? "Not specified" : $gender;
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <a class="close" href="patient.php">&times;</a>
                        <div class="content">

                        </div>
                        <div style="display: flex;justify-content: center;">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details.</p><br><br>
                                </td>
                            </tr>
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Patient ID: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    P-'.$id.'<br><br>
                                </td>
                                
                            </tr>
                            
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Name: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    '.$name.'<br><br>
                                </td>
                                
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Email" class="form-label">Email: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$email.'<br><br>
                                </td>
                            </tr>
                            
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Tele" class="form-label">Telephone: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$tele.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="gender" class="form-label">Gender: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.htmlspecialchars($gender, ENT_QUOTES, 'UTF-8').'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="spec" class="form-label">Address: </label>
                                    
                                </td>
                            </tr>
                            <tr>
                            <td class="label-td" colspan="2">
                            '.$address.'<br><br>
                            </td>
                            </tr>
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Date of Birth: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    '.$dob.'<br><br>
                                </td>
                                
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="patient.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn" ></a>
                                
                                    
                                </td>
                
                            </tr>
                           

                        </table>
                        </div>
                    </center>
                    <br><br>
            </div>
            </div>
            ';
        
    };

?>
</div>

</body>
</html>
