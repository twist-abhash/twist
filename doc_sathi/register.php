<?php
require_once __DIR__ . "/session_config.php";
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doc Sathi | Register</title>
    <link rel="stylesheet" href="css/auth-flow.css">
</head>
<body class="auth-page">
    <main class="auth-shell auth-choice-shell">
        <section class="auth-brand-panel">
            <a href="index.html" class="auth-logo">
                <img src="img/logo1.png" alt="Doc Sathi logo">
            </a>
            <p class="eyebrow">Doc Sathi</p>
            <h1>Healthcare access made simpler.</h1>
            <p class="brand-copy">
                Create the right account for your role and continue with a secure, guided registration process.
            </p>
        </section>

        <section class="auth-card choice-card" aria-labelledby="registration-title">
            <p class="eyebrow">Registration</p>
            <h2 id="registration-title">Choose Registration Type</h2>
            <p class="auth-subtext">Select how you want to proceed:</p>

            <div class="choice-actions">
                <a href="patient-register.php" class="choice-button">
                    <span class="choice-icon">P</span>
                    <span>
                        <strong>Register as Patient</strong>
                        <small>Book appointments with verified doctors.</small>
                    </span>
                </a>

                <a href="doctor-register.php" class="choice-button choice-button-alt">
                    <span class="choice-icon">D</span>
                    <span>
                        <strong>Register as Doctor</strong>
                        <small>Submit credentials and wait for admin approval.</small>
                    </span>
                </a>
            </div>

            <p class="auth-link-row">Already have an account? <a href="login.php">Log in</a></p>
        </section>
    </main>
</body>
</html>
