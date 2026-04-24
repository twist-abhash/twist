<?php ob_start();
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

$userStmt = $database->prepare("select * from patient where pemail=?");
$userStmt->bind_param("s", $useremail);
$userStmt->execute();
$userrow = $userStmt->get_result();
$userfetch = $userrow->fetch_assoc();

if (!$userfetch) {
    header("location: ../logout.php?role=p");
    exit();
}

$username = $userfetch["pname"];

date_default_timezone_set("Asia/Kathmandu");
$today = date("Y-m-d");
$search = trim($_POST["search"] ?? "");

$doctorOptions = $database->query(
    "select doctor.docname,
            doctor.docemail,
            doctor.clinic_name,
            specialties.sname as specialty_name
     from doctor
     left join specialties on doctor.specialties=specialties.id
     where doctor.verification_status='approved'
     order by doctor.docname"
);
$specialtyOptions = $database->query("select sname from specialties order by sname");

$searchResults = [];
if ($search !== "") {
    $searchResults = doc_sathi_search_doctors($database, $search, 100);
} else {
    $specialtiesResult = $database->query("select * from specialties order by sname");
}

ob_start();
?>
<form action="" method="post" class="patient-search-form">
    <input
        type="search"
        name="search"
        class="input-text patient-search-input"
        placeholder="Search doctor name, specialty, or clinic/hospital"
        list="specialty-search-options"
        value="<?php echo patient_portal_h($search); ?>"
    >
    <datalist id="specialty-search-options">
        <?php if ($doctorOptions): ?>
            <?php while ($option = $doctorOptions->fetch_assoc()): ?>
                <option value="<?php echo patient_portal_h($option["docname"]); ?>"></option>
                <option value="<?php echo patient_portal_h($option["docemail"]); ?>"></option>
                <?php if (trim((string)($option["specialty_name"] ?? "")) !== ""): ?>
                    <option value="<?php echo patient_portal_h($option["specialty_name"]); ?>"></option>
                <?php endif; ?>
                <?php if (trim((string)($option["clinic_name"] ?? "")) !== ""): ?>
                    <option value="<?php echo patient_portal_h($option["clinic_name"]); ?>"></option>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php endif; ?>
        <?php if ($specialtyOptions): ?>
            <?php while ($option = $specialtyOptions->fetch_assoc()): ?>
                <option value="<?php echo patient_portal_h($option["sname"]); ?>"></option>
            <?php endwhile; ?>
        <?php endif; ?>
    </datalist>
    <button type="submit" class="patient-btn primary">Search</button>
</form>
<?php
$searchHtml = ob_get_clean();
?>

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

    <title>Specialties</title>
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <div class="container patient-dashboard-layout">
        <?php patient_portal_sidebar($username, $useremail, "specialties"); ?>

        <main class="dash-body patient-dashboard-body">
            <div class="patient-dashboard-shell">
                <?php
                patient_portal_page_header([
                    "eyebrow" => "Care Categories",
                    "title" => "Specialties",
                    "subtitle" => "Explore specialties and the approved doctors with upcoming sessions.",
                    "today" => $today,
                    "search_html" => $searchHtml,
                ]);
                ?>

                <section class="patient-panel" aria-labelledby="specialties-title">
                    <div class="patient-panel-header">
                        <div>
                            <span class="patient-eyebrow"><?php echo $search !== "" ? "Search" : "Browse"; ?></span>
                            <h2 id="specialties-title">
                                <?php echo $search !== "" ? "Matching Doctors" : "All Specialties"; ?>
                            </h2>
                            <p>
                                <?php if ($search !== ""): ?>
                                    Results for "<?php echo patient_portal_h($search); ?>".
                                <?php else: ?>
                                    Open a specialty to see doctors who have upcoming sessions.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($search !== ""): ?>
                            <span class="patient-count-pill"><?php echo count($searchResults); ?> doctors</span>
                        <?php elseif (isset($specialtiesResult)): ?>
                            <span class="patient-count-pill"><?php echo (int)$specialtiesResult->num_rows; ?> specialties</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($search !== ""): ?>
                        <?php
                        echo patient_portal_doctor_list_html($searchResults, [
                            "source_href" => "department.php",
                            "reset_href" => "department.php",
                            "empty_message" => "No approved doctors matched your search. Try a specialty, doctor name, or clinic/hospital.",
                        ]);
                        ?>
                    <?php else: ?>
                        <div class="patient-specialty-list">
                            <?php while ($specialty = $specialtiesResult->fetch_assoc()): ?>
                                <?php
                                $specialtyId = (int)$specialty["id"];
                                $specialtyName = $specialty["sname"];
                                $cardId = "specialty-" . $specialtyId;

                                $doctorStmt = $database->prepare(
                                    "select distinct d.*
                                     from doctor d
                                     inner join schedule s on d.docid = s.docid
                                     where d.specialties = ?
                                       and d.verification_status = 'approved'
                                       and s.scheduledate >= ?
                                     order by d.docname"
                                );
                                $doctorStmt->bind_param("is", $specialtyId, $today);
                                $doctorStmt->execute();
                                $doctorsResult = $doctorStmt->get_result();
                                ?>
                                <article class="patient-specialty-card" id="<?php echo patient_portal_h($cardId); ?>">
                                    <button
                                        type="button"
                                        class="patient-specialty-toggle"
                                        onclick="toggleDoctors('<?php echo patient_portal_h($cardId); ?>', this)"
                                        aria-expanded="false"
                                        aria-controls="<?php echo patient_portal_h($cardId); ?>-content"
                                    >
                                        <span>
                                            <strong><?php echo patient_portal_h($specialtyName); ?></strong>
                                            <span><?php echo (int)$doctorsResult->num_rows; ?> doctors with upcoming sessions</span>
                                        </span>
                                        <span class="patient-specialty-chevron" aria-hidden="true">v</span>
                                    </button>
                                    <div class="patient-specialty-content" id="<?php echo patient_portal_h($cardId); ?>-content">
                                        <?php if ($doctorsResult->num_rows == 0): ?>
                                            <p class="patient-inline-empty">No doctors have upcoming sessions in this specialty right now.</p>
                                        <?php else: ?>
                                            <?php while ($doctor = $doctorsResult->fetch_assoc()): ?>
                                                <?php
                                                $docid = (int)$doctor["docid"];
                                                $docname = $doctor["docname"];
                                                $clinicName = trim((string)($doctor["clinic_name"] ?? ""));
                                                $clinicName = $clinicName === "" ? "Clinic / Hospital not provided" : $clinicName;
                                                ?>
                                                <article class="patient-doctor-row">
                                                    <div class="patient-doctor-avatar" aria-hidden="true">
                                                        <?php echo patient_portal_h(patient_portal_initials($docname)); ?>
                                                    </div>
                                                    <div class="patient-doctor-primary">
                                                        <strong><?php echo patient_portal_h($docname); ?></strong>
                                                        <span><?php echo patient_portal_h($doctor["docemail"]); ?></span>
                                                        <span><?php echo patient_portal_h($clinicName); ?></span>
                                                    </div>
                                                    <div class="patient-row-actions">
                                                        <a href="?action=view&id=<?php echo $docid; ?>" class="patient-action-link">View</a>
                                                        <a
                                                            href="?action=session&id=<?php echo $docid; ?>&name=<?php echo urlencode($docname); ?>"
                                                            class="patient-action-link primary"
                                                        >Sessions</a>
                                                    </div>
                                                </article>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <?php
    if ($_GET) {
        $id = (int)($_GET["id"] ?? 0);
        $action = $_GET["action"] ?? "";

        if ($action === "drop") {
            $nameget = $_GET["name"] ?? "";
            echo '
            <div id="popup1" class="overlay patient-modal-overlay">
                <div class="popup patient-modal-card">
                    <a class="close" href="department.php">&times;</a>
                    <div class="patient-modal-heading">
                        <span class="patient-eyebrow">Confirm Action</span>
                        <h2>Are you sure?</h2>
                        <p>You want to delete this record (' . patient_portal_h(substr($nameget, 0, 40)) . ').</p>
                    </div>
                    <div class="patient-modal-actions">
                        <a href="delete-doctor.php?id=' . $id . '" class="patient-btn primary">Yes</a>
                        <a href="department.php" class="patient-btn secondary">No</a>
                    </div>
                </div>
            </div>';
        } elseif ($action === "view") {
            $doctor = doc_sathi_get_doctor_by_id($database, $id, true);
            if (!$doctor) {
                header("location: department.php");
                exit();
            }

            patient_portal_doctor_details_modal("department.php", $doctor);
        } elseif ($action === "session") {
            patient_portal_session_redirect_modal("department.php", $_GET["name"] ?? "");
        }
    }
    ?>

    <script>
        function toggleDoctors(id, trigger) {
            const card = document.getElementById(id);
            if (!card) {
                return;
            }

            const isOpen = card.classList.toggle("is-open");
            if (trigger) {
                trigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
            }
        }
    </script>
</body>
</html>
