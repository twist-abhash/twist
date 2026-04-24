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

    <title>Doctors</title>
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
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

    $doctorOptionsStmt = $database->prepare(
        "select doctor.docname,
                doctor.docemail,
                doctor.clinic_name,
                specialties.sname as specialty_name
         from doctor
         left join specialties on doctor.specialties=specialties.id
         where doctor.verification_status='approved'
         order by doctor.docname"
    );
    $doctorOptionsStmt->execute();
    $doctorOptions = $doctorOptionsStmt->get_result();
    $doctors = doc_sathi_search_doctors($database, $search, 100);

    ob_start();
    ?>
    <form action="" method="post" class="patient-search-form" id="doctor-live-search-form" data-live-search-url="search-doctors.php">
        <input
            type="search"
            name="search"
            id="doctor-live-search"
            class="input-text patient-search-input"
            placeholder="Search doctor name, specialty, or clinic/hospital"
            list="doctors"
            value="<?php echo patient_portal_h($search); ?>"
            autocomplete="off"
        >
        <datalist id="doctors">
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
        </datalist>
        <button type="submit" class="patient-btn primary">Search</button>
        <span class="patient-live-search-status" id="doctor-live-search-status" aria-live="polite"></span>
    </form>
    <?php
    $searchHtml = ob_get_clean();
    ?>

    <div class="container patient-dashboard-layout">
        <?php patient_portal_sidebar($username, $useremail, "doctors"); ?>

        <main class="dash-body patient-dashboard-body">
            <div class="patient-dashboard-shell">
                <?php
                patient_portal_page_header([
                    "eyebrow" => "Find Care",
                    "title" => "Doctors",
                    "subtitle" => "Search approved doctors by name, specialty, or clinic/hospital.",
                    "today" => $today,
                    "search_html" => $searchHtml,
                ]);
                ?>

                <section class="patient-panel" aria-labelledby="doctors-title">
                    <div class="patient-panel-header">
                        <div>
                            <span class="patient-eyebrow">Directory</span>
                            <h2 id="doctors-title">
                                <?php echo $search !== "" ? "Matching Doctors" : "Available Doctors"; ?>
                            </h2>
                            <p id="doctors-description">
                                <?php if ($search !== ""): ?>
                                    Results for "<?php echo patient_portal_h($search); ?>".
                                <?php else: ?>
                                    Browse approved doctors and view their available sessions.
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="patient-count-pill" id="doctor-count"><?php echo count($doctors); ?> doctors</span>
                    </div>

                    <div id="doctor-results">
                        <?php
                        echo patient_portal_doctor_list_html($doctors, [
                            "source_href" => "doctors.php",
                            "reset_href" => "doctors.php",
                            "empty_message" => "We could not find an approved doctor matching your search. Try another name, specialty, or clinic/hospital.",
                        ]);
                        ?>
                    </div>
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
                    <a class="close" href="doctors.php">&times;</a>
                    <div class="patient-modal-heading">
                        <span class="patient-eyebrow">Confirm Action</span>
                        <h2>Are you sure?</h2>
                        <p>You want to delete this record (' . patient_portal_h(substr($nameget, 0, 40)) . ').</p>
                    </div>
                    <div class="patient-modal-actions">
                        <a href="delete-doctor.php?id=' . $id . '" class="patient-btn primary">Yes</a>
                        <a href="doctors.php" class="patient-btn secondary">No</a>
                    </div>
                </div>
            </div>';
        } elseif ($action === "view") {
            $doctor = doc_sathi_get_doctor_by_id($database, $id, true);
            if (!$doctor) {
                header("location: doctors.php");
                exit();
            }

            patient_portal_doctor_details_modal("doctors.php", $doctor);
        } elseif ($action === "session") {
            patient_portal_session_redirect_modal("doctors.php", $_GET["name"] ?? "");
        }
    }
    ?>
    <script>
        (function() {
            const form = document.getElementById('doctor-live-search-form');
            const input = document.getElementById('doctor-live-search');
            const results = document.getElementById('doctor-results');
            const count = document.getElementById('doctor-count');
            const title = document.getElementById('doctors-title');
            const description = document.getElementById('doctors-description');
            const status = document.getElementById('doctor-live-search-status');

            if (!form || !input || !results || !window.fetch) {
                return;
            }

            let timer = null;
            let controller = null;
            let lastQuery = input.value.trim();
            const endpoint = form.dataset.liveSearchUrl || 'search-doctors.php';

            function setStatus(message) {
                if (status) {
                    status.textContent = message;
                }
            }

            function runSearch() {
                const query = input.value.trim();

                if (query === lastQuery) {
                    return;
                }

                lastQuery = query;

                if (controller) {
                    controller.abort();
                }

                controller = window.AbortController ? new AbortController() : null;
                form.classList.add('is-loading');
                setStatus('Searching...');

                fetch(endpoint + '?q=' + encodeURIComponent(query), {
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                    signal: controller ? controller.signal : undefined
                })
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Search failed');
                        }

                        return response.json();
                    })
                    .then(function(payload) {
                        if (!payload || !payload.ok) {
                            throw new Error('Search failed');
                        }

                        results.innerHTML = payload.html || '';
                        if (count) {
                            count.textContent = String(payload.count || 0) + ' doctors';
                        }
                        if (title && payload.title) {
                            title.textContent = payload.title;
                        }
                        if (description && payload.description) {
                            description.textContent = payload.description;
                        }
                        setStatus(payload.count === 0 ? 'No doctors found' : '');
                    })
                    .catch(function(error) {
                        if (error.name !== 'AbortError') {
                            setStatus('Search is unavailable. Submit the form to search.');
                        }
                    })
                    .finally(function() {
                        form.classList.remove('is-loading');
                    });
            }

            input.addEventListener('input', function() {
                window.clearTimeout(timer);
                timer = window.setTimeout(runSearch, 220);
            });
        })();
    </script>
</body>
</html>
