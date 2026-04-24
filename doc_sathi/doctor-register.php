<?php
require_once __DIR__ . "/session_config.php";
require_once __DIR__ . "/algorithms.php";
session_start();

date_default_timezone_set('Asia/Kathmandu');
include("connection.php");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$success = false;
$name = "";
$email = "";
$phone = "";
$gender = "";
$address = "";
$clinicName = "";
$dob = "";
$specialty = 0;
$doctorMinimumAge = 27;
$doctorMaximumAge = 100;
$doctorDobLimit = doc_sathi_max_dob_for_age($doctorMinimumAge);
$doctorOldestDob = doc_sathi_min_dob_for_max_age($doctorMaximumAge);
$today = date('Y-m-d');
$specialties = [];

try {
    $specialtiesStmt = doc_sathi_prepare($database, "SELECT id, sname FROM specialties ORDER BY sname ASC");
    doc_sathi_execute($specialtiesStmt);
    $specialtiesResult = $specialtiesStmt->get_result();

    if ($specialtiesResult instanceof mysqli_result) {
        while ($row = $specialtiesResult->fetch_assoc()) {
            $specialties[] = $row;
        }
    }
} catch (Throwable $exception) {
    $message = doc_sathi_public_database_error_message(null, $exception->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $name = trim($_POST['docname'] ?? '');
        $email = trim($_POST['docemail'] ?? '');
        $phone = trim($_POST['doctel'] ?? '');
        $gender = doc_sathi_normalize_gender($_POST['docgender'] ?? '');
        $address = trim($_POST['docaddress'] ?? '');
        $clinicName = trim($_POST['clinic_name'] ?? '');
        $dob = trim($_POST['docdob'] ?? '');
        $specialty = (int)($_POST['specialties'] ?? 0);
        $password = $_POST['docpassword'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $name) || !preg_match('/[A-Za-z]/', $name)) {
            $message = "Full name must contain letters and valid name characters only.";
        } elseif (!doc_sathi_email_is_valid($email)) {
            $message = doc_sathi_email_policy_message();
        } elseif (!doc_sathi_phone_is_valid($phone)) {
            $message = doc_sathi_phone_policy_message();
        } elseif (!doc_sathi_gender_is_valid($gender)) {
            $message = doc_sathi_gender_required_message();
        } elseif ($address === "") {
            $message = "Please enter your address.";
        } elseif ($clinicName === "" || strlen($clinicName) > 255) {
            $message = "Please enter your clinic or hospital name.";
        } elseif (doc_sathi_parse_date($dob) === null) {
            $message = doc_sathi_dob_validation_message();
        } elseif (doc_sathi_dob_is_in_future($dob)) {
            $message = doc_sathi_dob_future_message();
        } elseif (!doc_sathi_doctor_dob_is_valid_age($dob)) {
            $message = doc_sathi_maximum_age_message('Doctor', $doctorMaximumAge);
        } elseif (!doc_sathi_dob_meets_minimum_age($dob, $doctorMinimumAge)) {
            $message = doc_sathi_minimum_age_message('Doctor', $doctorMinimumAge);
        } elseif ($specialty <= 0) {
            $message = "Please select a specialty.";
        } elseif ($password !== $confirmPassword) {
            $message = "Passwords do not match.";
        } elseif (!doc_sathi_password_is_strong($password)) {
            $message = doc_sathi_password_policy_message();
        } elseif (doc_sathi_email_exists($database, $email)) {
            $message = "An account already exists for this email address.";
        } elseif (doc_sathi_doctor_phone_exists($database, $phone)) {
            $message = "This mobile number is already used by another doctor account.";
        } else {
            $specialtyStmt = doc_sathi_prepare($database, "SELECT id FROM specialties WHERE id = ? LIMIT 1");
            $specialtyStmt->bind_param("i", $specialty);
            doc_sathi_execute($specialtyStmt);

            if ($specialtyStmt->get_result()->num_rows === 0) {
                $message = "Please select a valid specialty.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $status = 'pending';
                $usertype = 'd';
                $transactionStarted = false;

                try {
                    $database->begin_transaction();
                    $transactionStarted = true;

                    $doctorStmt = doc_sathi_prepare(
                        $database,
                        "INSERT INTO doctor (docemail, docname, docpassword, doctel, docaddress, docdob, gender, specialties, clinic_name, verification_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $doctorStmt->bind_param("sssssssiss", $email, $name, $hashedPassword, $phone, $address, $dob, $gender, $specialty, $clinicName, $status);
                    doc_sathi_execute($doctorStmt);

                    $webuserStmt = doc_sathi_prepare($database, "INSERT INTO webuser (email, usertype) VALUES (?, ?)");
                    $webuserStmt->bind_param("ss", $email, $usertype);
                    doc_sathi_execute($webuserStmt);

                    $database->commit();
                    $success = true;
                    $message = "Doctor registration submitted. You can log in now while admin verification is pending.";
                    echo "<script>setTimeout(() => window.location.href='login.php', 2500);</script>";
                } catch (Throwable $exception) {
                    if ($transactionStarted) {
                        doc_sathi_safe_rollback($database);
                    }

                    $message = doc_sathi_database_is_connection_error(null, $exception->getMessage())
                        ? doc_sathi_public_database_error_message(null, $exception->getMessage())
                        : "Doctor registration failed. Please try again.";
                }
            }
        }
    } catch (Throwable $exception) {
        $message = doc_sathi_public_database_error_message(null, $exception->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doc Sathi | Doctor Registration</title>
    <link rel="stylesheet" href="css/auth-flow.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a href="index.html" class="auth-logo">
                <img src="img/logo1.png" alt="Doc Sathi logo">
            </a>
            <p class="eyebrow">Doctor Account</p>
            <h1>Join Doc Sathi with verified credentials.</h1>
            <p class="brand-copy">
                Submit your profile for admin review. You can access your dashboard immediately while approval is pending.
            </p>
        </section>

        <section class="auth-card">
            <p class="eyebrow">Doctor Registration</p>
            <h2>Create a Doctor Account</h2>
            <p class="auth-subtext">Your account will remain pending until an admin reviews and approves it.</p>

            <?php if ($message !== ""): ?>
                <div class="auth-alert <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" onsubmit="return validateDoctorForm()">
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="docname">Full Name</label>
                        <input type="text" name="docname" id="docname" value="<?php echo h($name); ?>" placeholder="Dr. Full Name" required>
                    </div>

                    <div class="field">
                        <label for="docemail">Email</label>
                        <input type="email" name="docemail" id="docemail" value="<?php echo h($email); ?>" placeholder="doctor@example.com" required>
                    </div>

                    <div class="field">
                        <label for="doctel">Mobile Number</label>
                        <input type="tel" name="doctel" id="doctel" value="<?php echo h($phone); ?>" placeholder="98XXXXXXXX" inputmode="numeric" pattern="(98|97)[0-9]{8}" minlength="10" maxlength="10" autocomplete="tel-national" required oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);this.setCustomValidity('');" oninvalid="this.setCustomValidity(<?php echo h(json_encode(doc_sathi_phone_policy_message())); ?>)">
                    </div>

                    <div class="field">
                        <label for="docgender">Gender</label>
                        <select name="docgender" id="docgender" required>
                            <option value="" disabled <?php echo $gender === "" ? 'selected' : ''; ?>>Select Gender</option>
                            <?php foreach (doc_sathi_valid_genders() as $genderOption): ?>
                                <option value="<?php echo h($genderOption); ?>" <?php echo $gender === $genderOption ? 'selected' : ''; ?>>
                                    <?php echo h(doc_sathi_gender_label($genderOption)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="docdob">Date of Birth</label>
                        <input type="date" name="docdob" id="docdob" value="<?php echo h($dob); ?>" min="<?php echo h($doctorOldestDob); ?>" max="<?php echo h($doctorDobLimit); ?>" required oninput="clearDobError();this.setCustomValidity('');" onblur="validateDoctorDob()">
                        <p id="dob-error" class="field-error"></p>
                    </div>

                    <div class="field field-full">
                        <label for="docaddress">Address</label>
                        <input type="text" name="docaddress" id="docaddress" value="<?php echo h($address); ?>" placeholder="Clinic or home address" required>
                    </div>

                    <div class="field field-full">
                        <label for="clinic_name">Clinic / Hospital Name</label>
                        <input type="text" name="clinic_name" id="clinic_name" value="<?php echo h($clinicName); ?>" placeholder="Clinic or hospital patients can visit" maxlength="255" required>
                    </div>

                    <div class="field field-full">
                        <label for="specialties">Specialty</label>
                        <select name="specialties" id="specialties" required>
                            <option value="" disabled <?php echo $specialty <= 0 ? 'selected' : ''; ?>>Select Specialty</option>
                            <?php foreach ($specialties as $row): ?>
                                <option value="<?php echo (int)$row['id']; ?>" <?php echo $specialty === (int)$row['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($row['sname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="docpassword">Password</label>
                        <div class="password-wrap">
                            <input type="password" name="docpassword" id="docpassword" placeholder="8+ chars, letter, number, special" required>
                            <button type="button" class="password-toggle" data-toggle-password="docpassword" aria-label="Show password" aria-controls="docpassword" title="Show password">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="password-wrap">
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat your password" required>
                            <button type="button" class="password-toggle" data-toggle-password="confirm_password" aria-label="Show password" aria-controls="confirm_password" title="Show password">
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

                <button type="submit" class="primary-action">Submit for Verification</button>
            </form>

            <p class="auth-link-row">Need a different account? <a href="register.php">Choose registration type</a></p>
            <p class="auth-link-row">Already registered? <a href="login.php">Log in</a></p>
        </section>
    </main>
    <script>
        function showDobError(message) {
            const errorElement = document.getElementById('dob-error');
            if (!errorElement) {
                return false;
            }

            errorElement.textContent = message;
            errorElement.style.display = 'block';
            return false;
        }

        function clearDobError() {
            const errorElement = document.getElementById('dob-error');
            if (!errorElement) {
                return;
            }

            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }

        function validateDoctorDob() {
            const dobInput = document.getElementById('docdob');
            if (!dobInput) {
                return true;
            }

            const dob = dobInput.value;
            const today = <?php echo json_encode($today); ?>;
            const doctorDobLimit = <?php echo json_encode($doctorDobLimit); ?>;
            const doctorOldestDob = <?php echo json_encode($doctorOldestDob); ?>;
            clearDobError();

            if (!dob) {
                return showDobError('Please enter a valid date of birth.');
            }

            if (dob > today) {
                return showDobError(<?php echo json_encode(doc_sathi_dob_future_message()); ?>);
            }

            if (dob < doctorOldestDob) {
                return showDobError(<?php echo json_encode(doc_sathi_maximum_age_message('Doctor', $doctorMaximumAge)); ?>);
            }

            if (dob > doctorDobLimit) {
                return showDobError(<?php echo json_encode(doc_sathi_minimum_age_message('Doctor', $doctorMinimumAge)); ?>);
            }

            return true;
        }

        function validateDoctorForm() {
            return validateDoctorDob();
        }

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
    </script>
</body>
</html>
