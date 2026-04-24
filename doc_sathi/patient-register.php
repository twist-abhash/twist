<?php
require_once __DIR__ . "/session_config.php";
require_once __DIR__ . "/algorithms.php";
session_start();

date_default_timezone_set('Asia/Kathmandu');
$_SESSION["date"] = date('Y-m-d');

include("connection.php");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$success = false;
$pemail = "";
$pname = "";
$paddress = "";
$pdob = "";
$pgender = "";
$pnum = "";
$patientMinimumAge = 16;
$patientMaximumAge = 100;
$patientDobLimit = doc_sathi_max_dob_for_age($patientMinimumAge);
$patientOldestDob = doc_sathi_min_dob_for_max_age($patientMaximumAge);
$today = date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pemail = trim($_POST['pemail'] ?? '');
        $pname = trim($_POST['pname'] ?? '');
        $ppassword = $_POST['ppassword'] ?? '';
        $pconfirm_password = $_POST['pconfirm_password'] ?? '';
        $paddress = trim($_POST['paddress'] ?? '');
        $pdob = trim($_POST['pdob'] ?? '');
        $pgender = doc_sathi_normalize_gender($_POST['pgender'] ?? '');
        $pnum = trim($_POST['pnum'] ?? '');

        if (!preg_match("/^[a-zA-Z][a-zA-Z ]*$/", $pname)) {
            $message = "Name must contain alphabets and cannot be only spaces.";
        } elseif (!doc_sathi_email_is_valid($pemail)) {
            $message = doc_sathi_email_policy_message();
        } elseif (doc_sathi_parse_date($pdob) === null) {
            $message = doc_sathi_dob_validation_message();
        } elseif (doc_sathi_dob_is_in_future($pdob)) {
            $message = doc_sathi_dob_future_message();
        } elseif (!doc_sathi_patient_dob_is_valid_age($pdob)) {
            $message = doc_sathi_maximum_age_message('Patient', $patientMaximumAge);
        } elseif (!doc_sathi_dob_meets_minimum_age($pdob, $patientMinimumAge)) {
            $message = doc_sathi_minimum_age_message('Patient', $patientMinimumAge);
        } elseif (!doc_sathi_gender_is_valid($pgender)) {
            $message = doc_sathi_gender_required_message();
        } elseif ($ppassword !== $pconfirm_password) {
            $message = "Passwords do not match.";
        } elseif (!doc_sathi_password_is_strong($ppassword)) {
            $message = doc_sathi_password_policy_message();
        } elseif (!doc_sathi_phone_is_valid($pnum)) {
            $message = doc_sathi_phone_policy_message();
        } elseif (doc_sathi_patient_phone_exists($database, $pnum)) {
            $message = "This mobile number is already used by another patient account.";
        } elseif (doc_sathi_email_exists($database, $pemail)) {
            $message = "Email already exists.";
        } else {
            $hashedPassword = password_hash($ppassword, PASSWORD_DEFAULT);
            $usertype = 'p';
            $transactionStarted = false;

            try {
                $database->begin_transaction();
                $transactionStarted = true;

                $patientStmt = doc_sathi_prepare(
                    $database,
                    "INSERT INTO patient (pemail, pname, ppassword, paddress, pdob, gender, pnum)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $patientStmt->bind_param("sssssss", $pemail, $pname, $hashedPassword, $paddress, $pdob, $pgender, $pnum);
                doc_sathi_execute($patientStmt);

                $webuserStmt = doc_sathi_prepare($database, "INSERT INTO webuser (email, usertype) VALUES (?, ?)");
                $webuserStmt->bind_param("ss", $pemail, $usertype);
                doc_sathi_execute($webuserStmt);

                $database->commit();
                $success = true;
                $message = "Registration successful. Redirecting to login...";
                echo "<script>setTimeout(() => window.location.href='login.php', 2000);</script>";
            } catch (Throwable $exception) {
                if ($transactionStarted) {
                    doc_sathi_safe_rollback($database);
                }

                $message = doc_sathi_database_is_connection_error(null, $exception->getMessage())
                    ? doc_sathi_public_database_error_message(null, $exception->getMessage())
                    : "Error in registration. Please try again.";
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
    <title>Doc Sathi | Patient Registration</title>
    <link rel="stylesheet" href="css/auth-flow.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <a href="index.html" class="auth-logo">
                <img src="img/logo1.png" alt="Doc Sathi logo">
            </a>
            <p class="eyebrow">Patient Account</p>
            <h1>Book appointments with verified doctors.</h1>
            <p class="brand-copy">
                Create your patient profile to search doctors, review available sessions, and manage your bookings in one place.
            </p>
        </section>

        <section class="auth-card">
            <p class="eyebrow">Patient Registration</p>
            <h2>Create a Patient Account</h2>
            <p class="auth-subtext">Enter your details exactly as you want them to appear in appointments.</p>

            <?php if ($message !== ""): ?>
                <div class="auth-alert <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="auth-form" onsubmit="return validateForm()">
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="pname">Full Name</label>
                        <input type="text" name="pname" id="pname" value="<?php echo h($pname); ?>" placeholder="Full Name" required onkeypress="return allowOnlyAlphabets(event)" onblur="validateName()">
                        <p id="name-error" class="field-error"></p>
                    </div>

                    <div class="field">
                        <label for="pemail">Email Address</label>
                        <input type="email" name="pemail" id="pemail" value="<?php echo h($pemail); ?>" placeholder="name@example.com" required>
                    </div>

                    <div class="field">
                        <label for="pnum">Mobile Number</label>
                        <input type="tel" name="pnum" id="pnum" value="<?php echo h($pnum); ?>" placeholder="98XXXXXXXX" inputmode="numeric" pattern="(98|97)[0-9]{8}" minlength="10" maxlength="10" autocomplete="tel-national" required oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);this.setCustomValidity('');clearError('phone-error');" oninvalid="this.setCustomValidity(<?php echo h(json_encode(doc_sathi_phone_policy_message())); ?>)" onblur="validatePhone()">
                        <p id="phone-error" class="field-error"></p>
                    </div>

                    <div class="field">
                        <label for="pgender">Gender</label>
                        <select name="pgender" id="pgender" required>
                            <option value="" disabled <?php echo $pgender === "" ? 'selected' : ''; ?>>Select Gender</option>
                            <?php foreach (doc_sathi_valid_genders() as $genderOption): ?>
                                <option value="<?php echo h($genderOption); ?>" <?php echo $pgender === $genderOption ? 'selected' : ''; ?>>
                                    <?php echo h(doc_sathi_gender_label($genderOption)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field field-full">
                        <label for="paddress">Home Address</label>
                        <input type="text" name="paddress" id="paddress" value="<?php echo h($paddress); ?>" placeholder="Street, city, district" required>
                    </div>

                    <div class="field">
                        <label for="pdob">Date of Birth</label>
                        <input type="date" name="pdob" id="pdob" value="<?php echo h($pdob); ?>" min="<?php echo h($patientOldestDob); ?>" max="<?php echo h($patientDobLimit); ?>" required oninput="clearError('dob-error');this.setCustomValidity('');" onblur="validateDob()">
                        <p id="dob-error" class="field-error"></p>
                    </div>

                    <div class="field">
                        <label for="ppassword">Password</label>
                        <div class="password-wrap">
                            <input type="password" name="ppassword" id="ppassword" placeholder="8+ chars, letter, number, special" required onblur="validatePassword()">
                            <button type="button" class="password-toggle" data-toggle-password="ppassword" aria-label="Show password" aria-controls="ppassword" title="Show password">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="field field-full">
                        <label for="pconfirm_password">Confirm Password</label>
                        <div class="password-wrap">
                            <input type="password" name="pconfirm_password" id="pconfirm_password" placeholder="Repeat your password" required onblur="validatePassword()">
                            <button type="button" class="password-toggle" data-toggle-password="pconfirm_password" aria-label="Show password" aria-controls="pconfirm_password" title="Show password">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                </svg>
                            </button>
                        </div>
                        <p id="password-error" class="field-error"></p>
                    </div>
                </div>

                <button type="submit" class="primary-action">Create Patient Account</button>
            </form>

            <p class="auth-link-row">Need a different account? <a href="register.php">Choose registration type</a></p>
            <p class="auth-link-row">Already have an account? <a href="login.php">Log in</a></p>
        </section>
    </main>

    <script>
        function showError(fieldId, message) {
            const errorElement = document.getElementById(fieldId);
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            return false;
        }

        function clearError(fieldId) {
            const errorElement = document.getElementById(fieldId);
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }

        function validateName() {
            const name = document.getElementById('pname').value;
            clearError('name-error');

            if (!name.trim()) {
                return showError('name-error', 'Name cannot be empty or just spaces.');
            }

            if (!/^[a-zA-Z][a-zA-Z ]*$/.test(name)) {
                return showError('name-error', 'Name must contain alphabets and cannot be only spaces.');
            }

            return true;
        }

        function validateDob() {
            const dob = document.getElementById('pdob').value;
            clearError('dob-error');
            const today = <?php echo json_encode($today); ?>;
            const patientDobLimit = <?php echo json_encode($patientDobLimit); ?>;
            const patientOldestDob = <?php echo json_encode($patientOldestDob); ?>;

            if (!dob) {
                return showError('dob-error', 'Please enter a valid date of birth.');
            }

            if (dob > today) {
                return showError('dob-error', <?php echo json_encode(doc_sathi_dob_future_message()); ?>);
            }

            if (dob < patientOldestDob) {
                return showError('dob-error', <?php echo json_encode(doc_sathi_maximum_age_message('Patient', $patientMaximumAge)); ?>);
            }

            if (dob > patientDobLimit) {
                return showError('dob-error', <?php echo json_encode(doc_sathi_minimum_age_message('Patient', $patientMinimumAge)); ?>);
            }

            return true;
        }

        function validatePhone() {
            const phone = document.getElementById('pnum').value;
            clearError('phone-error');

            if (!/^(98|97)[0-9]{8}$/.test(phone)) {
                return showError('phone-error', <?php echo json_encode(doc_sathi_phone_policy_message()); ?>);
            }

            return true;
        }

        function validatePassword() {
            const password = document.getElementById('ppassword').value;
            const confirmPassword = document.getElementById('pconfirm_password').value;
            clearError('password-error');

            if (password !== confirmPassword) {
                return showError('password-error', 'Passwords do not match.');
            }

            if (!/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(password)) {
                return showError('password-error', 'Password must be at least 8 characters and include a letter, number, and special character.');
            }

            return true;
        }

        function allowOnlyAlphabets(e) {
            const char = String.fromCharCode(e.keyCode || e.which);
            if (!/^[a-zA-Z ]$/.test(char)) {
                e.preventDefault();
                return false;
            }
            return true;
        }

        document.getElementById('pname').addEventListener('paste', function(e) {
            let pastedText = '';
            if (window.clipboardData && window.clipboardData.getData) {
                pastedText = window.clipboardData.getData('Text');
            } else if (e.clipboardData && e.clipboardData.getData) {
                pastedText = e.clipboardData.getData('text/plain');
            }

            if (!/^[a-zA-Z ]+$/.test(pastedText)) {
                e.preventDefault();
                showError('name-error', 'Only alphabets and spaces are allowed in the name field.');
            }
        });

        function validateForm() {
            return validateName() && validateDob() && validatePhone() && validatePassword();
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
