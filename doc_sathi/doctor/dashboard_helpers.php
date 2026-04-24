<?php

function doctor_dashboard_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function doctor_dashboard_bind_params($stmt, $types, array &$params)
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindValues = [$types];

    foreach ($params as $index => &$value) {
        $bindValues[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

function doctor_dashboard_format_date($value, $format = 'M d, Y', $fallback = 'Not available')
{
    $value = trim((string)$value);

    if ($value === '' || $value === '0000-00-00') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date($format, $timestamp);
}

function doctor_dashboard_format_time($value, $format = 'h:i A', $fallback = 'Not available')
{
    $value = trim((string)$value);

    if ($value === '' || $value === '00:00:00') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }

    return date($format, $timestamp);
}

function doctor_dashboard_format_datetime($date, $time, $fallback = 'Not scheduled')
{
    $date = trim((string)$date);
    $time = trim((string)$time);

    if ($date === '' || $date === '0000-00-00') {
        return $fallback;
    }

    $combined = trim($date . ' ' . $time);
    $timestamp = strtotime($combined);

    if ($timestamp === false) {
        return doctor_dashboard_format_date($date);
    }

    return date('M d, Y', $timestamp) . ' at ' . date('h:i A', $timestamp);
}

function doctor_dashboard_initials($name)
{
    $cleanName = trim((string)$name);

    if ($cleanName === '') {
        return 'NA';
    }

    $parts = preg_split('/\s+/', $cleanName) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) === 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'NA';
}

function doctor_dashboard_build_url($page, array $currentParams = [], array $overrides = [])
{
    $params = array_merge($currentParams, $overrides);

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    return $page . (!empty($params) ? '?' . http_build_query($params) : '');
}

function doctor_dashboard_sidebar($activePage, $username, $useremail)
{
    $items = [
        [
            'key' => 'dashboard',
            'href' => 'index.php',
            'label' => 'Dashboard',
            'icon' => 'menu-icon-dashbord',
            'active_icon' => 'menu-icon-dashbord-active',
        ],
        [
            'key' => 'appointments',
            'href' => 'appointment.php',
            'label' => 'My Appointments',
            'icon' => 'menu-icon-appoinment',
            'active_icon' => 'menu-icon-appoinment-active',
        ],
        [
            'key' => 'sessions',
            'href' => 'schedule.php',
            'label' => 'My Sessions',
            'icon' => 'menu-icon-session',
            'active_icon' => 'menu-icon-session-active',
        ],
        [
            'key' => 'verification',
            'href' => 'verification.php',
            'label' => 'Verification',
            'icon' => 'menu-icon-doctor',
            'active_icon' => 'menu-icon-doctor-active',
        ],
        [
            'key' => 'patients',
            'href' => 'patient.php',
            'label' => 'My Patients',
            'icon' => 'menu-icon-patient',
            'active_icon' => 'menu-icon-patient-active',
        ],
        [
            'key' => 'account',
            'href' => 'account.php',
            'label' => 'Account',
            'icon' => 'menu-icon-account',
            'active_icon' => 'menu-icon-account-active',
        ],
    ];
    ?>
    <div class="menu doctor-menu">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px">
                                <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                            </td>
                            <td style="padding:0;margin:0;">
                                <p class="doctor-profile-name" title="<?php echo doctor_dashboard_h($username); ?>">
                                    <?php echo doctor_dashboard_h($username); ?>
                                </p>
                                <p class="doctor-profile-email" title="<?php echo doctor_dashboard_h($useremail); ?>">
                                    <?php echo doctor_dashboard_h($useremail); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php?role=d">
                                    <input type="button" value="Log out" class="logout-btn btn-primary-soft btn">
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php foreach ($items as $item): ?>
                <?php
                $isActive = $activePage === $item['key'];
                $classes = trim('menu-btn ' . $item['icon'] . ($isActive ? ' menu-active ' . $item['active_icon'] : ''));
                $linkClass = 'non-style-link-menu' . ($isActive ? ' non-style-link-menu-active' : '');
                ?>
                <tr class="menu-row">
                    <td class="<?php echo doctor_dashboard_h($classes); ?>">
                        <a href="<?php echo doctor_dashboard_h($item['href']); ?>" class="<?php echo doctor_dashboard_h($linkClass); ?>">
                            <div>
                                <p class="menu-text"><?php echo doctor_dashboard_h($item['label']); ?></p>
                            </div>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}
