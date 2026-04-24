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
    <link rel="stylesheet" href="../css/doctor-account.css?v=<?php echo filemtime(__DIR__ . '/../css/doctor-account.css'); ?>">
    <title>Doctor Account</title>
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

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function display_account_date($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Not available';
    }

    return date('M d, Y h:i A', strtotime($value));
}

function account_password_is_valid($password)
{
    return is_string($password)
        && strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

function account_password_matches($password, $storedPassword)
{
    $storedPassword = (string)$storedPassword;
    $matches = password_verify($password, $storedPassword);

    if (!$matches && password_get_info($storedPassword)['algo'] === 0) {
        $matches = hash_equals($storedPassword, $password);
    }

    return $matches;
}

function upload_profile_photo($file, $baseDir)
{
    if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Could not upload the profile photo.'];
    }

    $maxBytes = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'Profile photo must be 2 MB or smaller.'];
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        return ['ok' => false, 'message' => 'Profile photo must be a JPG, JPEG, PNG, or WEBP file.'];
    }

    $allowedMimeTypes = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mimeType, $allowedMimeTypes[$extension], true)) {
                return ['ok' => false, 'message' => 'Profile photo type does not match its extension.'];
            }
        }
    }

    $uploadDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile_photos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['ok' => false, 'message' => 'Could not create profile photo directory.'];
    }

    $filename = 'doctor_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'message' => 'Could not save profile photo.'];
    }

    return ['ok' => true, 'path' => 'uploads/profile_photos/' . $filename];
}

date_default_timezone_set('Asia/Kathmandu');

$useremail = $_SESSION["user"];
$doctor = doc_sathi_get_doctor_by_email($database, $useremail);
if (!$doctor) {
    header("location: ../login.php");
    exit();
}

$message = "";
$messageType = "success";
$userid = (int)$doctor["docid"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "";

    if ($formAction === "profile") {
        $name = trim($_POST["docname"] ?? "");
        $phone = trim($_POST["doctel"] ?? "");
        $gender = doc_sathi_normalize_gender($_POST["gender"] ?? "");
        $specialty = (int)($_POST["specialties"] ?? 0);
        $qualification = trim($_POST["qualification"] ?? "");
        $licenseNumber = trim($_POST["license_number"] ?? "");
        $experienceYears = trim($_POST["experience_years"] ?? "");
        $clinicName = trim($_POST["clinic_name"] ?? "");
        $website = trim($_POST["website"] ?? "");
        $address = trim($_POST["docaddress"] ?? "");
        $bio = trim($_POST["bio"] ?? "");
        $experienceValue = $experienceYears === "" ? null : (int)$experienceYears;

        if (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $name)) {
            $message = "Full name must contain valid name characters only.";
            $messageType = "error";
        } elseif (!doc_sathi_phone_is_valid($phone)) {
            $message = doc_sathi_phone_policy_message();
            $messageType = "error";
        } elseif (doc_sathi_doctor_phone_exists($database, $phone, $userid)) {
            $message = "This mobile number is already used by another doctor account.";
            $messageType = "error";
        } elseif (!doc_sathi_gender_is_valid($gender)) {
            $message = doc_sathi_gender_required_message();
            $messageType = "error";
        } elseif ($specialty <= 0) {
            $message = "Please select a specialization.";
            $messageType = "error";
        } elseif ($qualification !== "" && strlen($qualification) > 255) {
            $message = "Qualification must be 255 characters or fewer.";
            $messageType = "error";
        } elseif ($licenseNumber !== "" && strlen($licenseNumber) > 100) {
            $message = "License number must be 100 characters or fewer.";
            $messageType = "error";
        } elseif (doc_sathi_doctor_license_exists($database, $licenseNumber, $userid)) {
            $message = "This license number is already used by another doctor account.";
            $messageType = "error";
        } elseif ($experienceValue !== null && ($experienceValue < 0 || $experienceValue > 70)) {
            $message = "Experience must be between 0 and 70 years.";
            $messageType = "error";
        } elseif ($clinicName === "" || strlen($clinicName) > 255) {
            $message = "Please enter your clinic or hospital name.";
            $messageType = "error";
        } elseif ($website !== "" && !filter_var($website, FILTER_VALIDATE_URL)) {
            $message = "Website must be a valid URL, including https:// if applicable.";
            $messageType = "error";
        } elseif ($address === "") {
            $message = "Please enter your location or address.";
            $messageType = "error";
        } elseif (strlen($bio) > 1200) {
            $message = "Bio must be 1200 characters or fewer.";
            $messageType = "error";
        } else {
            $specialtyStmt = doc_sathi_prepare($database, "SELECT id FROM specialties WHERE id = ? LIMIT 1");
            $specialtyStmt->bind_param("i", $specialty);
            doc_sathi_execute($specialtyStmt);

            if ($specialtyStmt->get_result()->num_rows === 0) {
                $message = "Please select a valid specialization.";
                $messageType = "error";
            } else {
                $uploadResult = upload_profile_photo($_FILES["profile_photo"] ?? null, dirname(__DIR__));
                if (!$uploadResult["ok"]) {
                    $message = $uploadResult["message"];
                    $messageType = "error";
                } else {
                    $newPhoto = $uploadResult["path"] !== "" ? $uploadResult["path"] : ($doctor["profile_photo"] ?? "");
                    $oldPhoto = trim((string)($doctor["profile_photo"] ?? ""));

                    try {
                        $stmt = doc_sathi_prepare(
                            $database,
                            "UPDATE doctor
                             SET docname = ?,
                                 doctel = ?,
                                 gender = ?,
                                 specialties = ?,
                                 qualification = ?,
                                 license_number = NULLIF(?, ''),
                                 experience_years = ?,
                                 clinic_name = ?,
                                 website = ?,
                                 docaddress = ?,
                                 profile_photo = ?,
                                 bio = ?
                             WHERE docid = ?"
                        );
                        $stmt->bind_param(
                            "sssississsssi",
                            $name,
                            $phone,
                            $gender,
                            $specialty,
                            $qualification,
                            $licenseNumber,
                            $experienceValue,
                            $clinicName,
                            $website,
                            $address,
                            $newPhoto,
                            $bio,
                            $userid
                        );
                        doc_sathi_execute($stmt);

                        if ($uploadResult["path"] !== "" && $oldPhoto !== "" && strpos($oldPhoto, "uploads/profile_photos/") === 0 && $oldPhoto !== $newPhoto) {
                            $oldAbsolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPhoto);
                            if (is_file($oldAbsolutePath)) {
                                unlink($oldAbsolutePath);
                            }
                        }

                        $message = "Profile updated successfully.";
                        $messageType = "success";
                        $doctor = doc_sathi_get_doctor_by_email($database, $useremail);
                    } catch (Throwable $exception) {
                        if ($uploadResult["path"] !== "") {
                            $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadResult["path"]);
                            if (is_file($absolutePath)) {
                                unlink($absolutePath);
                            }
                        }

                        $message = "Could not update profile. Please try again.";
                        $messageType = "error";
                    }
                }
            }
        }
    } elseif ($formAction === "password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (!account_password_matches($currentPassword, $doctor["docpassword"] ?? "")) {
            $message = "Current password is incorrect.";
            $messageType = "error";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New password and confirmation do not match.";
            $messageType = "error";
        } elseif (!account_password_is_valid($newPassword)) {
            $message = "New password must be at least 8 characters and include at least one letter and one number.";
            $messageType = "error";
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = doc_sathi_prepare($database, "UPDATE doctor SET docpassword = ? WHERE docid = ?");
            $stmt->bind_param("si", $newHash, $userid);
            doc_sathi_execute($stmt);
            $message = "Password updated successfully.";
            $messageType = "success";
            $doctor = doc_sathi_get_doctor_by_email($database, $useremail);
        }
    } elseif ($formAction === "delete") {
        $deletePassword = $_POST["delete_password"] ?? "";
        $confirmDelete = $_POST["confirm_delete"] ?? "";
        $confirmChecked = isset($_POST["delete_confirm_check"]);

        if ($confirmDelete !== "1" || !$confirmChecked) {
            $message = "Please confirm account deletion before continuing.";
            $messageType = "error";
        } elseif (!account_password_matches($deletePassword, $doctor["docpassword"] ?? "")) {
            $message = "Current password is incorrect. Account was not deleted.";
            $messageType = "error";
        } else {
            $profilePhoto = trim((string)($doctor["profile_photo"] ?? ""));

            try {
                $database->begin_transaction();

                $deleteAppointments = doc_sathi_prepare(
                    $database,
                    "DELETE a FROM appointment a
                     INNER JOIN schedule s ON s.scheduleid = a.scheduleid
                     WHERE s.docid = ?"
                );
                $deleteAppointments->bind_param("i", $userid);
                doc_sathi_execute($deleteAppointments);

                $deleteSchedules = doc_sathi_prepare($database, "DELETE FROM schedule WHERE docid = ?");
                $deleteSchedules->bind_param("i", $userid);
                doc_sathi_execute($deleteSchedules);

                $deleteWebuser = doc_sathi_prepare($database, "DELETE FROM webuser WHERE email = ?");
                $deleteWebuser->bind_param("s", $useremail);
                doc_sathi_execute($deleteWebuser);

                $deleteDoctor = doc_sathi_prepare($database, "DELETE FROM doctor WHERE docid = ?");
                $deleteDoctor->bind_param("i", $userid);
                doc_sathi_execute($deleteDoctor);

                $database->commit();

                if ($profilePhoto !== "" && strpos($profilePhoto, "uploads/profile_photos/") === 0) {
                    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profilePhoto);
                    if (is_file($absolutePath)) {
                        unlink($absolutePath);
                    }
                }

                header("location: ../logout.php?role=d");
                exit();
            } catch (Throwable $exception) {
                $database->rollback();
                $message = "Could not delete account. Please try again.";
                $messageType = "error";
            }
        }
    }
}

$userid = (int)$doctor["docid"];
$username = $doctor["docname"];
$verificationStatus = $doctor["verification_status"] ?? "pending";
$verificationLabel = doc_sathi_doctor_status_label($verificationStatus);
$verificationMessage = doc_sathi_doctor_status_message($doctor);
$accountState = $verificationStatus === "approved" ? "Active" : "Limited";
$approvalStatus = $verificationStatus === "approved" ? "Verified" : ($verificationStatus === "rejected" ? "Rejected" : "Under Review");
$reviewedAt = ($doctor["verification_reviewed_at"] ?? "") ?: ($doctor["verified_at"] ?? "");
$profilePhoto = trim((string)($doctor["profile_photo"] ?? ""));
$profilePhotoSrc = $profilePhoto !== "" ? "../" . $profilePhoto : "../img/user.png";

$specialties = [];
$specialtiesResult = $database->query("SELECT id, sname FROM specialties ORDER BY sname ASC");
while ($row = $specialtiesResult->fetch_assoc()) {
    $specialties[] = $row;
}

$currentSpecialty = trim((string)($doctor["specialty_name"] ?? ""));
if ($currentSpecialty === "") {
    foreach ($specialties as $specialty) {
        if ((int)($doctor["specialties"] ?? 0) === (int)$specialty["id"]) {
            $currentSpecialty = $specialty["sname"];
            break;
        }
    }
}
if ($currentSpecialty === "") {
    $currentSpecialty = "Not selected";
}

$experienceDisplay = trim((string)($doctor["experience_years"] ?? ""));
$experienceDisplay = $experienceDisplay === "" ? "Not added" : $experienceDisplay . " years";
$clinicDisplay = trim((string)($doctor["clinic_name"] ?? ""));
$clinicDisplay = $clinicDisplay === "" ? "Not added" : $clinicDisplay;
$genderValue = doc_sathi_normalize_gender($doctor["gender"] ?? "");
$genderDisplay = doc_sathi_gender_label($genderValue);
$genderDisplay = $genderDisplay === "" ? "Not specified" : $genderDisplay;
?>

<div class="container doctor-dashboard-layout">
    <?php doctor_dashboard_sidebar('account', $username, $useremail); ?>

    <div class="dash-body doctor-dashboard-body">
        <main class="doctor-page-shell">
            <header class="doctor-page-header">
                <div class="doctor-page-header-main">
                    <a href="index.php" class="doctor-back-link">Back</a>
                    <div class="doctor-page-title-block">
                        <h1>Account</h1>
                        <p>Manage your account details, password, and preferences.</p>
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

            <section class="verification-banner <?php echo h($verificationStatus); ?>">
                <div>
                    <span class="doctor-badge <?php echo h($verificationStatus); ?>"><?php echo h($verificationLabel); ?></span>
                    <h2>Verification Status</h2>
                    <p><?php echo h($verificationMessage); ?></p>
                </div>
                <a href="verification.php" class="doctor-action-button"><?php echo $verificationStatus === "rejected" ? "Resubmit Documents" : "Go to Verification"; ?></a>
            </section>

            <section class="doctor-account-overview" aria-labelledby="profile-overview-title">
                <article class="doctor-page-card doctor-profile-summary-card">
                    <div class="doctor-profile-summary-main">
                        <img src="<?php echo h($profilePhotoSrc); ?>" alt="Doctor profile photo" class="doctor-profile-summary-photo" width="112" height="112">
                        <div>
                            <span class="doctor-section-kicker">Profile</span>
                            <h2 id="profile-overview-title">Dr. <?php echo h($username); ?></h2>
                            <p><?php echo h($doctor["docemail"] ?? ""); ?></p>
                            <div class="doctor-profile-badges">
                                <span class="doctor-badge <?php echo h($verificationStatus); ?>"><?php echo h($verificationLabel); ?></span>
                                <span class="doctor-account-pill"><?php echo h($currentSpecialty); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="doctor-profile-summary-meta" aria-label="Profile summary">
                        <div>
                            <span>Doctor ID</span>
                            <strong>#<?php echo $userid; ?></strong>
                        </div>
                        <div>
                            <span>Gender</span>
                            <strong><?php echo h($genderDisplay); ?></strong>
                        </div>
                        <div>
                            <span>Experience</span>
                            <strong><?php echo h($experienceDisplay); ?></strong>
                        </div>
                        <div>
                            <span>Practice</span>
                            <strong><?php echo h($clinicDisplay); ?></strong>
                        </div>
                        <div>
                            <span>Account Status</span>
                            <strong><?php echo h($accountState); ?></strong>
                        </div>
                    </div>
                </article>
            </section>

            <section class="doctor-account-layout">
                <div class="doctor-account-stack">
                    <article class="doctor-page-card doctor-account-card" id="profile-details">
                        <div class="doctor-card-header">
                            <div>
                                <span class="doctor-section-kicker">Editable Details</span>
                                <h2>Account Details</h2>
                                <p>Keep your profile accurate so patients and admins can identify your practice.</p>
                            </div>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" class="doctor-account-form">
                            <input type="hidden" name="form_action" value="profile">

                            <section class="doctor-form-section">
                                <div class="doctor-form-section-heading">
                                    <h3>Profile Photo</h3>
                                    <p>Upload a clear photo that represents your doctor profile.</p>
                                </div>
                                <div class="doctor-field full">
                                    <label>Profile Photo</label>
                                    <div class="doctor-photo-uploader">
                                        <img src="<?php echo h($profilePhotoSrc); ?>" alt="Doctor profile photo" class="doctor-profile-preview" width="112" height="112">
                                        <div class="doctor-upload-control">
                                            <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" class="doctor-input doctor-file-input">
                                            <p class="doctor-helper-text">JPG, JPEG, PNG, or WEBP. Maximum size: 2 MB.</p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="doctor-form-section">
                                <div class="doctor-form-section-heading">
                                    <h3>Personal Information</h3>
                                    <p>These details identify your account and contact information.</p>
                                </div>
                                <div class="doctor-form-grid">
                                    <div class="doctor-field">
                                        <label for="docname">Full Name</label>
                                        <input type="text" name="docname" id="docname" value="<?php echo h($doctor["docname"] ?? ""); ?>" class="doctor-input" required>
                                    </div>
                                    <div class="doctor-field">
                                        <label for="docemail">Email Address</label>
                                        <input type="email" id="docemail" value="<?php echo h($doctor["docemail"] ?? ""); ?>" class="doctor-input" readonly>
                                        <p class="doctor-helper-text">Email is used for login and cannot be changed here.</p>
                                    </div>
                                    <div class="doctor-field">
                                        <label for="doctel">Mobile Number</label>
                                        <input type="tel" name="doctel" id="doctel" value="<?php echo h($doctor["doctel"] ?? ""); ?>" class="doctor-input" inputmode="numeric" pattern="(98|97)[0-9]{8}" minlength="10" maxlength="10" autocomplete="tel-national" required oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);this.setCustomValidity('');" oninvalid="this.setCustomValidity('Mobile number must start with 98 or 97 and contain exactly 10 digits.')">
                                    </div>
                                    <div class="doctor-field">
                                        <label for="gender">Gender</label>
                                        <select name="gender" id="gender" class="doctor-select" required>
                                            <option value="" disabled <?php echo $genderValue === "" ? "selected" : ""; ?>>Select gender</option>
                                            <?php foreach (doc_sathi_valid_genders() as $genderOption): ?>
                                                <option value="<?php echo h($genderOption); ?>" <?php echo $genderValue === $genderOption ? "selected" : ""; ?>>
                                                    <?php echo h(doc_sathi_gender_label($genderOption)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="doctor-field">
                                        <label for="docaddress">Location / Address</label>
                                        <input type="text" name="docaddress" id="docaddress" value="<?php echo h($doctor["docaddress"] ?? ""); ?>" class="doctor-input" required>
                                    </div>
                                </div>
                            </section>

                            <section class="doctor-form-section">
                                <div class="doctor-form-section-heading">
                                    <h3>Professional Information</h3>
                                    <p>Describe your specialization, credentials, and practice details.</p>
                                </div>
                                <div class="doctor-form-grid">
                                    <div class="doctor-field">
                                        <label for="specialties">Specialization</label>
                                        <select name="specialties" id="specialties" class="doctor-select" required>
                                            <option value="" disabled <?php echo (int)($doctor["specialties"] ?? 0) <= 0 ? "selected" : ""; ?>>Select specialization</option>
                                            <?php foreach ($specialties as $specialty): ?>
                                                <option value="<?php echo (int)$specialty["id"]; ?>" <?php echo (int)($doctor["specialties"] ?? 0) === (int)$specialty["id"] ? "selected" : ""; ?>>
                                                    <?php echo h($specialty["sname"]); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="doctor-field">
                                        <label for="qualification">Qualification / Degree</label>
                                        <input type="text" name="qualification" id="qualification" value="<?php echo h($doctor["qualification"] ?? ""); ?>" class="doctor-input" placeholder="MBBS, MD, MS">
                                    </div>
                                    <div class="doctor-field">
                                        <label for="license_number">NMC / Medical License Number</label>
                                        <input type="text" name="license_number" id="license_number" value="<?php echo h($doctor["license_number"] ?? ""); ?>" class="doctor-input">
                                    </div>
                                    <div class="doctor-field">
                                        <label for="experience_years">Experience (Years)</label>
                                        <input type="number" name="experience_years" id="experience_years" min="0" max="70" value="<?php echo h($doctor["experience_years"] ?? ""); ?>" class="doctor-input" placeholder="Years">
                                    </div>
                                    <div class="doctor-field">
                                        <label for="clinic_name">Clinic / Hospital Name</label>
                                        <input type="text" name="clinic_name" id="clinic_name" value="<?php echo h($doctor["clinic_name"] ?? ""); ?>" class="doctor-input" maxlength="255" required>
                                    </div>
                                    <div class="doctor-field">
                                        <label for="website">Website</label>
                                        <input type="url" name="website" id="website" value="<?php echo h($doctor["website"] ?? ""); ?>" class="doctor-input" placeholder="https://example.com">
                                    </div>
                                    <div class="doctor-field full">
                                        <label for="bio">Professional Bio</label>
                                        <textarea name="bio" id="bio" class="doctor-textarea" placeholder="Write about your experience, approach, and specialties..."><?php echo h($doctor["bio"] ?? ""); ?></textarea>
                                    </div>
                                </div>
                            </section>

                            <div class="doctor-form-actions">
                                <button type="submit" class="doctor-btn">Save Profile</button>
                                <a href="index.php" class="doctor-btn secondary">Cancel</a>
                            </div>
                        </form>
                    </article>

                    <article class="doctor-page-card doctor-account-card" id="password">
                        <div class="doctor-card-header">
                            <div>
                                <span class="doctor-section-kicker">Security</span>
                                <h2>Change Password</h2>
                                <p>Use a secure password with at least 8 characters, one letter, and one number.</p>
                            </div>
                        </div>
                        <form action="" method="POST" class="doctor-account-form">
                            <input type="hidden" name="form_action" value="password">
                            <div class="doctor-form-grid">
                                <div class="doctor-field">
                                    <label for="current_password">Current Password</label>
                                    <div class="doctor-password-control">
                                        <input type="password" name="current_password" id="current_password" class="doctor-input" required>
                                        <button type="button" class="doctor-password-toggle" data-toggle-password="current_password" aria-label="Show password" title="Show password">
                                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                            </svg>
                                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="doctor-field">
                                    <label for="new_password">New Password</label>
                                    <div class="doctor-password-control">
                                        <input type="password" name="new_password" id="new_password" class="doctor-input" required>
                                        <button type="button" class="doctor-password-toggle" data-toggle-password="new_password" aria-label="Show password" title="Show password">
                                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                            </svg>
                                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="doctor-helper-text">Must include at least one letter and one number.</p>
                                </div>
                                <div class="doctor-field">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <div class="doctor-password-control">
                                        <input type="password" name="confirm_password" id="confirm_password" class="doctor-input" required>
                                        <button type="button" class="doctor-password-toggle" data-toggle-password="confirm_password" aria-label="Show password" title="Show password">
                                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                            </svg>
                                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="doctor-form-actions">
                                <button type="submit" class="doctor-btn">Update Password</button>
                                <a href="index.php" class="doctor-btn secondary">Cancel</a>
                            </div>
                        </form>
                    </article>

                    <article class="doctor-page-card doctor-account-card doctor-danger-card" id="danger-zone">
                        <div class="doctor-card-header">
                            <div>
                                <span class="doctor-section-kicker danger">Danger Zone</span>
                                <h2>Delete Account</h2>
                                <p>Deleting your account is permanent. Your sessions and related appointment records will be removed.</p>
                            </div>
                        </div>
                        <form action="" method="POST" class="doctor-account-form" id="delete-account-form">
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="confirm_delete" id="confirm_delete" value="0">
                            <div class="doctor-danger-notice">
                                <strong>Warning</strong>
                                <span>This action cannot be undone after confirmation.</span>
                            </div>
                            <div class="doctor-form-grid single">
                                <div class="doctor-field">
                                    <label for="delete_password">Current Password</label>
                                    <div class="doctor-password-control">
                                        <input type="password" name="delete_password" id="delete_password" class="doctor-input" required>
                                        <button type="button" class="doctor-password-toggle" data-toggle-password="delete_password" aria-label="Show password" title="Show password">
                                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                            </svg>
                                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <label class="delete-confirmation">
                                <input type="checkbox" name="delete_confirm_check" required>
                                <span>I understand this action is permanent and cannot be undone.</span>
                            </label>
                            <div class="doctor-form-actions">
                                <button type="submit" class="doctor-btn danger">Delete My Account</button>
                                <a href="index.php" class="doctor-btn secondary">Cancel</a>
                            </div>
                        </form>
                    </article>
                </div>

                <aside class="doctor-account-side">
                    <article class="doctor-page-card doctor-account-card">
                        <div class="doctor-card-header">
                            <div>
                                <span class="doctor-section-kicker">Record</span>
                                <h2>Account Record</h2>
                                <p>Key account and verification timestamps.</p>
                            </div>
                        </div>
                        <div class="verify-details-list account-details-list">
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Doctor ID</span>
                                <strong class="verify-detail-value">#<?php echo $userid; ?></strong>
                            </div>
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Email Address</span>
                                <strong class="verify-detail-value"><?php echo h($doctor["docemail"] ?? ""); ?></strong>
                            </div>
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Joined Date</span>
                                <strong class="verify-detail-value"><?php echo h(display_account_date($doctor["created_at"] ?? "")); ?></strong>
                            </div>
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Submitted Date</span>
                                <strong class="verify-detail-value"><?php echo h(display_account_date($doctor["verification_submitted_at"] ?? "")); ?></strong>
                            </div>
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Approval Date</span>
                                <strong class="verify-detail-value"><?php echo h(display_account_date($reviewedAt)); ?></strong>
                            </div>
                            <div class="verify-detail-item">
                                <span class="verify-detail-label">Account Status</span>
                                <strong class="verify-detail-value"><?php echo h($accountState); ?></strong>
                            </div>
                        </div>
                    </article>

                    <article class="doctor-page-card doctor-account-card doctor-account-help-card">
                        <span class="doctor-section-kicker">Verification</span>
                        <h2><?php echo h($approvalStatus); ?></h2>
                        <p><?php echo h($verificationMessage); ?></p>
                        <a href="verification.php" class="doctor-btn secondary"><?php echo $verificationStatus === "rejected" ? "Resubmit Documents" : "Go to Verification"; ?></a>
                    </article>
                </aside>
            </section>
        </main>
    </div>
</div>

<div class="doctor-modal" id="delete-modal" aria-hidden="true">
    <div class="doctor-page-card doctor-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <h2 id="delete-modal-title">Delete doctor account?</h2>
        <p>This permanently removes your doctor profile, sessions, and appointment records. This action cannot be undone.</p>
        <div class="doctor-delete-dialog-actions">
            <button type="button" class="doctor-btn secondary" id="cancel-delete">Cancel</button>
            <button type="button" class="doctor-btn danger" id="confirm-delete">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
        const input = document.getElementById(button.dataset.togglePassword);
        if (!input) {
            return;
        }

        button.addEventListener('click', function() {
            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.classList.toggle('is-visible', shouldShow);
            button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
            button.setAttribute('title', shouldShow ? 'Hide password' : 'Show password');
        });
    });

    const deleteForm = document.getElementById('delete-account-form');
    const deleteModal = document.getElementById('delete-modal');
    const cancelDelete = document.getElementById('cancel-delete');
    const confirmDelete = document.getElementById('confirm-delete');

    if (deleteForm) {
        deleteForm.addEventListener('submit', function(event) {
            const confirmedInput = document.getElementById('confirm_delete');
            if (confirmedInput.value === '1') {
                return;
            }

            event.preventDefault();
            deleteModal.classList.add('is-open');
            deleteModal.setAttribute('aria-hidden', 'false');
        });
    }

    if (cancelDelete) {
        cancelDelete.addEventListener('click', function() {
            deleteModal.classList.remove('is-open');
            deleteModal.setAttribute('aria-hidden', 'true');
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function(event) {
            if (event.target === deleteModal) {
                deleteModal.classList.remove('is-open');
                deleteModal.setAttribute('aria-hidden', 'true');
            }
        });
    }

    if (confirmDelete) {
        confirmDelete.addEventListener('click', function() {
            document.getElementById('confirm_delete').value = '1';
            deleteForm.submit();
        });
    }
</script>
</body>
</html>
