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
    <link rel="stylesheet" href="../css/verification.css">
    <title>Doctor Verifications</title>
    <style>
        .verify-status-cell {
            min-width: 180px;
        }
        .verify-status-remark {
            margin-top: 8px;
            font-size: 13px;
            line-height: 1.45;
            color: #4a4f5a;
            white-space: normal;
        }
        .verify-status-remark strong {
            display: inline-block;
            margin-right: 4px;
            color: #8f2d3f;
        }
    </style>
</head>
<body class="admin-subpage admin-verifications-page">
<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_date($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Not available';
    }

    return date('M d, Y h:i A', strtotime($value));
}

function display_queue_datetime($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return [
        'date' => date('M d, Y', $timestamp),
        'time' => date('h:i A', $timestamp),
    ];
}

function display_queue_status_label($status)
{
    $status = $status ?: 'pending';

    switch ($status) {
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Pending';
    }
}

$adminEmail = $_SESSION["user"];
$statusFilter = $_GET["status"] ?? "all";
$allowedStatuses = ["all", "pending", "approved", "rejected"];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "all";
}

$search = trim($_GET["search"] ?? "");
$likeSearch = doc_sathi_search_pattern($search);
$action = $_GET["action"] ?? "";
$actionMessage = [
    "verified" => "Doctor approved successfully.",
    "rejected" => "Doctor rejected successfully.",
    "invalid-doctor" => "Invalid doctor selected.",
    "missing-reason" => "Please provide rejection remarks.",
][$action] ?? "";

$adminStmt = doc_sathi_prepare($database, "SELECT aid, aemail FROM admin WHERE aemail = ? LIMIT 1");
$adminStmt->bind_param("s", $adminEmail);
doc_sathi_execute($adminStmt);
$admin = $adminStmt->get_result()->fetch_assoc();

$countRows = ["pending" => 0, "approved" => 0, "rejected" => 0];
$countResult = $database->query("SELECT verification_status, COUNT(*) AS total FROM doctor GROUP BY verification_status");
while ($countRow = $countResult->fetch_assoc()) {
    $countRows[$countRow["verification_status"] ?: "pending"] = (int)$countRow["total"];
}
$totalCount = array_sum($countRows);

$baseSql = "SELECT doctor.*, specialties.sname AS specialty_name
            FROM doctor
            LEFT JOIN specialties ON doctor.specialties = specialties.id";
$orderSql = " ORDER BY FIELD(doctor.verification_status, 'pending', 'rejected', 'approved'),
                     doctor.verification_submitted_at DESC,
                     doctor.docid DESC";

if ($statusFilter !== "all" && $search !== "") {
    $stmt = doc_sathi_prepare(
        $database,
        $baseSql . " WHERE doctor.verification_status = ?
                     AND (doctor.docname LIKE ? OR doctor.docemail LIKE ? OR doctor.license_number LIKE ? OR specialties.sname LIKE ?)" . $orderSql
    );
    $stmt->bind_param("sssss", $statusFilter, $likeSearch, $likeSearch, $likeSearch, $likeSearch);
} elseif ($statusFilter !== "all") {
    $stmt = doc_sathi_prepare($database, $baseSql . " WHERE doctor.verification_status = ?" . $orderSql);
    $stmt->bind_param("s", $statusFilter);
} elseif ($search !== "") {
    $stmt = doc_sathi_prepare(
        $database,
        $baseSql . " WHERE doctor.docname LIKE ? OR doctor.docemail LIKE ? OR doctor.license_number LIKE ? OR specialties.sname LIKE ?" . $orderSql
    );
    $stmt->bind_param("ssss", $likeSearch, $likeSearch, $likeSearch, $likeSearch);
} else {
    $stmt = doc_sathi_prepare($database, $baseSql . $orderSql);
}

doc_sathi_execute($stmt);
$result = $stmt->get_result();
?>

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
                                <p class="profile-subtitle"><?php echo h($admin["aemail"] ?? $adminEmail); ?></p>
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
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor menu-active menu-icon-doctor-active">
                    <a href="doctor-verifications.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Verifications</p></div></a>
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
        <div class="admin-page-shell">
            <header class="admin-page-header admin-subpage-header">
                <a href="index.php" class="admin-back-button">Back</a>
                <div class="admin-title-block">
                    <span class="admin-eyebrow">Admin Review</span>
                    <h1>Verifications</h1>
                    <p>Review submitted doctor credentials, supporting documents, and approval decisions.</p>
                </div>
                <form action="" method="get" class="admin-header-search">
                    <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">
                    <input type="search" name="search" class="input-text admin-search-input" placeholder="Search doctor, email, license, or specialty" value="<?php echo h($search); ?>">
                    <button type="submit" class="admin-btn">Search</button>
                </form>
                <aside class="admin-date-card" aria-label="Today">
                    <span>Today's Date</span>
                    <strong><?php echo h(date('M d, Y')); ?></strong>
                </aside>
            </header>

        <main class="verify-shell">
            <section class="verify-hero">
                <div>
                    <p class="verify-eyebrow">Admin Review</p>
                    <h1>Doctor Verification Management</h1>
                    <p>Review submitted license details and supporting documents before doctors become bookable by patients.</p>
                </div>
                <aside class="verify-status-card">
                    <span class="verify-badge pending">Pending: <?php echo (int)$countRows["pending"]; ?></span>
                    <dl>
                        <div>
                            <dt>Approved</dt>
                            <dd><?php echo (int)$countRows["approved"]; ?></dd>
                        </div>
                        <div>
                            <dt>Rejected</dt>
                            <dd><?php echo (int)$countRows["rejected"]; ?></dd>
                        </div>
                        <div>
                            <dt>Total Doctors</dt>
                            <dd><?php echo (int)$totalCount; ?></dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="admin-stats-grid admin-verification-stats" aria-label="Verification summary">
                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Pending</span>
                        <strong><?php echo (int)$countRows["pending"]; ?></strong>
                        <p>Doctors awaiting review.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-verifications" aria-hidden="true"></span>
                </article>
                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Approved</span>
                        <strong><?php echo (int)$countRows["approved"]; ?></strong>
                        <p>Doctors cleared for booking.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-doctors" aria-hidden="true"></span>
                </article>
                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Rejected</span>
                        <strong><?php echo (int)$countRows["rejected"]; ?></strong>
                        <p>Submissions needing correction.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-bookings" aria-hidden="true"></span>
                </article>
                <article class="admin-stat-card">
                    <div>
                        <span class="admin-stat-label">Total Doctors</span>
                        <strong><?php echo (int)$totalCount; ?></strong>
                        <p>Total doctor accounts in review scope.</p>
                    </div>
                    <span class="admin-stat-icon admin-stat-calendar" aria-hidden="true"></span>
                </article>
            </section>

            <section class="verify-card" style="margin-top:22px;">
                <?php if ($actionMessage !== ""): ?>
                    <div class="verify-alert success"><?php echo h($actionMessage); ?></div>
                <?php endif; ?>

                <h2>Verification Queue</h2>
                <p class="verify-copy">Filter by status, view documents, and approve or reject each submission.</p>
                <div class="verify-filter-row">
                    <?php foreach ($allowedStatuses as $filter): ?>
                        <a class="<?php echo $statusFilter === $filter ? "active" : ""; ?>" href="?status=<?php echo h($filter); ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo h(ucfirst($filter)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="verify-table-wrap">
                    <table class="verify-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Specialty</th>
                                <th>License No.</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                                <th>Document</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows === 0): ?>
                                <tr>
                                    <td colspan="9">No doctor verification records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($doctor = $result->fetch_assoc()): ?>
                                    <?php
                                    $docid = (int)$doctor["docid"];
                                    $status = $doctor["verification_status"] ?: "pending";
                                    $documentPath = doc_sathi_doctor_verification_document_path($doctor);
                                    $documentGroups = doc_sathi_fetch_doctor_documents($database, $docid);
                                    $documentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
                                    $hasStandaloneDocument = $documentPath !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $documentPath);
                                    $totalDocumentCount = $documentCount + ($hasStandaloneDocument ? 1 : 0);
                                    $reviewedAt = ($doctor["verification_reviewed_at"] ?? "") ?: ($doctor["verified_at"] ?? "");
                                    $submittedAtParts = display_queue_datetime($doctor["verification_submitted_at"] ?? "");
                                    $reviewedAtParts = display_queue_datetime($reviewedAt);
                                    $remarks = trim((string)(($doctor["admin_remarks"] ?? "") ?: ($doctor["rejection_reason"] ?? "")));
                                    ?>
                                    <tr>
                                        <td><?php echo h($doctor["docname"]); ?></td>
                                        <td><?php echo h($doctor["docemail"]); ?></td>
                                        <td><?php echo h($doctor["specialty_name"] ?? "Not selected"); ?></td>
                                        <td><?php echo h(($doctor["license_number"] ?? "") !== "" ? $doctor["license_number"] : "Not submitted"); ?></td>
                                        <td class="verify-status-cell">
                                            <span class="verify-badge <?php echo h($status); ?>"><?php echo h(display_queue_status_label($status)); ?></span>
                                            <?php if ($status === "rejected" && $remarks !== ""): ?>
                                                <div class="verify-status-remark"><strong>Reason:</strong><?php echo h($remarks); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="verify-date-cell">
                                            <?php if ($submittedAtParts !== null): ?>
                                                <span class="verify-datetime">
                                                    <span><?php echo h($submittedAtParts['date']); ?></span>
                                                    <span><?php echo h($submittedAtParts['time']); ?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="verify-date-muted">Not available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="verify-date-cell">
                                            <?php if ($reviewedAtParts !== null): ?>
                                                <span class="verify-datetime">
                                                    <span><?php echo h($reviewedAtParts['date']); ?></span>
                                                    <span><?php echo h($reviewedAtParts['time']); ?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="verify-date-muted">Not available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="verify-doc-cell">
                                            <?php if ($totalDocumentCount > 0): ?>
                                                <?php echo (int)$totalDocumentCount; ?> uploaded file<?php echo $totalDocumentCount === 1 ? "" : "s"; ?>
                                                <br><a href="?action=documents&id=<?php echo $docid; ?>&status=<?php echo h($statusFilter); ?>">View documents</a>
                                            <?php else: ?>
                                                Not uploaded
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="verify-actions">
                                                <a class="verify-button secondary" href="?action=view&id=<?php echo $docid; ?>&status=<?php echo h($statusFilter); ?>">View</a>
                                                <?php if ($status !== "approved"): ?>
                                                    <form action="verify-doctor.php" method="post">
                                                        <input type="hidden" name="docid" value="<?php echo $docid; ?>">
                                                        <input type="hidden" name="source" value="verifications">
                                                        <button type="submit" class="verify-button">Approve</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($status !== "rejected"): ?>
                                                    <a class="verify-button danger-soft" href="?action=reject&id=<?php echo $docid; ?>&status=<?php echo h($statusFilter); ?>">Reject</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
        </div>
    </div>
</div>

<?php
if ($_GET) {
    $modalId = (int)($_GET["id"] ?? 0);
    if ($action === "documents" && $modalId > 0) {
        $doctor = doc_sathi_get_doctor_by_id($database, $modalId);
        if ($doctor) {
            $documentPath = doc_sathi_doctor_verification_document_path($doctor);
            $documentGroups = doc_sathi_fetch_doctor_documents($database, (int)$doctor["docid"]);
            $documentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
            $hasStandaloneDocument = $documentPath !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $documentPath);
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <center>
                        <h2>Documents</h2>
                        <a class="close" href="doctor-verifications.php?status=' . h($statusFilter) . '">&times;</a>
                        <div style="display:flex;justify-content:center;">
                            <table width="85%" class="sub-table scrolldown add-doc-form-container" border="0">
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
                    echo '<a href="../' . h($documentPath) . '" target="_blank" rel="noopener">Open document</a>';
                    echo '</div>';
                }
                echo '</div><br>';
            } else {
                echo 'Not uploaded<br><br>';
            }

            echo '</td></tr>
                                <tr><td colspan="2"><a href="doctor-verifications.php?status=' . h($statusFilter) . '"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a></td></tr>
                            </table>
                        </div>
                    </center>
                </div>
            </div>';
        }
    } elseif ($action === "view" && $modalId > 0) {
        $doctor = doc_sathi_get_doctor_by_id($database, $modalId);
        if ($doctor) {
            $documentPath = doc_sathi_doctor_verification_document_path($doctor);
            $documentGroups = doc_sathi_fetch_doctor_documents($database, (int)$doctor["docid"]);
            $documentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
            $hasStandaloneDocument = $documentPath !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $documentPath);
            $remarks = trim((string)(($doctor["admin_remarks"] ?? "") ?: ($doctor["rejection_reason"] ?? "")));
            $reviewedAt = ($doctor["verification_reviewed_at"] ?? "") ?: ($doctor["verified_at"] ?? "");
            $gender = doc_sathi_gender_label($doctor["gender"] ?? "");
            $gender = $gender === "" ? "Not specified" : $gender;
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <center>
                        <h2>Verification Details</h2>
                        <a class="close" href="doctor-verifications.php?status=' . h($statusFilter) . '">&times;</a>
                        <div style="display:flex;justify-content:center;">
                            <table width="85%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr><td class="label-td" colspan="2"><label class="form-label">Doctor:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h($doctor["docname"]) . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Email:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h($doctor["docemail"]) . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">License / Registration No.:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h(($doctor["license_number"] ?? "") !== "" ? $doctor["license_number"] : "Not submitted") . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Gender:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h($gender) . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Clinic / Hospital:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h(($doctor["clinic_name"] ?? "") !== "" ? $doctor["clinic_name"] : "Not provided") . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Address:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h($doctor["docaddress"] ?? "") . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Specialty:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h($doctor["specialty_name"] ?? "Not selected") . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Status:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h(doc_sathi_doctor_status_label($doctor["verification_status"] ?? "pending")) . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Submitted At:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h(display_date($doctor["verification_submitted_at"] ?? "")) . '<br><br></td></tr>
                                <tr><td class="label-td" colspan="2"><label class="form-label">Reviewed At:</label></td></tr>
                                <tr><td class="label-td" colspan="2">' . h(display_date($reviewedAt)) . '<br><br></td></tr>';

            if ($remarks !== "") {
                echo '<tr><td class="label-td" colspan="2"><label class="form-label">Admin Remarks:</label></td></tr>
                      <tr><td class="label-td" colspan="2">' . h($remarks) . '<br><br></td></tr>';
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
                    echo '<a href="../' . h($documentPath) . '" target="_blank" rel="noopener">Open document</a>';
                    echo '</div>';
                }
                echo '</div><br>';
            } else {
                echo 'Not uploaded<br><br>';
            }

            echo '</td></tr>
                                 <tr><td colspan="2"><a href="doctor-verifications.php?status=' . h($statusFilter) . '"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a></td></tr>
                             </table>
                         </div>
                    </center>
                </div>
            </div>';
        }
    } elseif ($action === "reject" && $modalId > 0) {
        $doctor = doc_sathi_get_doctor_by_id($database, $modalId);
        if ($doctor) {
            echo '
            <div id="popup1" class="overlay">
                <div class="popup admin-reject-dialog">
                    <a class="close" href="doctor-verifications.php?status=' . h($statusFilter) . '">&times;</a>
                    <div class="admin-reject-header">
                        <span class="admin-eyebrow">Review Action</span>
                        <h2>Reject Verification</h2>
                        <p>Share a clear reason so the doctor knows what must be corrected before the next review.</p>
                    </div>
                    <div class="admin-reject-subject">
                        <span class="admin-reject-subject-label">Doctor</span>
                        <strong class="admin-reject-subject-name">' . h($doctor["docname"]) . '</strong>
                        <span class="admin-reject-subject-meta">' . h($doctor["docemail"]) . '</span>
                    </div>
                    <form action="reject-doctor.php" method="post" class="admin-reject-form">
                        <input type="hidden" name="docid" value="' . (int)$doctor["docid"] . '">
                        <input type="hidden" name="source" value="verifications">
                        <label class="admin-reject-label" for="rejection_reason_' . (int)$doctor["docid"] . '">Reason for rejection</label>
                        <p class="admin-reject-helper">This message is shown to the doctor in their verification status and dashboard.</p>
                        <textarea class="reject-box" id="rejection_reason_' . (int)$doctor["docid"] . '" name="rejection_reason" maxlength="1000" required placeholder="Describe the missing evidence, mismatch, or correction needed"></textarea>
                        <div class="admin-reject-actions">
                            <a href="doctor-verifications.php?status=' . h($statusFilter) . '" class="admin-link-button">Cancel</a>
                            <button type="submit" class="admin-btn admin-danger-button">Reject Verification</button>
                        </div>
                    </form>
                </div>
            </div>';
        }
    }
}
?>
</body>
</html>
