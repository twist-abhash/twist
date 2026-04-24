<?php

if (!function_exists('patient_portal_h')) {
    function patient_portal_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function patient_portal_format_date($value, $format = 'M d, Y')
{
    $timestamp = strtotime((string)$value);
    return $timestamp ? date($format, $timestamp) : 'Not scheduled';
}

function patient_portal_format_time($value)
{
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('h:i A', $timestamp) : 'Time pending';
}

function patient_portal_initials($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return 'DS';
    }

    $parts = preg_split('/\s+/', $name);
    $first = strtoupper(substr($parts[0] ?? 'D', 0, 1));
    $second = strtoupper(substr($parts[1] ?? ($parts[0] ?? 'S'), 0, 1));

    return $first . $second;
}

function patient_portal_sidebar($username, $useremail, $activePage)
{
    $items = [
        ['key' => 'home', 'href' => 'index.php', 'label' => 'Home', 'icon' => 'home'],
        ['key' => 'doctors', 'href' => 'doctors.php', 'label' => 'Doctors', 'icon' => 'doctor'],
        ['key' => 'specialties', 'href' => 'department.php', 'label' => 'Specialties', 'icon' => 'doctor'],
        ['key' => 'sessions', 'href' => 'schedule.php', 'label' => 'Sessions', 'icon' => 'session'],
        ['key' => 'bookings', 'href' => 'appointment.php', 'label' => 'My Bookings', 'icon' => 'appoinment'],
        ['key' => 'account', 'href' => 'settings.php', 'label' => 'Account', 'icon' => 'settings'],
    ];

    echo '<aside class="menu patient-sidebar" aria-label="Patient navigation">';
    echo '<table class="menu-container" border="0">';
    echo '<tr><td class="patient-profile-cell" colspan="2">';
    echo '<table border="0" class="profile-container patient-profile-card">';
    echo '<tr>';
    echo '<td class="patient-avatar-cell"><img src="../img/user.png" alt="Patient avatar"></td>';
    echo '<td class="patient-profile-copy">';
    echo '<p class="profile-title" title="' . patient_portal_h($username) . '">' . patient_portal_h($username) . '</p>';
    echo '<p class="profile-subtitle" title="' . patient_portal_h($useremail) . '">' . patient_portal_h($useremail) . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr><td colspan="2"><a href="../logout.php?role=p"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a></td></tr>';
    echo '</table>';
    echo '</td></tr>';

    foreach ($items as $item) {
        $isActive = $item['key'] === $activePage;
        $cellClass = 'menu-btn menu-icon-' . $item['icon'];
        $linkClass = 'non-style-link-menu';
        $aria = '';

        if ($isActive) {
            $cellClass .= ' menu-active menu-icon-' . $item['icon'] . '-active';
            $linkClass .= ' non-style-link-menu-active';
            $aria = ' aria-current="page"';
        }

        echo '<tr class="menu-row">';
        echo '<td class="' . patient_portal_h($cellClass) . '">';
        echo '<a href="' . patient_portal_h($item['href']) . '" class="' . patient_portal_h($linkClass) . '"' . $aria . '><div><p class="menu-text">' . patient_portal_h($item['label']) . '</p></div></a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</aside>';
}

function patient_portal_date_card($today)
{
    return '<div class="patient-date-card" aria-label="Today date">'
        . '<span>Today\'s Date</span>'
        . '<strong>' . patient_portal_h(patient_portal_format_date($today)) . '</strong>'
        . '</div>';
}

function patient_portal_page_header(array $config)
{
    $title = $config['title'] ?? '';
    $subtitle = $config['subtitle'] ?? '';
    $eyebrow = $config['eyebrow'] ?? 'Patient Portal';
    $backHref = $config['back_href'] ?? 'index.php';
    $today = $config['today'] ?? date('Y-m-d');
    $searchHtml = trim((string)($config['search_html'] ?? ''));
    $headerClass = 'patient-page-header' . ($searchHtml === '' ? ' patient-page-header-simple' : '');

    echo '<header class="' . patient_portal_h($headerClass) . '">';
    echo '<a href="' . patient_portal_h($backHref) . '" class="patient-back-button">Back</a>';
    echo '<div class="patient-title-block">';
    echo '<span class="patient-eyebrow">' . patient_portal_h($eyebrow) . '</span>';
    echo '<h1>' . patient_portal_h($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . patient_portal_h($subtitle) . '</p>';
    }
    echo '</div>';

    if ($searchHtml !== '') {
        echo '<div class="patient-header-search">' . $searchHtml . '</div>';
    }

    echo patient_portal_date_card($today);
    echo '</header>';
}

function patient_portal_doctor_details_modal($returnHref, array $doctor)
{
    $specialty = trim((string)($doctor['specialty_name'] ?? ''));
    if ($specialty === '') {
        $specialty = 'Not specified';
    }

    $telephone = trim((string)($doctor['doctel'] ?? ''));
    if ($telephone === '') {
        $telephone = 'Not provided';
    }

    $gender = doc_sathi_gender_label($doctor['gender'] ?? '');
    if ($gender === '') {
        $gender = 'Not specified';
    }

    $clinic = trim((string)($doctor['clinic_name'] ?? ''));
    if ($clinic === '') {
        $clinic = 'Not provided';
    }

    echo '<div id="popup1" class="overlay patient-modal-overlay">';
    echo '<div class="popup patient-modal-card">';
    echo '<a class="close" href="' . patient_portal_h($returnHref) . '">&times;</a>';
    echo '<div class="patient-modal-heading">';
    echo '<span class="patient-eyebrow">Doctor Profile</span>';
    echo '<h2>Doctor Details</h2>';
    echo '<p>Review this doctor before viewing sessions or booking a visit.</p>';
    echo '</div>';
    echo '<dl class="patient-detail-list">';
    echo '<div><dt>Name</dt><dd>' . patient_portal_h($doctor['docname'] ?? '') . '</dd></div>';
    echo '<div><dt>Email</dt><dd>' . patient_portal_h($doctor['docemail'] ?? '') . '</dd></div>';
    echo '<div><dt>Telephone</dt><dd>' . patient_portal_h($telephone) . '</dd></div>';
    echo '<div><dt>Gender</dt><dd>' . patient_portal_h($gender) . '</dd></div>';
    echo '<div><dt>Specialty</dt><dd>' . patient_portal_h($specialty) . '</dd></div>';
    echo '<div><dt>Clinic / Hospital</dt><dd>' . patient_portal_h($clinic) . '</dd></div>';
    echo '</dl>';
    echo '<div class="patient-modal-actions">';
    echo '<a href="' . patient_portal_h($returnHref) . '" class="patient-btn primary">OK</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

function patient_portal_url_with_query($baseHref, array $params)
{
    $baseHref = trim((string)$baseHref);
    if ($baseHref === '') {
        $baseHref = 'doctors.php';
    }

    $separator = strpos($baseHref, '?') === false ? '?' : '&';

    return $baseHref . $separator . http_build_query($params);
}

function patient_portal_doctor_list_html(array $doctors, array $options = [])
{
    $sourceHref = $options['source_href'] ?? 'doctors.php';
    $resetHref = $options['reset_href'] ?? 'doctors.php';
    $emptyTitle = $options['empty_title'] ?? 'No doctors found';
    $emptyMessage = $options['empty_message'] ?? 'We could not find an approved doctor matching your search.';
    $showReset = $options['show_reset'] ?? true;
    $showRecommendationReason = $options['show_recommendation_reason'] ?? false;

    ob_start();

    if (count($doctors) === 0): ?>
        <div class="patient-empty-state">
            <div class="patient-empty-icon" aria-hidden="true"></div>
            <h3><?php echo patient_portal_h($emptyTitle); ?></h3>
            <p><?php echo patient_portal_h($emptyMessage); ?></p>
            <?php if ($showReset): ?>
                <a href="<?php echo patient_portal_h($resetHref); ?>" class="patient-btn primary">Show All Doctors</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="patient-doctor-list">
            <?php foreach ($doctors as $row): ?>
                <?php
                $docid = (int)($row["docid"] ?? 0);
                $name = (string)($row["docname"] ?? "");
                $email = (string)($row["docemail"] ?? "");
                $specialtyName = trim((string)($row["specialty_name"] ?? ""));
                $specialtyName = $specialtyName === "" ? "Not specified" : $specialtyName;
                $clinicName = trim((string)($row["clinic_name"] ?? ""));
                $clinicName = $clinicName === "" ? "Clinic / Hospital not provided" : $clinicName;
                $reason = trim((string)($row["recommendation_reason"] ?? ""));
                ?>
                <article class="patient-doctor-row">
                    <div class="patient-doctor-avatar" aria-hidden="true">
                        <?php echo patient_portal_h(patient_portal_initials($name)); ?>
                    </div>
                    <div class="patient-doctor-primary">
                        <strong><?php echo patient_portal_h($name); ?></strong>
                        <span><?php echo patient_portal_h($email); ?></span>
                        <?php if ($showRecommendationReason && $reason !== ""): ?>
                            <span class="patient-recommendation-reason"><?php echo patient_portal_h($reason); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="patient-doctor-secondary">
                        <span class="patient-specialty-badge"><?php echo patient_portal_h($specialtyName); ?></span>
                        <span><?php echo patient_portal_h($clinicName); ?></span>
                    </div>
                    <div class="patient-row-actions">
                        <a href="<?php echo patient_portal_h(patient_portal_url_with_query($sourceHref, ['action' => 'view', 'id' => $docid])); ?>" class="patient-action-link">View</a>
                        <a
                            href="<?php echo patient_portal_h(patient_portal_url_with_query($sourceHref, ['action' => 'session', 'id' => $docid, 'name' => $name])); ?>"
                            class="patient-action-link primary"
                        >Sessions</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif;

    return ob_get_clean();
}

function patient_portal_session_redirect_modal($returnHref, $doctorName)
{
    echo '<div id="popup1" class="overlay patient-modal-overlay">';
    echo '<div class="popup patient-modal-card">';
    echo '<a class="close" href="' . patient_portal_h($returnHref) . '">&times;</a>';
    echo '<div class="patient-modal-heading">';
    echo '<span class="patient-eyebrow">Doctor Sessions</span>';
    echo '<h2>View available sessions?</h2>';
    echo '<p>You will be redirected to sessions by ' . patient_portal_h($doctorName) . '.</p>';
    echo '</div>';
    echo '<form action="schedule.php" method="post" class="patient-modal-actions">';
    echo '<input type="hidden" name="search" value="' . patient_portal_h($doctorName) . '">';
    echo '<button type="submit" class="patient-btn primary">View Sessions</button>';
    echo '<a href="' . patient_portal_h($returnHref) . '" class="patient-btn secondary">Cancel</a>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
}

function patient_portal_booking_status(
    $scheduledate,
    $scheduletime,
    $durationMinutes = null,
    $endTime = '',
    $status = '',
    $completedAt = ''
)
{
    $statusDetails = doc_sathi_appointment_status_details(
        [
            'scheduledate' => $scheduledate,
            'scheduletime' => $scheduletime,
            'duration_minutes' => $durationMinutes,
            'end_time' => $endTime,
            'status' => $status,
            'completed_at' => $completedAt,
        ]
    );

    if (($statusDetails['code'] ?? '') === 'completed') {
        return ['label' => 'Ended by Doctor', 'class' => 'past'];
    }

    if (($statusDetails['code'] ?? '') === 'pending_completion') {
        return ['label' => 'Session Ended', 'class' => 'past'];
    }

    if (in_array($statusDetails['code'], ['today', 'in_progress'], true)) {
        return ['label' => $statusDetails['label'], 'class' => 'today'];
    }

    return ['label' => $statusDetails['label'], 'class' => 'upcoming'];
}
