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
    <link rel="stylesheet" href="../css/patient-dashboard.css">

    <title>Account</title>
</head>
<body class="patient-dashboard-page">
    <?php
    require_once __DIR__ . "/../session_config.php";
    require_once __DIR__ . "/../algorithms.php";
    require_once __DIR__ . "/patient-ui.php";

    session_start();

    if (isset($_SESSION["user"])) {
        if (($_SESSION["user"]) == "" || $_SESSION["usertype"] != "p") {
            header("location: ../login.php");
            exit();
        }
        $useremail = $_SESSION["user"];
    } else {
        header("location: ../login.php");
        exit();
    }

    include("../connection.php");

    $sqlmain = "select * from patient where pemail=?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("s", $useremail);
    $stmt->execute();
    $userfetch = $stmt->get_result()->fetch_assoc();

    if (!$userfetch) {
        header("location: ../logout.php?role=p");
        exit();
    }

    $userid = (int)$userfetch["pid"];
    $username = $userfetch["pname"];
    $email = $userfetch["pemail"];
    $phone = trim((string)($userfetch["pnum"] ?? ""));
    $address = trim((string)($userfetch["paddress"] ?? ""));
    $dob = trim((string)($userfetch["pdob"] ?? ""));
    $genderValue = doc_sathi_normalize_gender($userfetch["gender"] ?? "");
    $genderDisplay = doc_sathi_gender_label($genderValue);
    $genderDisplay = $genderDisplay === "" ? "Not specified" : $genderDisplay;

    $phoneDisplay = $phone === "" ? "Not added" : $phone;
    $addressDisplay = $address === "" ? "Not added" : $address;
    $dobDisplay = $dob === "" || $dob === "0000-00-00" ? "Not added" : patient_portal_format_date($dob);
    $openDeleteModal = ($_GET["action"] ?? "") === "drop";

    $statusMessages = [
        "profile-updated" => "Profile updated successfully.",
        "password-updated" => "Password updated successfully.",
    ];
    $errorMessages = [
        "1" => "Already have an account for this email address.",
        "2" => "Password confirmation error. Reconfirm your password.",
        "3" => "Could not save changes. Please review the form and try again.",
        "8" => "Password must be at least 8 characters and include a letter, number, and special character.",
        "9" => "This phone number is already used by another patient account.",
        "10" => "Current password is incorrect.",
        "11" => "Phone number must start with 98 or 97 and contain exactly 10 digits.",
        "12" => "Please select a gender.",
        "4" => "Account details updated successfully. If you changed your email, please log out and log in again.",
    ];

    $noticeMessage = "";
    $noticeType = "success";
    if (isset($_GET["status"]) && isset($statusMessages[$_GET["status"]])) {
        $noticeMessage = $statusMessages[$_GET["status"]];
    } elseif (isset($_GET["error"]) && ($_GET["error"] ?? "0") !== "0") {
        $noticeMessage = $errorMessages[$_GET["error"]] ?? $errorMessages["3"];
        $noticeType = ($_GET["error"] ?? "") === "4" ? "success" : "error";
    }

    date_default_timezone_set("Asia/Kathmandu");
    $today = date("Y-m-d");
    ?>

    <div class="container patient-dashboard-layout">
        <?php patient_portal_sidebar($username, $useremail, "account"); ?>

        <main class="dash-body patient-dashboard-body">
            <div class="patient-dashboard-shell">
                <?php
                patient_portal_page_header([
                    "eyebrow" => "Profile",
                    "title" => "Account",
                    "subtitle" => "Manage your account details, password, and preferences.",
                    "today" => $today,
                ]);
                ?>

                <?php if ($noticeMessage !== ""): ?>
                    <div class="patient-alert <?php echo patient_portal_h($noticeType); ?>">
                        <?php echo patient_portal_h($noticeMessage); ?>
                    </div>
                <?php endif; ?>

                <section class="patient-account-overview" aria-labelledby="patient-profile-overview-title">
                    <article class="patient-panel patient-profile-summary-card">
                        <div class="patient-profile-summary-main">
                            <img src="../img/user.png" alt="Patient avatar" class="patient-profile-summary-photo" width="112" height="112">
                            <div>
                                <span class="patient-section-kicker">Profile</span>
                                <h2 id="patient-profile-overview-title"><?php echo patient_portal_h($username); ?></h2>
                                <p><?php echo patient_portal_h($email); ?></p>
                                <div class="patient-profile-badges">
                                    <span class="patient-account-pill">Patient</span>
                                    <span class="patient-status-badge upcoming">Active</span>
                                </div>
                            </div>
                        </div>

                        <div class="patient-profile-summary-meta" aria-label="Profile summary">
                            <div>
                                <span>Patient ID</span>
                                <strong>#<?php echo $userid; ?></strong>
                            </div>
                            <div>
                                <span>Phone</span>
                                <strong><?php echo patient_portal_h($phoneDisplay); ?></strong>
                            </div>
                            <div>
                                <span>Gender</span>
                                <strong><?php echo patient_portal_h($genderDisplay); ?></strong>
                            </div>
                            <div>
                                <span>Date of Birth</span>
                                <strong><?php echo patient_portal_h($dobDisplay); ?></strong>
                            </div>
                            <div>
                                <span>Location</span>
                                <strong><?php echo patient_portal_h($addressDisplay); ?></strong>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="patient-account-layout">
                    <div class="patient-account-stack">
                        <article class="patient-panel patient-account-section-card" id="profile-details">
                            <div class="patient-panel-header">
                                <div>
                                    <span class="patient-section-kicker">Editable Details</span>
                                    <h2>Account Details</h2>
                                    <p>Keep your profile and contact information current for bookings and account communication.</p>
                                </div>
                            </div>

                            <form action="edit-user.php" method="POST" class="patient-account-form" id="patient-profile-form">
                                <input type="hidden" name="form_action" value="profile">
                                <input type="hidden" value="<?php echo $userid; ?>" name="id00">
                                <input type="hidden" name="oldemail" value="<?php echo patient_portal_h($email); ?>">

                                <section class="patient-form-section">
                                    <div class="patient-form-section-heading">
                                        <h3>Personal Information</h3>
                                        <p>These details are used to identify your patient account.</p>
                                    </div>
                                    <div class="patient-form-grid">
                                        <label class="patient-field">
                                            <span>Name</span>
                                            <input type="text" name="name" id="name" class="input-text patient-filter-input" placeholder="Patient Name" value="<?php echo patient_portal_h($username); ?>" required onkeypress="return allowOnlyAlphabets(event)">
                                        </label>

                                        <label class="patient-field">
                                            <span>Email</span>
                                            <input type="email" name="email" class="input-text patient-filter-input" placeholder="Email Address" value="<?php echo patient_portal_h($email); ?>" required>
                                        </label>

                                        <label class="patient-field">
                                            <span>Telephone</span>
                                            <input type="tel" name="Tele" id="Tele" class="input-text patient-filter-input" placeholder="Telephone Number" value="<?php echo patient_portal_h($phone); ?>" inputmode="numeric" pattern="(98|97)[0-9]{8}" minlength="10" maxlength="10" autocomplete="tel-national" required oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);this.setCustomValidity('');" oninvalid="this.setCustomValidity('Phone number must start with 98 or 97 and contain exactly 10 digits.')">
                                        </label>

                                        <label class="patient-field">
                                            <span>Gender</span>
                                            <select name="gender" class="input-text patient-filter-input" required>
                                                <option value="" disabled <?php echo $genderValue === "" ? "selected" : ""; ?>>Select Gender</option>
                                                <?php foreach (doc_sathi_valid_genders() as $genderOption): ?>
                                                    <option value="<?php echo patient_portal_h($genderOption); ?>" <?php echo $genderValue === $genderOption ? "selected" : ""; ?>>
                                                        <?php echo patient_portal_h(doc_sathi_gender_label($genderOption)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <label class="patient-field">
                                            <span>Address</span>
                                            <input type="text" name="address" class="input-text patient-filter-input" placeholder="Address" value="<?php echo patient_portal_h($address); ?>" required>
                                        </label>
                                    </div>
                                </section>

                                <div class="patient-form-actions">
                                    <button type="submit" class="patient-btn primary">Save Profile</button>
                                    <button type="reset" class="patient-btn secondary">Reset</button>
                                </div>
                            </form>
                        </article>

                        <article class="patient-panel patient-account-section-card" id="password">
                            <div class="patient-panel-header">
                                <div>
                                    <span class="patient-section-kicker">Security</span>
                                    <h2>Change Password</h2>
                                    <p>Use a secure password with at least 8 characters, one letter, one number, and one special character.</p>
                                </div>
                            </div>

                            <form action="edit-user.php" method="POST" class="patient-account-form">
                                <input type="hidden" name="form_action" value="password">
                                <input type="hidden" value="<?php echo $userid; ?>" name="id00">

                                <div class="patient-form-grid">
                                    <label class="patient-field">
                                        <span>Current Password</span>
                                        <span class="patient-password-control">
                                            <input type="password" name="current_password" id="current_password" class="input-text patient-filter-input" required>
                                            <button type="button" class="patient-password-toggle" data-toggle-password="current_password" aria-label="Show password" title="Show password">
                                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                                </svg>
                                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </label>

                                    <label class="patient-field">
                                        <span>New Password</span>
                                        <span class="patient-password-control">
                                            <input type="password" name="new_password" id="new_password" class="input-text patient-filter-input" required>
                                            <button type="button" class="patient-password-toggle" data-toggle-password="new_password" aria-label="Show password" title="Show password">
                                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                                </svg>
                                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </label>

                                    <label class="patient-field">
                                        <span>Confirm New Password</span>
                                        <span class="patient-password-control">
                                            <input type="password" name="confirm_password" id="confirm_password" class="input-text patient-filter-input" required>
                                            <button type="button" class="patient-password-toggle" data-toggle-password="confirm_password" aria-label="Show password" title="Show password">
                                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                                </svg>
                                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </label>
                                </div>

                                <div class="patient-form-actions">
                                    <button type="submit" class="patient-btn primary">Update Password</button>
                                    <button type="reset" class="patient-btn secondary">Reset</button>
                                </div>
                            </form>
                        </article>

                        <article class="patient-panel patient-account-section-card patient-danger-card" id="danger-zone">
                            <div class="patient-panel-header">
                                <div>
                                    <span class="patient-section-kicker danger">Danger Zone</span>
                                    <h2>Delete Account</h2>
                                    <p>Deleting your account is permanent and removes your patient account access.</p>
                                </div>
                            </div>

                            <div class="patient-danger-notice">
                                <strong>Warning</strong>
                                <span>This action cannot be undone after confirmation.</span>
                            </div>

                            <form action="delete-account.php" method="POST" class="patient-account-form" id="delete-account-form">
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="id00" value="<?php echo $userid; ?>">
                                <input type="hidden" name="confirm_delete" id="confirm_delete" value="0">

                                <div class="patient-form-grid single">
                                    <label class="patient-field">
                                        <span>Current Password</span>
                                        <span class="patient-password-control">
                                            <input type="password" name="delete_password" id="delete_password" class="input-text patient-filter-input" required>
                                            <button type="button" class="patient-password-toggle" data-toggle-password="delete_password" aria-label="Show password" title="Show password">
                                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M12 5c5.2 0 8.5 4.4 9.6 6.2.3.5.3 1.1 0 1.6C20.5 14.6 17.2 19 12 19s-8.5-4.4-9.6-6.2a1.5 1.5 0 0 1 0-1.6C3.5 9.4 6.8 5 12 5Zm0 2C7.8 7 5.1 10.5 4.2 12c.9 1.5 3.6 5 7.8 5s6.9-3.5 7.8-5C18.9 10.5 16.2 7 12 7Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"/>
                                                </svg>
                                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path fill="currentColor" d="M3.3 2.3 21.7 20.7l-1.4 1.4-3.1-3.1A10.6 10.6 0 0 1 12 20c-5.2 0-8.6-4.4-9.7-6.2a1.6 1.6 0 0 1 0-1.6 16 16 0 0 1 4-4.3L1.9 3.7l1.4-1.4Zm4.5 7A13.8 13.8 0 0 0 4.2 13c.9 1.5 3.6 5 7.8 5 1.3 0 2.5-.3 3.5-.9l-2-2A3.8 3.8 0 0 1 8.9 10.5l-1.1-1.2ZM12 4c5.2 0 8.6 4.4 9.7 6.2.3.5.3 1.1 0 1.6a16 16 0 0 1-3.1 3.6L16.8 14a13.7 13.7 0 0 0 3-3c-.9-1.5-3.6-5-7.8-5-1 0-1.9.2-2.8.6L7.7 5.1C9 4.4 10.4 4 12 4Zm.3 4.2a3.8 3.8 0 0 1 3.5 3.5l-3.5-3.5Z"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </label>
                                </div>

                                <label class="patient-delete-confirmation">
                                    <input type="checkbox" name="delete_confirm_check" required>
                                    <span>I understand this action is permanent and cannot be undone.</span>
                                </label>

                                <div class="patient-form-actions">
                                    <button type="submit" class="patient-btn danger">Delete My Account</button>
                                    <a href="index.php" class="patient-btn secondary">Cancel</a>
                                </div>
                            </form>
                        </article>
                    </div>

                    <aside class="patient-account-side">
                        <article class="patient-panel patient-account-section-card">
                            <div class="patient-panel-header">
                                <div>
                                    <span class="patient-section-kicker">Record</span>
                                    <h2>Account Record</h2>
                                    <p>Your saved patient profile information.</p>
                                </div>
                            </div>

                            <div class="patient-record-list">
                                <div>
                                    <span>Patient ID</span>
                                    <strong>#<?php echo $userid; ?></strong>
                                </div>
                                <div>
                                    <span>Email Address</span>
                                    <strong><?php echo patient_portal_h($email); ?></strong>
                                </div>
                                <div>
                                    <span>Telephone</span>
                                    <strong><?php echo patient_portal_h($phoneDisplay); ?></strong>
                                </div>
                                <div>
                                    <span>Date of Birth</span>
                                    <strong><?php echo patient_portal_h($dobDisplay); ?></strong>
                                </div>
                                <div>
                                    <span>Address</span>
                                    <strong><?php echo patient_portal_h($addressDisplay); ?></strong>
                                </div>
                                <div>
                                    <span>Account Status</span>
                                    <strong>Active</strong>
                                </div>
                            </div>
                        </article>

                        <article class="patient-panel patient-account-section-card patient-account-help-card">
                            <span class="patient-section-kicker">Care Access</span>
                            <h2>Patient Portal</h2>
                            <p>Use your account to book sessions, review appointments, and manage your care activity.</p>
                            <div class="patient-side-actions">
                                <a href="schedule.php" class="patient-btn secondary">Book a Session</a>
                                <a href="appointment.php" class="patient-btn secondary">My Bookings</a>
                            </div>
                        </article>
                    </aside>
                </section>
            </div>
        </main>
    </div>

    <div class="patient-delete-modal <?php echo $openDeleteModal ? "is-open" : ""; ?>" id="delete-modal" aria-hidden="<?php echo $openDeleteModal ? "false" : "true"; ?>">
        <div class="patient-panel patient-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <h2 id="delete-modal-title">Delete patient account?</h2>
            <p>This permanently removes your patient account. This action cannot be undone.</p>
            <div class="patient-delete-dialog-actions">
                <button type="button" class="patient-btn secondary" id="cancel-delete">Cancel</button>
                <button type="button" class="patient-btn danger" id="confirm-delete">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        function allowOnlyAlphabets(e) {
            const char = String.fromCharCode(e.keyCode || e.which);
            const regex = /^[a-zA-Z ]$/;

            if (!regex.test(char)) {
                e.preventDefault();
                return false;
            }
            return true;
        }

        const nameInput = document.getElementById("name");
        if (nameInput) {
            nameInput.addEventListener("paste", function(e) {
                let pastedText = "";
                if (window.clipboardData && window.clipboardData.getData) {
                    pastedText = window.clipboardData.getData("Text");
                } else if (e.clipboardData && e.clipboardData.getData) {
                    pastedText = e.clipboardData.getData("text/plain");
                }

                const regex = /^[a-zA-Z ]+$/;
                const hasAlphabet = /[a-zA-Z]/.test(pastedText);

                if (!regex.test(pastedText) || !hasAlphabet) {
                    e.preventDefault();
                    alert("Name must contain alphabets and cannot be only spaces!");
                }
            });
        }

        const profileForm = document.getElementById("patient-profile-form");
        if (profileForm && nameInput) {
            profileForm.addEventListener("submit", function(e) {
                const name = nameInput.value;
                const nameRegex = /^[a-zA-Z ]+$/;
                const hasAlphabet = /[a-zA-Z]/.test(name);

                if (!nameRegex.test(name)) {
                    e.preventDefault();
                    alert("Only alphabets and spaces are allowed in the name field!");
                    return false;
                }

                if (!hasAlphabet) {
                    e.preventDefault();
                    alert("Name cannot be only spaces, it must contain at least one alphabet character!");
                    return false;
                }
            });
        }

        document.querySelectorAll("[data-toggle-password]").forEach(function(button) {
            const input = document.getElementById(button.dataset.togglePassword);
            if (!input) {
                return;
            }

            button.addEventListener("click", function() {
                const shouldShow = input.type === "password";
                input.type = shouldShow ? "text" : "password";
                button.classList.toggle("is-visible", shouldShow);
                button.setAttribute("aria-label", shouldShow ? "Hide password" : "Show password");
                button.setAttribute("title", shouldShow ? "Hide password" : "Show password");
            });
        });

        const deleteForm = document.getElementById("delete-account-form");
        const deleteModal = document.getElementById("delete-modal");
        const cancelDelete = document.getElementById("cancel-delete");
        const confirmDelete = document.getElementById("confirm-delete");

        function closeDeleteModal() {
            deleteModal.classList.remove("is-open");
            deleteModal.setAttribute("aria-hidden", "true");
        }

        if (deleteForm && deleteModal) {
            deleteForm.addEventListener("submit", function(event) {
                const confirmedInput = document.getElementById("confirm_delete");
                if (confirmedInput.value === "1") {
                    return;
                }

                event.preventDefault();
                deleteModal.classList.add("is-open");
                deleteModal.setAttribute("aria-hidden", "false");
            });
        }

        if (cancelDelete) {
            cancelDelete.addEventListener("click", closeDeleteModal);
        }

        if (deleteModal) {
            deleteModal.addEventListener("click", function(event) {
                if (event.target === deleteModal) {
                    closeDeleteModal();
                }
            });
        }

        if (confirmDelete && deleteForm) {
            confirmDelete.addEventListener("click", function() {
                document.getElementById("confirm_delete").value = "1";
                deleteForm.submit();
            });
        }
    </script>
</body>
</html>
