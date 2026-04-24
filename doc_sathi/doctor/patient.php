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
    <title>My Patients</title>
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.45s;
        }
    </style>
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

function patient_page_sort_sql($sort)
{
    $sortMap = [
        'name_asc' => 'patient_summary.pname ASC, patient_summary.pid ASC',
        'name_desc' => 'patient_summary.pname DESC, patient_summary.pid DESC',
        'recent' => 'CASE WHEN last_appointment IS NULL THEN 1 ELSE 0 END, last_appointment DESC, patient_summary.pname ASC',
        'oldest' => 'CASE WHEN last_appointment IS NULL THEN 1 ELSE 0 END, last_appointment ASC, patient_summary.pname ASC',
    ];

    return $sortMap[$sort] ?? $sortMap['recent'];
}

date_default_timezone_set('Asia/Kathmandu');

$useremail = $_SESSION["user"];
$doctor = doc_sathi_get_doctor_by_email($database, $useremail);

if (!$doctor) {
    header("location: ../login.php");
    exit();
}

$userid = (int)$doctor["docid"];
$username = $doctor["docname"];
$today = date('Y-m-d');
$recentWindowStart = date('Y-m-d', strtotime('-30 days'));

$searchTerm = trim($_GET['search'] ?? '');
$scope = trim($_GET['scope'] ?? 'my');
$sort = trim($_GET['sort'] ?? 'recent');
$action = trim($_GET['action'] ?? '');
$recordId = (int)($_GET['id'] ?? 0);

$allowedScopes = ['my', 'all'];
$allowedSorts = ['name_asc', 'name_desc', 'recent', 'oldest'];

if (!in_array($scope, $allowedScopes, true)) {
    $scope = 'my';
}

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'recent';
}

$filterState = [
    'search' => $searchTerm,
    'scope' => $scope !== 'my' ? $scope : '',
    'sort' => $sort !== 'recent' ? $sort : '',
];
$baseReturnUrl = doctor_dashboard_build_url('patient.php', $filterState);
$filtersActive = $searchTerm !== '' || $scope !== 'my' || $sort !== 'recent';

$patientsSql = "SELECT *
                FROM (
                    SELECT p.pid,
                           p.pname,
                           p.pemail,
                           p.pdob,
                           p.pnum,
                           p.paddress,
                           COALESCE(SUM(CASE WHEN s.docid = ? THEN 1 ELSE 0 END), 0) AS appointment_count,
                           MAX(CASE WHEN s.docid = ? THEN s.scheduledate END) AS last_appointment,
                           COALESCE(SUM(CASE WHEN s.docid = ? AND s.scheduledate >= ? THEN 1 ELSE 0 END), 0) AS upcoming_count,
                           COALESCE(SUM(CASE WHEN s.docid = ? AND s.scheduledate BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) AS recent_appointments
                    FROM patient p
                    LEFT JOIN appointment a ON a.pid = p.pid
                    LEFT JOIN schedule s ON s.scheduleid = a.scheduleid
                    GROUP BY p.pid, p.pname, p.pemail, p.pdob, p.pnum, p.paddress
                ) AS patient_summary
                WHERE 1 = 1";
$patientTypes = "iiisiss";
$patientParams = [$userid, $userid, $userid, $today, $userid, $recentWindowStart, $today];

if ($searchTerm !== '') {
    $patientsSql .= " AND (patient_summary.pname LIKE ? OR patient_summary.pemail LIKE ? OR patient_summary.pnum LIKE ?)";
    $searchPattern = doc_sathi_search_pattern($searchTerm);
    $patientTypes .= "sss";
    $patientParams[] = $searchPattern;
    $patientParams[] = $searchPattern;
    $patientParams[] = $searchPattern;
}

if ($scope === 'my') {
    $patientsSql .= " AND patient_summary.appointment_count > 0";
}

$patientsSql .= " ORDER BY " . patient_page_sort_sql($sort);

$patientsStmt = doc_sathi_prepare($database, $patientsSql);
doctor_dashboard_bind_params($patientsStmt, $patientTypes, $patientParams);
doc_sathi_execute($patientsStmt);
$patientsResult = $patientsStmt->get_result();
$patients = [];

while ($row = $patientsResult->fetch_assoc()) {
    $patients[] = $row;
}

$totalPatients = count($patients);
$activePatients = 0;
$recentAppointments = 0;
$returningPatients = 0;

foreach ($patients as $patient) {
    if ((int)$patient['upcoming_count'] > 0) {
        $activePatients++;
    }

    if ((int)$patient['appointment_count'] > 1) {
        $returningPatients++;
    }

    $recentAppointments += (int)$patient['recent_appointments'];
}

$activePatient = null;
if ($recordId > 0 && $action === 'view') {
    $dialogSql = "SELECT p.pid,
                         p.pname,
                         p.pemail,
                         p.pdob,
                         p.pnum,
                         p.paddress,
                         COALESCE(SUM(CASE WHEN s.docid = ? THEN 1 ELSE 0 END), 0) AS appointment_count,
                         MAX(CASE WHEN s.docid = ? THEN s.scheduledate END) AS last_appointment,
                         COALESCE(SUM(CASE WHEN s.docid = ? AND s.scheduledate >= ? THEN 1 ELSE 0 END), 0) AS upcoming_count,
                         COALESCE(SUM(CASE WHEN s.docid = ? AND s.scheduledate BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) AS recent_appointments
                  FROM patient p
                  LEFT JOIN appointment a ON a.pid = p.pid
                  LEFT JOIN schedule s ON s.scheduleid = a.scheduleid
                  WHERE p.pid = ?
                  GROUP BY p.pid, p.pname, p.pemail, p.pdob, p.pnum, p.paddress
                  LIMIT 1";
    $dialogTypes = "iiisissi";
    $dialogParams = [$userid, $userid, $userid, $today, $userid, $recentWindowStart, $today, $recordId];
    $dialogStmt = doc_sathi_prepare($database, $dialogSql);
    doctor_dashboard_bind_params($dialogStmt, $dialogTypes, $dialogParams);
    doc_sathi_execute($dialogStmt);
    $activePatient = $dialogStmt->get_result()->fetch_assoc();
}
?>

<div class="container doctor-dashboard-layout">
    <?php doctor_dashboard_sidebar('patients', $username, $useremail); ?>

    <div class="dash-body doctor-dashboard-body">
        <main class="doctor-page-shell">
            <header class="doctor-page-header">
                <div class="doctor-page-header-main">
                    <a href="index.php" class="doctor-back-link">Back</a>
                    <div class="doctor-page-title-block">
                        <h1>My Patients</h1>
                        <p>View and manage your patient records</p>
                    </div>
                </div>
                <aside class="doctor-date-card">
                    <span>Today's Date</span>
                    <strong><?php echo doctor_dashboard_h(date('M d, Y')); ?></strong>
                </aside>
            </header>

            <section class="doctor-stats-grid doctor-page-stats" aria-label="Patient summary">
                <article class="doctor-stat-card">
                    <span>Total Patients</span>
                    <strong><?php echo $totalPatients; ?></strong>
                    <p>Patients shown for the current scope and filter set.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Active Patients</span>
                    <strong><?php echo $activePatients; ?></strong>
                    <p>Patients with at least one upcoming appointment with you.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Recent Appointments</span>
                    <strong><?php echo $recentAppointments; ?></strong>
                    <p>Appointments booked with you during the last 30 days.</p>
                </article>
                <article class="doctor-stat-card">
                    <span>Returning Patients</span>
                    <strong><?php echo $returningPatients; ?></strong>
                    <p>Patients who have booked with you more than once.</p>
                </article>
            </section>

            <section class="doctor-page-card">
                <div class="doctor-card-header">
                    <div>
                        <h2>Filter Patients</h2>
                        <p>Find patient records by contact details, adjust the scope, and sort the list for faster review.</p>
                    </div>
                </div>

                <form action="patient.php" method="get" class="doctor-toolbar patients-toolbar">
                    <div class="doctor-field">
                        <label for="patient-search">Search</label>
                        <input
                            id="patient-search"
                            type="search"
                            name="search"
                            value="<?php echo doctor_dashboard_h($searchTerm); ?>"
                            class="doctor-input"
                            placeholder="Search by name, email, or phone"
                        >
                    </div>

                    <div class="doctor-field">
                        <label for="patient-scope">Patient Scope</label>
                        <select id="patient-scope" name="scope" class="doctor-select">
                            <option value="my" <?php echo $scope === 'my' ? 'selected' : ''; ?>>My Patients Only</option>
                            <option value="all" <?php echo $scope === 'all' ? 'selected' : ''; ?>>All Registered Patients</option>
                        </select>
                    </div>

                    <div class="doctor-field">
                        <label for="patient-sort">Sort</label>
                        <select id="patient-sort" name="sort" class="doctor-select">
                            <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Recent Activity</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest Activity</option>
                            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                        </select>
                    </div>

                    <div class="doctor-toolbar-actions">
                        <button type="submit" class="doctor-btn">Filter</button>
                    </div>

                    <div class="doctor-toolbar-actions">
                        <a href="patient.php" class="doctor-btn secondary">Reset</a>
                    </div>
                </form>
            </section>

            <section class="doctor-page-card compact">
                <div class="doctor-table-card-header">
                    <div class="doctor-card-header">
                        <div>
                            <h2>Patient Directory</h2>
                            <p><?php echo $totalPatients; ?> patient<?php echo $totalPatients === 1 ? '' : 's'; ?> shown.</p>
                        </div>
                    </div>
                </div>

                <?php if (empty($patients)): ?>
                    <div class="doctor-empty-state">
                        <div class="doctor-empty-icon">P</div>
                        <h3>No patients found</h3>
                        <p>
                            <?php echo $filtersActive
                                ? 'No patient records matched the current search, scope, or sort settings.'
                                : 'Patients who book your sessions will appear here once records are available.'; ?>
                        </p>
                        <?php if ($filtersActive): ?>
                            <a href="patient.php" class="doctor-btn secondary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="doctor-table-wrap">
                        <table class="doctor-data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Date of Birth</th>
                                    <th>Last Appointment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td>
                                            <div class="doctor-primary-cell">
                                                <div class="doctor-avatar"><?php echo doctor_dashboard_h(doctor_dashboard_initials($patient['pname'])); ?></div>
                                                <div class="doctor-primary-text">
                                                    <strong><?php echo doctor_dashboard_h($patient['pname']); ?></strong>
                                                    <span>Patient ID P-<?php echo (int)$patient['pid']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo doctor_dashboard_h($patient['pnum']); ?></td>
                                        <td><?php echo doctor_dashboard_h($patient['pemail']); ?></td>
                                        <td><?php echo doctor_dashboard_h(doctor_dashboard_format_date($patient['pdob'])); ?></td>
                                        <td>
                                            <?php if (!empty($patient['last_appointment'])): ?>
                                                <div class="doctor-metric">
                                                    <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($patient['last_appointment'])); ?></strong>
                                                    <span><?php echo (int)$patient['appointment_count']; ?> appointment<?php echo (int)$patient['appointment_count'] === 1 ? '' : 's'; ?> with you</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="doctor-metric">
                                                    <strong>No appointments yet</strong>
                                                    <span>Not linked to your sessions</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="doctor-table-actions">
                                                <a
                                                    href="<?php echo doctor_dashboard_h(doctor_dashboard_build_url('patient.php', $filterState, ['action' => 'view', 'id' => (int)$patient['pid']])); ?>"
                                                    class="doctor-btn small ghost"
                                                >
                                                    View Profile
                                                </a>
                                                <?php if ((int)$patient['appointment_count'] > 0): ?>
                                                    <a
                                                        href="<?php echo doctor_dashboard_h('appointment.php?search=' . urlencode($patient['pname']) . '&patient_id=' . (int)$patient['pid']); ?>"
                                                        class="doctor-btn small secondary"
                                                    >
                                                        Appointment History
                                                    </a>
                                                <?php else: ?>
                                                    <span class="doctor-btn small disabled">No History</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<?php if ($action === 'view' && $activePatient): ?>
    <div id="popup1" class="overlay">
        <div class="popup doctor-dialog">
            <a class="close" href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>">&times;</a>
            <div class="doctor-dialog-header">
                <h2>Patient Profile</h2>
                <p>Review patient contact information, activity with your sessions, and the latest appointment context.</p>
            </div>
            <div class="doctor-dialog-body">
                <div class="doctor-dialog-grid">
                    <div class="doctor-dialog-item">
                        <span>Patient Name</span>
                        <strong><?php echo doctor_dashboard_h($activePatient['pname']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Patient ID</span>
                        <strong>P-<?php echo (int)$activePatient['pid']; ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Email</span>
                        <strong><?php echo doctor_dashboard_h($activePatient['pemail']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Phone</span>
                        <strong><?php echo doctor_dashboard_h($activePatient['pnum']); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Date of Birth</span>
                        <strong><?php echo doctor_dashboard_h(doctor_dashboard_format_date($activePatient['pdob'])); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Appointments With You</span>
                        <strong><?php echo (int)$activePatient['appointment_count']; ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Last Appointment</span>
                        <strong><?php echo doctor_dashboard_h(!empty($activePatient['last_appointment']) ? doctor_dashboard_format_date($activePatient['last_appointment']) : 'No appointments yet'); ?></strong>
                    </div>
                    <div class="doctor-dialog-item">
                        <span>Upcoming Appointments</span>
                        <strong><?php echo (int)$activePatient['upcoming_count']; ?></strong>
                    </div>
                    <div class="doctor-dialog-item full">
                        <span>Address</span>
                        <strong><?php echo doctor_dashboard_h($activePatient['paddress'] ?: 'Not available'); ?></strong>
                    </div>
                </div>

                <div class="doctor-dialog-actions">
                    <?php if ((int)$activePatient['appointment_count'] > 0): ?>
                        <a
                            href="<?php echo doctor_dashboard_h('appointment.php?search=' . urlencode($activePatient['pname']) . '&patient_id=' . (int)$activePatient['pid']); ?>"
                            class="doctor-btn secondary"
                        >
                            Appointment History
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo doctor_dashboard_h($baseReturnUrl); ?>" class="doctor-btn">Close</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
