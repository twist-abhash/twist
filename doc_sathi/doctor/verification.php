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
    <link rel="stylesheet" href="../css/doctor-dashboard.css">
    <link rel="stylesheet" href="../css/verification.css">
    <title>Doctor Verification</title>
</head>
<body>
<?php
require_once __DIR__ . "/../session_config.php";
require_once __DIR__ . "/../algorithms.php";
require_once __DIR__ . "/dashboard_helpers.php";
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] === "" || $_SESSION["usertype"] !== 'd') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

date_default_timezone_set('Asia/Kathmandu');

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_date($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return 'Not available';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'Not available';
    }

    return date('M d, Y h:i A', $timestamp);
}

$useremail = $_SESSION["user"];
$userfetch = doc_sathi_get_doctor_by_email($database, $useremail);

if (!$userfetch) {
    header("location: ../login.php");
    exit();
}

$userid = (int)$userfetch["docid"];
$username = $userfetch["docname"];
$message = "";
$messageType = "success";

$specialties = [];
$specialtyLookup = [];
$specialtiesResult = $database->query("SELECT id, sname FROM specialties ORDER BY sname ASC");
while ($row = $specialtiesResult->fetch_assoc()) {
    $specialties[] = $row;
    $specialtyLookup[(int)$row["id"]] = $row["sname"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["docname"] ?? "");
    $gender = doc_sathi_normalize_gender($_POST["gender"] ?? "");
    $licenseNumber = trim($_POST["license_number"] ?? "");
    $specialty = (int)($_POST["specialties"] ?? 0);
    $clinicName = trim($_POST["clinic_name"] ?? "");
    $address = trim($_POST["docaddress"] ?? "");
    $existingDocument = doc_sathi_doctor_verification_document_path($userfetch);
    $existingDocumentGroups = doc_sathi_fetch_doctor_documents($database, $userid);
    $hasExistingDocuments = $existingDocument !== "" || doc_sathi_count_grouped_doctor_documents($existingDocumentGroups) > 0;
    $uploadedDocumentPaths = [];

    if (($userfetch["verification_status"] ?? "pending") === "approved") {
        $message = "Approved accounts cannot resubmit verification details.";
        $messageType = "error";
    } elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $name)) {
        $message = "Doctor full name must contain valid name characters only.";
        $messageType = "error";
    } elseif (!doc_sathi_gender_is_valid($gender)) {
        $message = doc_sathi_gender_required_message();
        $messageType = "error";
    } elseif ($licenseNumber === "" || strlen($licenseNumber) > 100) {
        $message = "Please enter a valid license or registration number.";
        $messageType = "error";
    } elseif (doc_sathi_doctor_license_exists($database, $licenseNumber, $userid)) {
        $message = "This license number is already used by another doctor account.";
        $messageType = "error";
    } elseif ($specialty <= 0) {
        $message = "Please select a specialty.";
        $messageType = "error";
    } elseif ($clinicName === "" || strlen($clinicName) > 255) {
        $message = "Please enter your clinic or hospital name.";
        $messageType = "error";
    } elseif ($address === "") {
        $message = "Please enter your professional address.";
        $messageType = "error";
    } else {
        $specialtyStmt = doc_sathi_prepare($database, "SELECT id FROM specialties WHERE id = ? LIMIT 1");
        $specialtyStmt->bind_param("i", $specialty);
        doc_sathi_execute($specialtyStmt);

        if ($specialtyStmt->get_result()->num_rows === 0) {
            $message = "Please select a valid specialty.";
            $messageType = "error";
        } else {
            $hasUpload = doc_sathi_has_categorized_doctor_document_upload($_FILES["verification_documents"] ?? null);

            if (!$hasUpload && !$hasExistingDocuments) {
                $message = "Please upload at least one verification document.";
                $messageType = "error";
            }

            if ($message === "") {
                try {
                    $database->begin_transaction();

                    $uploadResult = doc_sathi_upload_categorized_doctor_documents(
                        $database,
                        $userid,
                        $_FILES["verification_documents"] ?? [],
                        dirname(__DIR__)
                    );

                    if (!$uploadResult["ok"]) {
                        throw new RuntimeException($uploadResult["message"] ?? "Could not upload documents.");
                    }

                    $uploadedDocumentPaths = $uploadResult["uploaded_paths"] ?? [];
                    $documentPath = $existingDocument;
                    if ($documentPath === "" && !empty($uploadResult["documents"])) {
                        $documentPath = $uploadResult["documents"][0]["file_path"];
                    }

                    $stmt = doc_sathi_prepare(
                        $database,
                        "UPDATE doctor
                         SET docname = ?,
                             gender = ?,
                             license_number = NULLIF(?, ''),
                             clinic_name = ?,
                             docaddress = ?,
                             specialties = ?,
                             verification_document = ?,
                             certification_file = ?,
                             verification_status = 'pending',
                             verification_submitted_at = NOW(),
                             verification_reviewed_at = NULL,
                             verified_at = NULL,
                             verified_by = NULL,
                             admin_remarks = NULL,
                             rejection_reason = NULL
                         WHERE docid = ?"
                    );
                    $stmt->bind_param("sssssissi", $name, $gender, $licenseNumber, $clinicName, $address, $specialty, $documentPath, $documentPath, $userid);
                    doc_sathi_execute($stmt);

                    $database->commit();

                    $message = "Verification details submitted for admin review.";
                    $messageType = "success";
                    $userfetch = doc_sathi_get_doctor_by_email($database, $useremail);
                    $username = $userfetch["docname"];
                } catch (Throwable $exception) {
                    $database->rollback();

                    doc_sathi_delete_uploaded_document_paths($uploadedDocumentPaths, dirname(__DIR__));

                    $message = $exception->getMessage() !== ""
                        ? $exception->getMessage()
                        : "Could not submit verification details. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}

$verificationStatus = $userfetch["verification_status"] ?? "pending";
$statusLabel = doc_sathi_doctor_status_label($verificationStatus);
$statusMessage = doc_sathi_doctor_status_message($userfetch);
$documentPath = doc_sathi_doctor_verification_document_path($userfetch);
$documentGroups = doc_sathi_fetch_doctor_documents($database, $userid);
$categorizedDocumentCount = doc_sathi_count_grouped_doctor_documents($documentGroups);
$hasStandaloneDocument = $documentPath !== "" && !doc_sathi_document_path_is_grouped($documentGroups, $documentPath);
$totalDocumentCount = $categorizedDocumentCount + ($hasStandaloneDocument ? 1 : 0);
$licenseNumber = $userfetch["license_number"] ?? "";
$clinicName = $userfetch["clinic_name"] ?? "";
$genderValue = doc_sathi_normalize_gender($userfetch["gender"] ?? "");
$genderLabel = doc_sathi_gender_label($genderValue);
$genderLabel = $genderLabel === "" ? "Not specified" : $genderLabel;
$address = $userfetch["docaddress"] ?? "";
$specialtyId = (int)($userfetch["specialties"] ?? 0);
$specialtyName = $specialtyLookup[$specialtyId] ?? "Not selected";
$submittedAt = $userfetch["verification_submitted_at"] ?? "";
$reviewedAt = ($userfetch["verification_reviewed_at"] ?? "") ?: ($userfetch["verified_at"] ?? "");
$adminRemarks = trim((string)(($userfetch["admin_remarks"] ?? "") ?: ($userfetch["rejection_reason"] ?? "")));
$canSubmit = $verificationStatus !== "approved";

$heroTitle = "Verification under review";
if ($verificationStatus === "approved") {
    $heroTitle = "Verification approved";
} elseif ($verificationStatus === "rejected") {
    $heroTitle = "Verification requires updates";
}

$statusCardTitle = "Submit Verification Details";
$statusCardCopy = "Upload verification documents by category for admin review.";
$statusTone = "warning";

if ($verificationStatus === "approved") {
    $statusCardTitle = "Verification Approved";
    $statusCardCopy = "Your account has been approved. You can create sessions and receive patient bookings.";
    $statusTone = "success";
} elseif ($verificationStatus === "rejected") {
    $statusCardTitle = "Resubmit Verification";
    $statusCardCopy = "Update your details and upload a current supporting document so the admin team can review your credentials again.";
    $statusTone = "danger";
}
?>

<div class="container doctor-dashboard-layout">
    <?php doctor_dashboard_sidebar('verification', $username, $useremail); ?>

    <div class="dash-body doctor-dashboard-body">
        <main class="doctor-page-shell">
            <header class="doctor-page-header">
                <div class="doctor-page-header-main">
                    <a href="index.php" class="doctor-back-link">Back</a>
                    <div class="doctor-page-title-block">
                        <h1>Doctor Verification</h1>
                        <p>Review your approval status, professional details, and supporting documents from one place.</p>
                    </div>
                </div>
                <aside class="doctor-date-card">
                    <span>Today's Date</span>
                    <strong><?php echo h(date('M d, Y')); ?></strong>
                </aside>
            </header>

            <?php if ($message !== ""): ?>
                <div class="doctor-alert <?php echo h($messageType); ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>

            <section class="verify-status-banner verify-status-<?php echo h($verificationStatus); ?>">
                <div class="verify-banner-left">
                    <div class="verify-banner-label">Credential Review</div>
                    <div class="verify-banner-headline">
                        <h2><?php echo h($heroTitle); ?></h2>
                        <span class="doctor-badge <?php echo h($verificationStatus); ?>"><?php echo h($statusLabel); ?></span>
                    </div>
                    <p class="verify-banner-description"><?php echo h($statusMessage); ?></p>
                </div>

                <div class="verify-banner-right">
                    <div class="verify-banner-stat">
                        <span class="verify-stat-label">Submitted At</span>
                        <strong class="verify-stat-value"><?php echo h(display_date($submittedAt)); ?></strong>
                    </div>
                    <div class="verify-banner-stat">
                        <span class="verify-stat-label">Reviewed At</span>
                        <strong class="verify-stat-value"><?php echo h(display_date($reviewedAt)); ?></strong>
                    </div>
                    <div class="verify-banner-stat">
                        <span class="verify-stat-label">License / Registration</span>
                        <strong class="verify-stat-value"><?php echo h($licenseNumber !== "" ? $licenseNumber : "Not submitted"); ?></strong>
                    </div>
                </div>
            </section>

            <section class="verify-content-grid">
                <article class="doctor-page-card verify-summary-card">
                    <div class="doctor-card-header">
                        <div>
                            <h2>Verification Summary</h2>
                            <p>Your current verification profile and credentials on file.</p>
                        </div>
                    </div>

                    <div class="verify-details-list">
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Approval Status</span>
                            <strong class="verify-detail-value"><?php echo h($statusLabel); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Submitted At</span>
                            <strong class="verify-detail-value"><?php echo h(display_date($submittedAt)); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Reviewed At</span>
                            <strong class="verify-detail-value"><?php echo h(display_date($reviewedAt)); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">License / Registration No.</span>
                            <strong class="verify-detail-value"><?php echo h($licenseNumber !== "" ? $licenseNumber : "Not submitted"); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Gender</span>
                            <strong class="verify-detail-value"><?php echo h($genderLabel); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Specialty</span>
                            <strong class="verify-detail-value"><?php echo h($specialtyName); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Clinic / Hospital</span>
                            <strong class="verify-detail-value"><?php echo h($clinicName !== "" ? $clinicName : "Not provided"); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Professional Address</span>
                            <strong class="verify-detail-value"><?php echo h($address !== "" ? $address : "Not provided"); ?></strong>
                        </div>
                        <div class="verify-detail-item">
                            <span class="verify-detail-label">Documents on File</span>
                            <strong class="verify-detail-value"><?php echo (int)$totalDocumentCount; ?> uploaded file<?php echo $totalDocumentCount === 1 ? "" : "s"; ?></strong>
                        </div>
                        <?php if ($adminRemarks !== ""): ?>
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Admin Remarks</span>
                                <strong class="verify-detail-value"><?php echo h($adminRemarks); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalDocumentCount > 0): ?>
                        <div class="verify-document-groups compact">
                            <?php if ($categorizedDocumentCount > 0): ?>
                                <?php foreach ($documentGroups as $group): ?>
                                    <div class="verify-document-group">
                                        <strong><?php echo h($group["label"]); ?></strong>
                                        <?php if (count($group["documents"]) === 0): ?>
                                            <span>No files uploaded</span>
                                        <?php else: ?>
                                            <?php foreach ($group["documents"] as $document): ?>
                                                <a href="../<?php echo h($document["file_path"]); ?>" target="_blank" rel="noopener"><?php echo h($document["original_name"]); ?></a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if ($hasStandaloneDocument): ?>
                                <div class="verify-document-group">
                                    <strong>Documents</strong>
                                    <a href="../<?php echo h($documentPath); ?>" target="_blank" rel="noopener">Open existing document</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="verify-card-footer">
                            <?php if ($hasStandaloneDocument): ?>
                                <a href="../<?php echo h($documentPath); ?>" target="_blank" rel="noopener" class="doctor-btn secondary">View Document</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="doctor-page-card verify-action-card">
                    <div class="doctor-card-header">
                        <div>
                            <h2><?php echo h($statusCardTitle); ?></h2>
                            <p><?php echo h($statusCardCopy); ?></p>
                        </div>
                    </div>

                    <?php if ($adminRemarks !== "" && $verificationStatus !== "approved"): ?>
                        <div class="doctor-alert <?php echo $verificationStatus === "rejected" ? "error" : "warning"; ?> verify-card-alert">
                            <strong>Admin Remarks:</strong> <?php echo h($adminRemarks); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$canSubmit): ?>
                        <div class="verify-approved-details">
                            <div class="verify-approved-item">
                                <span class="verify-detail-label">Approval Status</span>
                                <strong class="verify-detail-value"><?php echo h($statusLabel); ?></strong>
                            </div>
                            <div class="verify-approved-item">
                                <span class="verify-detail-label">Reviewed At</span>
                                <strong class="verify-detail-value"><?php echo h(display_date($reviewedAt)); ?></strong>
                            </div>
                            <div class="verify-approved-item">
                                <span class="verify-detail-label">Gender</span>
                                <strong class="verify-detail-value"><?php echo h($genderLabel); ?></strong>
                            </div>
                            <div class="verify-approved-item">
                                <span class="verify-detail-label">Clinic / Hospital</span>
                                <strong class="verify-detail-value"><?php echo h($clinicName !== "" ? $clinicName : "Not provided"); ?></strong>
                            </div>
                            <div class="verify-approved-item">
                                <span class="verify-detail-label">Document on File</span>
                                <strong class="verify-detail-value"><?php echo h($totalDocumentCount > 0 ? "Available" : "Not uploaded"); ?></strong>
                            </div>
                        </div>

                        <?php if ($totalDocumentCount > 0): ?>
                            <div class="verify-document-groups compact">
                                <?php if ($categorizedDocumentCount > 0): ?>
                                    <?php foreach ($documentGroups as $group): ?>
                                        <div class="verify-document-group">
                                            <strong><?php echo h($group["label"]); ?></strong>
                                            <?php if (count($group["documents"]) === 0): ?>
                                                <span>No files uploaded</span>
                                            <?php else: ?>
                                                <?php foreach ($group["documents"] as $document): ?>
                                                    <a href="../<?php echo h($document["file_path"]); ?>" target="_blank" rel="noopener"><?php echo h($document["original_name"]); ?></a>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($hasStandaloneDocument): ?>
                                    <div class="verify-document-group">
                                        <strong>Documents</strong>
                                        <a href="../<?php echo h($documentPath); ?>" target="_blank" rel="noopener">Open existing document</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="verify-card-footer">
                                <?php if ($hasStandaloneDocument): ?>
                                    <a href="../<?php echo h($documentPath); ?>" target="_blank" rel="noopener" class="doctor-btn">View Document</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <form action="" method="POST" enctype="multipart/form-data" class="doctor-form-grid verify-form">
                            <div class="doctor-field full">
                                <label for="docname">Doctor Full Name</label>
                                <input type="text" name="docname" id="docname" value="<?php echo h($username); ?>" class="doctor-input" required>
                            </div>

                            <div class="doctor-field">
                                <label for="gender">Gender</label>
                                <select name="gender" id="gender" class="doctor-select" required>
                                    <option value="" disabled <?php echo $genderValue === "" ? "selected" : ""; ?>>Select Gender</option>
                                    <?php foreach (doc_sathi_valid_genders() as $genderOption): ?>
                                        <option value="<?php echo h($genderOption); ?>" <?php echo $genderValue === $genderOption ? "selected" : ""; ?>>
                                            <?php echo h(doc_sathi_gender_label($genderOption)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="doctor-field">
                                <label for="license_number">License / Registration Number</label>
                                <input type="text" name="license_number" id="license_number" value="<?php echo h($licenseNumber); ?>" class="doctor-input" maxlength="100" required>
                            </div>

                            <div class="doctor-field">
                                <label for="specialties">Specialty</label>
                                <select name="specialties" id="specialties" class="doctor-select" required>
                                    <option value="" disabled <?php echo $specialtyId <= 0 ? "selected" : ""; ?>>Select Specialty</option>
                                    <?php foreach ($specialties as $specialty): ?>
                                        <option value="<?php echo (int)$specialty["id"]; ?>" <?php echo $specialtyId === (int)$specialty["id"] ? "selected" : ""; ?>>
                                            <?php echo h($specialty["sname"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="doctor-field">
                                <label for="clinic_name">Clinic / Hospital Name</label>
                                <input type="text" name="clinic_name" id="clinic_name" value="<?php echo h($clinicName); ?>" class="doctor-input" maxlength="255" required>
                            </div>

                            <div class="doctor-field">
                                <label for="docaddress">Address</label>
                                <input type="text" name="docaddress" id="docaddress" value="<?php echo h($address); ?>" class="doctor-input" required>
                            </div>

                            <div class="doctor-field full">
                                <label>Verification Documents</label>
                                <div class="verify-upload-card verify-document-section">
                                    <div>
                                        <strong class="verify-upload-title">Document Uploads</strong>
                                        <p class="doctor-helper-text verify-upload-hint">Upload files under the correct category. Each category accepts multiple PDF, JPG, JPEG, PNG, or WEBP files up to 8 MB each. Existing files are preserved when you add more.</p>
                                    </div>

                                    <div class="verify-document-upload-grid">
                                        <?php foreach (doc_sathi_doctor_document_categories() as $category => $label): ?>
                                            <div class="verify-document-upload-group">
                                                <label for="verification_documents_<?php echo h($category); ?>"><?php echo h($label); ?></label>
                                                <input
                                                    type="file"
                                                    name="verification_documents[<?php echo h($category); ?>][]"
                                                    id="verification_documents_<?php echo h($category); ?>"
                                                    class="doctor-input verify-file-input"
                                                    accept=".pdf,.png,.jpg,.jpeg,.webp"
                                                    multiple
                                                >
                                                <div class="verify-selected-files" data-selected-for="verification_documents_<?php echo h($category); ?>"></div>
                                                <p class="doctor-helper-text">You can select multiple files.</p>
                                                <?php if (!empty($documentGroups[$category]["documents"])): ?>
                                                    <div class="verify-document-list">
                                                        <?php foreach ($documentGroups[$category]["documents"] as $document): ?>
                                                            <a href="../<?php echo h($document["file_path"]); ?>" target="_blank" rel="noopener"><?php echo h($document["original_name"]); ?></a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if ($hasStandaloneDocument): ?>
                                        <div class="verify-document-existing">
                                            <strong>Documents on file</strong>
                                            <a href="../<?php echo h($documentPath); ?>" target="_blank" rel="noopener" class="verify-doc-link">Open existing document</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="verify-form-actions full">
                                <button type="submit" class="doctor-btn"><?php echo $verificationStatus === "rejected" ? "Resubmit for Review" : "Submit for Review"; ?></button>
                                <?php if ($hasStandaloneDocument): ?>
                                    <a href="../<?php echo h($documentPath); ?>" target="_blank" rel="noopener" class="doctor-btn secondary">Open Document</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </article>
            </section>
        </main>
    </div>
</div>
<script>
    document.querySelectorAll('.verify-document-upload-group input[type="file"]').forEach(function(input) {
        input.addEventListener('change', function() {
            const target = document.querySelector('[data-selected-for="' + input.id + '"]');
            if (!target) {
                return;
            }

            const names = Array.prototype.map.call(input.files || [], function(file) {
                return file.name;
            });

            target.textContent = names.length ? names.join(', ') : '';
        });
    });
</script>
</body>
</html>
