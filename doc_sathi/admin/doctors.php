<?php ob_start();
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION['usertype'] !== 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$adminEmail = $_SESSION["user"];
$today = date('Y-m-d');
$search = trim($_POST["search"] ?? "");

$adminStmt = doc_sathi_prepare($database, "SELECT aid, aemail FROM admin WHERE aemail = ? LIMIT 1");
$adminStmt->bind_param("s", $adminEmail);
doc_sathi_execute($adminStmt);
$admin = $adminStmt->get_result()->fetch_assoc();

$countResult = $database->query("SELECT COUNT(*) AS total FROM doctor");
$totalDoctors = (int)($countResult->fetch_assoc()['total'] ?? 0);

if ($search !== "") {
    $likeSearch = doc_sathi_search_pattern($search);
    $stmt = doc_sathi_prepare(
        $database,
        "SELECT doctor.*, specialties.sname AS specialty_name
         FROM doctor
         LEFT JOIN specialties ON doctor.specialties = specialties.id
         WHERE doctor.docemail = ?
            OR doctor.docname = ?
            OR doctor.docemail LIKE ?
            OR doctor.docname LIKE ?
            OR specialties.sname LIKE ?
            OR doctor.verification_status LIKE ?
         ORDER BY FIELD(doctor.verification_status, 'pending', 'rejected', 'approved'), doctor.docid DESC"
    );
    $stmt->bind_param("ssssss", $search, $search, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
} else {
    $stmt = doc_sathi_prepare(
        $database,
        "SELECT doctor.*, specialties.sname AS specialty_name
         FROM doctor
         LEFT JOIN specialties ON doctor.specialties = specialties.id
         ORDER BY FIELD(doctor.verification_status, 'pending', 'rejected', 'approved'), doctor.docid DESC"
    );
}

doc_sathi_execute($stmt);
$result = $stmt->get_result();

$datalistResult = $database->query("SELECT docname, docemail FROM doctor ORDER BY docname ASC");

$action = $_GET["action"] ?? "";
$actionMessage = [
    'verified' => 'Doctor approved successfully.',
    'rejected' => 'Doctor rejected successfully.',
    'invalid-doctor' => 'Invalid doctor selected.',
    'missing-reason' => 'Please provide a rejection reason.',
    'doctor-deleted' => 'Doctor removed successfully.',
    'doctor-not-found' => 'Doctor record was not found.',
    'error-deleting-doctor' => 'Could not remove doctor.',
    'manual-add-disabled' => 'Admin-created doctor accounts are disabled. Doctors must register themselves.',
][$action] ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css?v=20260421-admin-pages">
    <link rel="stylesheet" href="../css/verification.css">
    <title>Doctor Verification</title>
    <style>
        .popup,
        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .status-cell {
            min-width: 220px;
        }
        .status-remark {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.45;
            color: #4a4f5a;
            white-space: normal;
        }
        .status-remark strong {
            display: inline-block;
            margin-right: 4px;
            color: #721c24;
        }
        .notice {
            margin-left: 45px;
            margin-top: 15px;
            padding: 12px 16px;
            width: 86%;
            border-radius: 8px;
            background: #eef6ff;
            color: #15558a;
            font-size: 14px;
        }
        .inline-form {
            display: inline-block;
            margin: 0 4px;
        }
        .reject-box {
            width: 90%;
            min-height: 110px;
            padding: 12px;
            border: 1px solid #d6d6d6;
            border-radius: 6px;
            resize: vertical;
        }
    </style>
</head>
<body class="admin-subpage admin-doctors-page">
    <div class="container">
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle"><?php echo h($admin['aemail'] ?? $adminEmail); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php?role=a"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor menu-active menu-icon-doctor-active">
                        <a href="doctors.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Doctors</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctor-verifications.php" class="non-style-link-menu"><div><p class="menu-text">Verifications</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Sessions</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointments</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><div><p class="menu-text">Patients</p></div></a>
                    </td>
                </tr>
            </table>
        </div>
        <div class="dash-body">
            <main class="admin-page-shell">
                <header class="admin-page-header admin-subpage-header">
                    <a href="index.php" class="admin-back-button">Back</a>
                    <div class="admin-title-block">
                        <span class="admin-eyebrow">Doctor Directory</span>
                        <h1>Doctors</h1>
                        <p>Review registered doctors, verification documents, statuses, and account actions.</p>
                    </div>
                    <form action="" method="post" class="admin-header-search">
                        <input type="search" name="search" class="input-text admin-search-input" placeholder="Search doctor name, email, specialty, or status" list="doctors" value="<?php echo h($search); ?>">
                        <datalist id="doctors">
                            <?php while ($row = $datalistResult->fetch_assoc()): ?>
                                <option value="<?php echo h($row["docname"]); ?>">
                                <option value="<?php echo h($row["docemail"]); ?>">
                            <?php endwhile; ?>
                        </datalist>
                        <button type="submit" class="admin-btn">Search</button>
                    </form>
                    <aside class="admin-date-card" aria-label="Today">
                        <span>Today's Date</span>
                        <strong><?php echo h(date('M d, Y', strtotime($today))); ?></strong>
                    </aside>
                </header>

                <?php if ($actionMessage !== ""): ?>
                    <div class="admin-alert success"><?php echo h($actionMessage); ?></div>
                <?php endif; ?>

                <section class="admin-panel admin-list-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span class="admin-panel-kicker">Directory</span>
                            <h2>Doctors<?php echo $search !== "" ? " matching your search" : ""; ?></h2>
                            <p><?php echo $search !== "" ? (int)$result->num_rows : (int)$totalDoctors; ?> doctor records available.</p>
                        </div>
                    </div>

            <table class="admin-shell-table" border="0" width="100%" style="border-spacing:0;margin:0;padding:0;margin-top:25px;">
                <tr>
                    <td colspan="4" style="padding-top:30px;">
                        <p class="heading-main12" style="margin-left:45px;font-size:20px;color:rgb(49,49,49)">Doctor Verification</p>
                        <p style="margin-left:45px;color:#666;">Doctors register themselves and appear here for approval or rejection. Admin-created doctor accounts are disabled.</p>
                        <?php if ($actionMessage !== ""): ?>
                            <div class="notice"><?php echo h($actionMessage); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding-top:10px;">
                        <p class="heading-main12" style="margin-left:45px;font-size:18px;color:rgb(49,49,49)">Doctors (<?php echo $search !== "" ? $result->num_rows : $totalDoctors; ?>)</p>
                    </td>
                </tr>
                <tr class="admin-table-row">
                    <td colspan="4">
                        <center>
                            <div class="abc scroll">
                                <table width="100%" class="sub-table scrolldown" border="0">
                                    <thead>
                                        <tr>
                                            <th class="table-headin">Doctor Name</th>
                                            <th class="table-headin">Email</th>
                                            <th class="table-headin">Specialty</th>
                                            <th class="table-headin">Status</th>
                                            <th class="table-headin">Document</th>
                                            <th class="table-headin">Events</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result->num_rows === 0): ?>
                                            <tr>
                                                <td colspan="6">
                                                    <br><br><br>
                                                    <center>
                                                        <img src="../img/notfound.svg" width="25%">
                                                        <br>
                                                        <p class="heading-main12" style="font-size:20px;color:rgb(49,49,49)">No doctors found.</p>
                                                        <a class="non-style-link" href="doctors.php"><button class="login-btn btn-primary-soft btn">&nbsp; Show all Doctors &nbsp;</button></a>
                                                    </center>
                                                    <br><br><br>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <?php
                                                $docid = (int)$row["docid"];
                                                $status = $row["verification_status"] ?: 'pending';
                                                $certification = doc_sathi_doctor_verification_document_path($row);
                                                $documentGroups = doc_sathi_fetch_doctor_documents($database, $docid);
                                                $documentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
                                                $hasStandaloneDocument = $certification !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $certification);
                                                $totalDocumentCount = $documentCount + ($hasStandaloneDocument ? 1 : 0);
                                                $remarks = trim((string)(($row["admin_remarks"] ?? "") ?: ($row["rejection_reason"] ?? "")));
                                                ?>
                                                <tr>
                                                    <td>&nbsp;<?php echo h(substr($row["docname"], 0, 30)); ?></td>
                                                    <td><?php echo h(substr($row["docemail"], 0, 30)); ?></td>
                                                    <td><?php echo h(substr($row["specialty_name"] ?? "Not selected", 0, 25)); ?></td>
                                                    <td class="status-cell">
                                                        <span class="status-badge status-<?php echo h($status); ?>"><?php echo h(doc_sathi_doctor_status_label($status)); ?></span>
                                                        <?php if ($status === 'rejected' && $remarks !== ''): ?>
                                                            <div class="status-remark"><strong>Reason:</strong><?php echo h($remarks); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($totalDocumentCount > 0): ?>
                                                            <?php echo (int)$totalDocumentCount; ?> uploaded file<?php echo $totalDocumentCount === 1 ? "" : "s"; ?>
                                                            <br><a href="?action=documents&id=<?php echo $docid; ?>">View documents</a>
                                                        <?php else: ?>
                                                            Not uploaded
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="admin-row-actions">
                                                            <a href="?action=view&id=<?php echo $docid; ?>" class="verify-button secondary">View</a>
                                                            <?php if ($status !== 'approved'): ?>
                                                                <form action="verify-doctor.php" method="post" class="inline-form">
                                                                    <input type="hidden" name="docid" value="<?php echo $docid; ?>">
                                                                    <button type="submit" class="verify-button">Approve</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ($status !== 'rejected'): ?>
                                                                <a href="?action=reject&id=<?php echo $docid; ?>" class="verify-button danger-soft">Reject</a>
                                                            <?php endif; ?>
                                                            <a href="?action=drop&id=<?php echo $docid; ?>&name=<?php echo urlencode($row["docname"]); ?>" class="verify-button danger-soft">Remove</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
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
    if ($_GET) {
        $id = (int)($_GET["id"] ?? 0);
        if ($action === 'documents' && $id > 0) {
            $doctor = doc_sathi_get_doctor_by_id($database, $id);
            if ($doctor) {
                $certification = doc_sathi_doctor_verification_document_path($doctor);
                $documentGroups = doc_sathi_fetch_doctor_documents($database, (int)$doctor["docid"]);
                $documentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
                $hasStandaloneDocument = $certification !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $certification);
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <a class="close" href="doctors.php">&times;</a>
                            <div class="content">Documents</div>
                            <div style="display:flex;justify-content:center;">
                                <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Doctor:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($doctor["docname"]) . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Uploaded Documents:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">';

                if ($documentCount > 0 || $hasStandaloneDocument) {
                    echo '<div class="verify-document-groups admin-document-groups">';
                    if ($documentCount > 0) {
                        foreach ($documentGroups as $group) {
                            echo '<div class="verify-document-group"><strong>' . h($group["label"]) . '</strong>';
                            if (count($group["documents"]) === 0) {
                                echo '<span>No files uploaded</span>';
                            } else {
                                foreach ($group["documents"] as $document) {
                                    echo '<a href="../' . h($document["file_path"]) . '" target="_blank" rel="noopener">' . h($document["original_name"]) . '</a>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                    if ($hasStandaloneDocument) {
                        echo '<div class="verify-document-group"><strong>Documents</strong>';
                        echo '<a href="../' . h($certification) . '" target="_blank" rel="noopener">Open document</a>';
                        echo '</div>';
                    }
                    echo '</div><br>';
                } else {
                    echo 'Not uploaded<br><br>';
                }

                echo '</td></tr>
                                    <tr><td colspan="2"><a href="doctors.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a></td></tr>
                                </table>
                            </div>
                        </center>
                        <br><br>
                    </div>
                </div>';
            }
        } elseif ($action === 'view' && $id > 0) {
            $doctor = doc_sathi_get_doctor_by_id($database, $id);
            if ($doctor) {
                $certification = doc_sathi_doctor_verification_document_path($doctor);
                $documentGroups = doc_sathi_fetch_doctor_documents($database, (int)$doctor["docid"]);
                $documentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
                $hasStandaloneDocument = $certification !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $certification);
                $address = trim((string)($doctor["docaddress"] ?? ""));
                $dob = trim((string)($doctor["docdob"] ?? ""));
                $gender = doc_sathi_gender_label($doctor["gender"] ?? "");
                $gender = $gender === "" ? "Not specified" : $gender;
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <a class="close" href="doctors.php">&times;</a>
                            <div class="content">Doctor Details</div>
                            <div style="display:flex;justify-content:center;">
                                <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                    <tr><td><p style="padding:0;margin:0;text-align:left;font-size:25px;font-weight:500;">View Details.</p><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Name:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($doctor["docname"]) . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Email:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($doctor["docemail"]) . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Telephone:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($doctor["doctel"]) . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Address:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($address !== "" ? $address : "Not provided") . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Date of Birth:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($dob !== "" ? $dob : "Not provided") . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Gender:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($gender) . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Specialty:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h($doctor["specialty_name"] ?? "Not selected") . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">License / Registration No.:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h(($doctor["license_number"] ?? "") !== "" ? $doctor["license_number"] : "Not submitted") . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Clinic / Hospital:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h(($doctor["clinic_name"] ?? "") !== "" ? $doctor["clinic_name"] : "Not provided") . '<br><br></td></tr>
                                    <tr><td class="label-td" colspan="2"><label class="form-label">Verification Status:</label></td></tr>
                                    <tr><td class="label-td" colspan="2">' . h(doc_sathi_doctor_status_label($doctor["verification_status"])) . '<br><br></td></tr>';

                if (($doctor["verification_status"] ?? '') === 'rejected') {
                    echo '<tr><td class="label-td" colspan="2"><label class="form-label">Rejection Reason:</label></td></tr>
                          <tr><td class="label-td" colspan="2">' . h($doctor["rejection_reason"] ?? '') . '<br><br></td></tr>';
                }

                echo '<tr><td class="label-td" colspan="2"><label class="form-label">Documents:</label></td></tr>
                      <tr><td class="label-td" colspan="2">';

                if ($documentCount > 0 || $hasStandaloneDocument) {
                    echo '<div class="verify-document-groups admin-document-groups">';
                    if ($documentCount > 0) {
                        foreach ($documentGroups as $group) {
                            echo '<div class="verify-document-group"><strong>' . h($group["label"]) . '</strong>';
                            if (count($group["documents"]) === 0) {
                                echo '<span>No files uploaded</span>';
                            } else {
                                foreach ($group["documents"] as $document) {
                                    echo '<a href="../' . h($document["file_path"]) . '" target="_blank" rel="noopener">' . h($document["original_name"]) . '</a>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                    if ($hasStandaloneDocument) {
                        echo '<div class="verify-document-group"><strong>Documents</strong>';
                        echo '<a href="../' . h($certification) . '" target="_blank" rel="noopener">Open document</a>';
                        echo '</div>';
                    }
                    echo '</div><br>';
                } else {
                    echo 'Not uploaded<br><br>';
                }

                echo '</td></tr>
                                     <tr><td colspan="2"><a href="doctors.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a></td></tr>
                                 </table>
                             </div>
                        </center>
                        <br><br>
                    </div>
                </div>';
            }
        } elseif ($action === 'reject' && $id > 0) {
            $doctor = doc_sathi_get_doctor_by_id($database, $id);
            if ($doctor) {
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup admin-reject-dialog">
                        <a class="close" href="doctors.php">&times;</a>
                        <div class="admin-reject-header">
                            <span class="admin-eyebrow">Review Action</span>
                            <h2>Reject Doctor</h2>
                            <p>Explain what needs to be corrected before this doctor can be approved.</p>
                        </div>
                        <div class="admin-reject-subject">
                            <span class="admin-reject-subject-label">Doctor</span>
                            <strong class="admin-reject-subject-name">' . h($doctor["docname"]) . '</strong>
                            <span class="admin-reject-subject-meta">' . h($doctor["docemail"]) . '</span>
                        </div>
                        <form action="reject-doctor.php" method="post" class="admin-reject-form">
                            <input type="hidden" name="docid" value="' . (int)$doctor["docid"] . '">
                            <label class="admin-reject-label" for="rejection_reason_' . (int)$doctor["docid"] . '">Reason for rejection</label>
                            <p class="admin-reject-helper">This message is shown to the doctor so they know what to fix before resubmitting.</p>
                            <textarea class="reject-box" id="rejection_reason_' . (int)$doctor["docid"] . '" name="rejection_reason" maxlength="1000" required placeholder="Describe the issue, missing proof, or correction required"></textarea>
                            <div class="admin-reject-actions">
                                <a href="doctors.php" class="admin-link-button">Cancel</a>
                                <button type="submit" class="admin-btn admin-danger-button">Reject Verification</button>
                            </div>
                        </form>
                    </div>
                </div>';
            }
        } elseif ($action === 'drop' && $id > 0) {
            $nameget = $_GET["name"] ?? "";
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <center>
                        <h2>Are you sure?</h2>
                        <a class="close" href="doctors.php">&times;</a>
                        <div class="content">You want to delete this record<br>(' . h(substr($nameget, 0, 40)) . ').</div>
                        <div style="display:flex;justify-content:center;">
                            <a href="delete-doctor.php?id=' . $id . '" class="non-style-link"><button class="btn-primary btn" style="display:flex;justify-content:center;align-items:center;margin:10px;padding:10px;">&nbsp;Yes&nbsp;</button></a>
                            <a href="doctors.php" class="non-style-link"><button class="btn-primary btn" style="display:flex;justify-content:center;align-items:center;margin:10px;padding:10px;">&nbsp;&nbsp;No&nbsp;&nbsp;</button></a>
                        </div>
                    </center>
                </div>
            </div>';
        }
    }
    ?>
</body>
</html>
