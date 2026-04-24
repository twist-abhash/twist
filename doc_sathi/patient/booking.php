<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
        
    <title>Sessions</title>
    <style>
        .popup{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
</style>
</head>
<body>
    <?php


    require_once __DIR__ . "/../session_config.php";
    require_once __DIR__ . "/../algorithms.php";


    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
            header("location: ../login.php");
            exit();
        }else{
            $useremail=$_SESSION["user"];
        }

    }else{
        header("location: ../login.php");
        exit();
    }
    

    //import database
    include("../connection.php");

    $sqlmain = "SELECT * FROM patient WHERE pemail=?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("s", $useremail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch the result row
    $userfetch = $result->fetch_assoc();
    
    if ($userfetch) {
        $userid = $userfetch["pid"];
        $username = $userfetch["pname"];
    } else {
        header("location: ../logout.php?role=p");
        exit();
    }
    

    //echo $userid;
    //echo $username;
    


    date_default_timezone_set('Asia/Kathmandu');

    $today = date('Y-m-d');


 //echo $userid;
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
                                 <p class="profile-title"><?php echo substr($username,0,13)  ?>..</p>
                                 <p class="profile-subtitle"><?php echo substr($useremail,0,22)  ?></p>
                             </td>
                         </tr>
                         <tr>
                             <td colspan="2">
                                 <a href="../logout.php?role=p" ><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                             </td>
                         </tr>
                 </table>
                 </td>
             </tr>
             <tr class="menu-row" >
                    <td class="menu-btn menu-icon-home " >
                        <a href="index.php" class="non-style-link-menu "><div><p class="menu-text">Home</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="department.php" class="non-style-link-menu"><div><p class="menu-text">Specialties</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-session menu-active menu-icon-session-active">
                        <a href="schedule.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Sessions</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Bookings</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Account</p></div></a>
                    </td>
                </tr>
                
            </table>
        </div>
        
        <div class="dash-body">
            <table border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;margin-top:25px; ">
                <tr >
                    <td width="13%" >
                    <a href="schedule.php" ><button  class="login-btn btn-primary-soft btn btn-icon-back"  style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                    </td>
                    <td >
                            <form action="schedule.php" method="post" class="header-search">

                                        <input type="search" name="search" class="input-text header-searchbar" placeholder="Search Doctor name or Email or Date (YYYY-MM-DD)" list="doctors" >&nbsp;&nbsp;
                                        
                                        <?php
                                            echo '<datalist id="doctors">';
                                            $doctorOptionsStmt = $database->prepare("select DISTINCT docname from doctor where verification_status='approved';");
                                            $doctorOptionsStmt->execute();
                                            $list11 = $doctorOptionsStmt->get_result();
                                            $sessionOptionsStmt = $database->prepare("select DISTINCT schedule.title from schedule inner join doctor on schedule.docid=doctor.docid where doctor.verification_status='approved' GROUP BY schedule.title;");
                                            $sessionOptionsStmt->execute();
                                            $list12 = $sessionOptionsStmt->get_result();

                                            for ($y=0;$y<$list11->num_rows;$y++){
                                                $row00=$list11->fetch_assoc();
                                                $d=$row00["docname"];
                                               
                                                echo "<option value='$d'><br/>";
                                               
                                            };


                                            for ($y=0;$y<$list12->num_rows;$y++){
                                                $row00=$list12->fetch_assoc();
                                                $d=$row00["title"];
                                               
                                                echo "<option value='$d'><br/>";
                                                                                         };

                                        echo ' </datalist>';
            ?>   
                                        <input type="Submit" value="Search" class="login-btn btn-primary btn" style="padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;">
                                        </form>
                    </td>
                    <td width="15%">
                        <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                            Today's Date
                        </p>
                        <p class="heading-sub12" style="padding: 0;margin: 0;">
                            <?php  
                                echo htmlspecialchars(date('M d, Y', strtotime($today)), ENT_QUOTES, 'UTF-8');
                        ?>
                        </p>
                    </td>
                    <td width="10%">
                        <button  class="btn-label"  style="display: flex;justify-content: center;align-items: center;"><img src="../img/calendar.svg" width="100%"></button>
                    </td>
                </tr>
             
                <tr>
                    <td colspan="4" style="padding-top:10px;width: 100%;" >
                        <!-- <p class="heading-main12" style="margin-left: 45px;font-size:18px;color:rgb(49, 49, 49);font-weight:400;">Sessions / Booking / <b>Review Booking</b></p> -->  
                    </td>
                </tr>
                
                
                
                <tr>
                   <td colspan="4">
                       <center>
                        <div class="abc scroll">
                        <table width="100%" class="sub-table scrolldown" border="0" style="padding: 50px;border:none">
                            
                        <tbody>
                        
                            <?php
                            
                            if(($_GET)){
                                
                                
                                if(isset($_GET["id"])){
                                    

                                    $id=(int)$_GET["id"];

                                    if ($id <= 0) {
                                        header("location: schedule.php?action=invalid-session");
                                        exit();
                                    }

                                    $sqlmain= "select * from schedule inner join doctor on schedule.docid=doctor.docid where schedule.scheduleid=? and doctor.verification_status='approved' order by schedule.scheduledate desc";
                                    $stmt = $database->prepare($sqlmain);
                                    $stmt->bind_param("i", $id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    //echo $sqlmain;
                                    $row=$result->fetch_assoc();

                                    if (!$row) {
                                        header("location: schedule.php?action=session-not-found");
                                        exit();
                                    }

                                    $validationReason = doc_sathi_validate_schedule_for_booking($row);
                                    if ($validationReason === 'session-expired') {
                                        header("location: schedule.php?action=session-expired&id=" . $id);
                                        exit();
                                    }

                                    if ($validationReason === 'invalid-session') {
                                        header("location: schedule.php?action=invalid-session&id=" . $id);
                                        exit();
                                    }

                                    $scheduleid=$row["scheduleid"];
                                    $title=$row["title"];
                                    $docname=$row["docname"];


                                    $spe=(int)$row["specialties"];
                                    $specialtyStmt = $database->prepare("select sname from specialties where id=?");
                                    $specialtyStmt->bind_param("i", $spe);
                                    $specialtyStmt->execute();
                                    $specialtyRow = $specialtyStmt->get_result()->fetch_assoc();
                                    $specialtiesList = $specialtyRow ? $specialtyRow['sname'] : 'No specialty found';
                                    $docemail=$row["docemail"];
                                    $doctorGender = doc_sathi_gender_label($row["gender"] ?? "");
                                    $doctorGender = $doctorGender === "" ? "Not specified" : $doctorGender;
                                    $clinicName = trim((string)($row["clinic_name"] ?? ""));
                                    $clinicName = $clinicName === "" ? "Not provided" : $clinicName;
                                    $scheduledate=$row["scheduledate"];
                                    $scheduletime=$row["scheduletime"];
                                    $bookingStatus = doc_sathi_schedule_booking_status($database, $id);
                                    $alreadyBooked = doc_sathi_patient_has_booking($database, (int)$userid, $id);
                                    $timeConflict = doc_sathi_patient_overlapping_appointment($database, (int)$userid, $row, $id);
                                    $isFull = !$bookingStatus || $bookingStatus['is_full'];
                                    $apponum = $bookingStatus ? $bookingStatus['next_apponum'] : null;
                                    $apponumPreview = $apponum !== null ? $apponum : '--';

                                    echo '
                                        <form action="booking-complete.php" method="post">
                                            <input type="hidden" name="scheduleid" value="'.$scheduleid.'" >
                                            <input type="hidden" name="apponum" value="'.($apponum !== null ? $apponum : 0).'" >
                                            <input type="hidden" name="date" value="'.$today.'" >
                                    ';echo '
                                    <td style="width: 50%;" rowspan="2">
                                            <div  class="dashboard-items search-items"  >
                                            
                                                <div style="width:100%">
                                                        <div class="h1-search" style="font-size:25px;">
                                                            Session Details
                                                        </div><br><br>
                                                        <div class="h3-search" style="font-size:18px;line-height:30px">
                                                            Doctor name:  &nbsp;&nbsp;<b>'.$docname.'</b><br>
                                                            Doctor Email:  &nbsp;&nbsp;<b>'.$docemail.'</b><br> 
                                                            Doctor Specialties: &nbsp;&nbsp;<b>' . $specialtiesList . '</b><br>
                                                            Doctor Gender: &nbsp;&nbsp;<b>' . htmlspecialchars($doctorGender, ENT_QUOTES, "UTF-8") . '</b><br>
                                                            Clinic / Hospital: &nbsp;&nbsp;<b>' . htmlspecialchars($clinicName, ENT_QUOTES, "UTF-8") . '</b><br>
                                                        </div>
                                                        <div class="h3-search" style="font-size:18px;">
                                                          
                                                        </div><br>
                                                        <div class="h3-search" style="font-size:18px;">
                                                            Session Title: '.$title.'<br>
                                                            Session Scheduled Date: '.$scheduledate.'<br>
                                                            Session Starts : '.$scheduletime.'<br>
                                                            Consultation Fee : <b>NRP 800.00</b>

                                                        </div>
                                                        <br>
                                                        
                                                </div>
                                                        
                                            </div>
                                        </td>
                                        
                                        
                                        
                                        <td style="width: 25%;">
                                            <div  class="dashboard-items search-items"  >
                                            
                                                <div style="width:100%;padding-top: 15px;padding-bottom: 15px;">
                                                        <div class="h1-search" style="font-size:20px;line-height: 35px;margin-left:8px;text-align:center;">
                                                            Your Appointment Number
                                                        </div>
                                                        <center>
                                                        <div class=" dashboard-icons" style="margin-left: 0px;width:90%;font-size:70px;font-weight:800;text-align:center;color:var(--btnnictext);background-color: var(--btnice)">'.$apponumPreview.'</div>
                                                    </center>
                                                       
                                                        </div><br>
                                                        
                                                        <br>
                                                        <br>
                                                </div>
                                                        
                                            </div>
                                        </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                ';
                                                if ($alreadyBooked) {
                                                    echo '<input type="button" class="login-btn btn-primary-soft btn btn-book" disabled style="margin-left:10px;padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;width:95%;text-align: center;opacity:0.6;" value="Already Booked">';
                                                } elseif ($timeConflict) {
                                                    echo '<input type="button" class="login-btn btn-primary-soft btn btn-book" disabled style="margin-left:10px;padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;width:95%;text-align: center;opacity:0.6;" value="Time Conflict">';
                                                } elseif ($isFull) {
                                                    echo '<input type="button" class="login-btn btn-primary-soft btn btn-book" disabled style="margin-left:10px;padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;width:95%;text-align: center;opacity:0.6;" value="Session Full">';
                                                } else {
                                                    echo '<input type="Submit" class="login-btn btn-primary btn btn-book" style="margin-left:10px;padding-left: 25px;padding-right: 25px;padding-top: 10px;padding-bottom: 10px;width:95%;text-align: center;" value="Book now" name="booknow">';
                                                }
                                                if ($timeConflict) {
                                                    echo '<p style="font-size:12px;line-height:1.6;color:#7a5d00;padding:12px 16px 0 16px;margin:0;text-align:left;">You already have appointment #' . (int)($timeConflict["apponum"] ?? 0) . ' with ' . htmlspecialchars((string)($timeConflict["docname"] ?? ''), ENT_QUOTES, "UTF-8") . ' at ' . htmlspecialchars((string)($timeConflict["scheduletime"] ?? ''), ENT_QUOTES, "UTF-8") . ' on ' . htmlspecialchars((string)($timeConflict["scheduledate"] ?? ''), ENT_QUOTES, "UTF-8") . '.</p>';
                                                }
                                                echo '
                                            </form>
                                            </td>
                                        </tr>
                                        '; 
                                        




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
        </div>
    </div>
    
    
   
    </div>

</body>
</html>
