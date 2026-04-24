<?php
    // Database Connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "doc_sathi";

mysqli_report(MYSQLI_REPORT_OFF);

// Create a single database connection with a short timeout so a stopped or
// unhealthy local MySQL server does not make every page hang for a long time.
$database = mysqli_init();

if (!$database) {
    die("Connection failed: Could not initialize MySQL.");
}

$database->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
    $database->options(MYSQLI_OPT_READ_TIMEOUT, 3);
}

// Check connection
if (!@$database->real_connect($servername, $username, $password, $dbname)) {
    die("Connection failed: " . $database->connect_error);
}

$database->set_charset("utf8mb4");

if (!function_exists('doc_sathi_runtime_column_exists')) {
    function doc_sathi_runtime_column_exists($database, $table, $column)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $column = trim((string)$column);

        if ($table === '' || $column === '') {
            return false;
        }

        $sql = "SHOW COLUMNS FROM `" . $table . "` LIKE '" . $database->real_escape_string($column) . "'";
        $result = @$database->query($sql);

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('doc_sathi_runtime_index_exists')) {
    function doc_sathi_runtime_index_exists($database, $table, $indexName)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $indexName = trim((string)$indexName);

        if ($table === '' || $indexName === '') {
            return false;
        }

        $sql = "SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . $database->real_escape_string($indexName) . "'";
        $result = @$database->query($sql);

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('doc_sathi_runtime_unique_index_exists_for_column')) {
    function doc_sathi_runtime_unique_index_exists_for_column($database, $table, $column)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $column = trim((string)$column);

        if ($table === '' || $column === '') {
            return false;
        }

        $sql = "SHOW INDEX FROM `" . $table . "` WHERE Column_name = '" . $database->real_escape_string($column) . "' AND Non_unique = 0";
        $result = @$database->query($sql);

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('doc_sathi_runtime_nullify_empty_strings')) {
    function doc_sathi_runtime_nullify_empty_strings($database, $table, $column)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column);

        if ($table === '' || $column === '') {
            return false;
        }

        return @ $database->query(
            "UPDATE `" . $table . "`
             SET `" . $column . "` = NULL
             WHERE `" . $column . "` IS NOT NULL
               AND TRIM(`" . $column . "`) = ''"
        ) !== false;
    }
}

if (!function_exists('doc_sathi_runtime_infer_gender_from_name')) {
    function doc_sathi_runtime_infer_gender_from_name($name)
    {
        if (function_exists('doc_sathi_infer_gender_from_name')) {
            return doc_sathi_infer_gender_from_name($name);
        }

        $name = strtolower(trim((string)$name));
        $name = preg_replace('/\b(dr|mr|mrs|ms|miss|prof)\.?\b/', ' ', $name);
        $name = preg_replace('/[^a-z ]+/', ' ', $name);
        $parts = preg_split('/\s+/', trim($name));
        $firstName = $parts[0] ?? '';

        $maleNames = [
            'aakash', 'aarav', 'aayush', 'abhas', 'amit', 'anil', 'awas', 'bikash',
            'bikram', 'binod', 'bishal', 'deepak', 'hari', 'kiran', 'krishna', 'manoj',
            'nabin', 'narayan', 'prabin', 'prakash', 'rajan', 'rajesh', 'ram', 'ramesh',
            'roshan', 'sagar', 'santosh', 'shyam', 'sudip', 'sunil', 'sushil',
        ];
        $femaleNames = [
            'anjali', 'anita', 'asmita', 'gita', 'kabita', 'kamala', 'laxmi', 'manisha',
            'menuka', 'nirmala', 'pooja', 'pratiksha', 'puja', 'rekha', 'rita', 'rojina',
            'sabina', 'sabita', 'sangita', 'sarita', 'sarmila', 'sita', 'sunita', 'sushma',
            'susmita',
        ];

        if (in_array($firstName, $maleNames, true)) {
            return 'male';
        }

        if (in_array($firstName, $femaleNames, true)) {
            return 'female';
        }

        return 'other';
    }
}

if (!function_exists('doc_sathi_runtime_backfill_gender')) {
    function doc_sathi_runtime_backfill_gender($database, $table, $idColumn, $nameColumn, $genderColumn)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $idColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string)$idColumn);
        $nameColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string)$nameColumn);
        $genderColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string)$genderColumn);

        if ($table === '' || $idColumn === '' || $nameColumn === '' || $genderColumn === '') {
            return false;
        }

        $result = @ $database->query(
            "SELECT `" . $idColumn . "`, `" . $nameColumn . "`
             FROM `" . $table . "`
             WHERE `" . $genderColumn . "` IS NULL
                OR TRIM(`" . $genderColumn . "`) = ''"
        );

        if (!($result instanceof mysqli_result)) {
            return false;
        }

        $stmt = @ $database->prepare(
            "UPDATE `" . $table . "`
             SET `" . $genderColumn . "` = ?
             WHERE `" . $idColumn . "` = ?
               AND (`" . $genderColumn . "` IS NULL OR TRIM(`" . $genderColumn . "`) = '')"
        );

        if (!$stmt) {
            return false;
        }

        while ($row = $result->fetch_assoc()) {
            $gender = doc_sathi_runtime_infer_gender_from_name($row[$nameColumn] ?? '');
            $id = (int)($row[$idColumn] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $stmt->bind_param("si", $gender, $id);
            @ $stmt->execute();
        }

        return true;
    }
}

if (!function_exists('doc_sathi_runtime_has_duplicate_values')) {
    function doc_sathi_runtime_has_duplicate_values($database, $table, $column, $ignoreEmptyStrings = false)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column);

        if ($table === '' || $column === '') {
            return true;
        }

        $whereSql = "`" . $column . "` IS NOT NULL";

        if ($ignoreEmptyStrings) {
            $whereSql .= " AND TRIM(`" . $column . "`) <> ''";
        }

        $result = @ $database->query(
            "SELECT 1
             FROM `" . $table . "`
             WHERE " . $whereSql . "
             GROUP BY `" . $column . "`
             HAVING COUNT(*) > 1
             LIMIT 1"
        );

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('doc_sathi_runtime_add_unique_index_if_clean')) {
    function doc_sathi_runtime_add_unique_index_if_clean($database, $table, $indexName, $column, $ignoreEmptyStrings = false)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
        $indexName = preg_replace('/[^A-Za-z0-9_]/', '', (string)$indexName);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column);

        if ($table === '' || $indexName === '' || $column === '') {
            return false;
        }

        if (
            doc_sathi_runtime_index_exists($database, $table, $indexName) ||
            doc_sathi_runtime_unique_index_exists_for_column($database, $table, $column)
        ) {
            return true;
        }

        if (doc_sathi_runtime_has_duplicate_values($database, $table, $column, $ignoreEmptyStrings)) {
            return false;
        }

        return @ $database->query(
            "ALTER TABLE `" . $table . "`
             ADD UNIQUE INDEX `" . $indexName . "` (`" . $column . "`)"
        ) !== false;
    }
}

if (!function_exists('doc_sathi_runtime_upgrade_schema')) {
    function doc_sathi_runtime_upgrade_schema($database)
    {
        static $hasRun = false;

        if ($hasRun) {
            return true;
        }

        $hasRun = true;

        if (!($database instanceof mysqli)) {
            return false;
        }

        $defaultDuration = 30;

        if (!doc_sathi_runtime_column_exists($database, 'patient', 'gender')) {
            @ $database->query(
                "ALTER TABLE `patient`
                 ADD COLUMN `gender` ENUM('male','female','other') DEFAULT NULL
                 AFTER `pdob`"
            );
        }

        if (!doc_sathi_runtime_column_exists($database, 'doctor', 'gender')) {
            @ $database->query(
                "ALTER TABLE `doctor`
                 ADD COLUMN `gender` ENUM('male','female','other') DEFAULT NULL
                 AFTER `docdob`"
            );
        }

        @ $database->query(
            "CREATE TABLE IF NOT EXISTS `doctor_documents` (
              `doctor_document_id` INT(11) NOT NULL AUTO_INCREMENT,
              `docid` INT(11) NOT NULL,
              `document_category` ENUM('cv','education','experience','license') NOT NULL,
              `original_name` VARCHAR(255) NOT NULL,
              `file_path` VARCHAR(255) NOT NULL,
              `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`doctor_document_id`),
              KEY `idx_doctor_documents_doctor_category` (`docid`, `document_category`),
              CONSTRAINT `fk_doctor_documents_doctor`
                FOREIGN KEY (`docid`) REFERENCES `doctor` (`docid`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        if (!doc_sathi_runtime_column_exists($database, 'schedule', 'duration_minutes')) {
            @ $database->query(
                "ALTER TABLE `schedule`
                 ADD COLUMN `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 30
                 AFTER `scheduletime`"
            );
        }

        if (!doc_sathi_runtime_column_exists($database, 'schedule', 'end_time')) {
            @ $database->query(
                "ALTER TABLE `schedule`
                 ADD COLUMN `end_time` TIME DEFAULT NULL
                 AFTER `duration_minutes`"
            );
        }

        if (!doc_sathi_runtime_index_exists($database, 'schedule', 'idx_schedule_doctor_window')) {
            @ $database->query(
                "ALTER TABLE `schedule`
                 ADD INDEX `idx_schedule_doctor_window` (`docid`, `scheduledate`, `scheduletime`, `end_time`)"
            );
        }

        if (!doc_sathi_runtime_column_exists($database, 'appointment', 'status')) {
            @ $database->query(
                "ALTER TABLE `appointment`
                 ADD COLUMN `status` ENUM('confirmed','completed','cancelled') NOT NULL DEFAULT 'confirmed'
                 AFTER `appodate`"
            );
        }

        if (!doc_sathi_runtime_column_exists($database, 'appointment', 'completed_at')) {
            @ $database->query(
                "ALTER TABLE `appointment`
                 ADD COLUMN `completed_at` DATETIME DEFAULT NULL
                 AFTER `status`"
            );
        }

        if (!doc_sathi_runtime_column_exists($database, 'appointment', 'completed_by')) {
            @ $database->query(
                "ALTER TABLE `appointment`
                 ADD COLUMN `completed_by` INT(11) DEFAULT NULL
                 AFTER `completed_at`"
            );
        }

        if (!doc_sathi_runtime_column_exists($database, 'appointment', 'checkup_result')) {
            @ $database->query(
                "ALTER TABLE `appointment`
                 ADD COLUMN `checkup_result` TEXT DEFAULT NULL
                 AFTER `completed_by`"
            );
        }

        if (!doc_sathi_runtime_index_exists($database, 'appointment', 'idx_appointment_status')) {
            @ $database->query(
                "ALTER TABLE `appointment`
                 ADD INDEX `idx_appointment_status` (`status`)"
            );
        }

        if (!doc_sathi_runtime_index_exists($database, 'appointment', 'idx_appointment_schedule_status')) {
            @ $database->query(
                "ALTER TABLE `appointment`
                 ADD INDEX `idx_appointment_schedule_status` (`scheduleid`, `status`)"
            );
        }

        if (doc_sathi_runtime_column_exists($database, 'patient', 'pemail')) {
            doc_sathi_runtime_nullify_empty_strings($database, 'patient', 'pemail');
            doc_sathi_runtime_add_unique_index_if_clean($database, 'patient', 'unique_patient_email', 'pemail', true);
        }

        if (doc_sathi_runtime_column_exists($database, 'patient', 'pnum')) {
            doc_sathi_runtime_nullify_empty_strings($database, 'patient', 'pnum');
            doc_sathi_runtime_add_unique_index_if_clean($database, 'patient', 'unique_patient_phone', 'pnum', true);
        }

        if (doc_sathi_runtime_column_exists($database, 'patient', 'gender')) {
            doc_sathi_runtime_backfill_gender($database, 'patient', 'pid', 'pname', 'gender');
        }

        if (doc_sathi_runtime_column_exists($database, 'doctor', 'docemail')) {
            doc_sathi_runtime_add_unique_index_if_clean($database, 'doctor', 'unique_doctor_email', 'docemail');
        }

        if (doc_sathi_runtime_column_exists($database, 'doctor', 'doctel')) {
            doc_sathi_runtime_nullify_empty_strings($database, 'doctor', 'doctel');
            doc_sathi_runtime_add_unique_index_if_clean($database, 'doctor', 'unique_doctor_phone', 'doctel', true);
        }

        if (doc_sathi_runtime_column_exists($database, 'doctor', 'license_number')) {
            doc_sathi_runtime_nullify_empty_strings($database, 'doctor', 'license_number');
            doc_sathi_runtime_add_unique_index_if_clean($database, 'doctor', 'unique_doctor_license', 'license_number', true);
        }

        if (doc_sathi_runtime_column_exists($database, 'doctor', 'gender')) {
            doc_sathi_runtime_backfill_gender($database, 'doctor', 'docid', 'docname', 'gender');
        }

        if (
            doc_sathi_runtime_column_exists($database, 'schedule', 'duration_minutes') &&
            doc_sathi_runtime_column_exists($database, 'schedule', 'end_time')
        ) {
            @ $database->query(
                "UPDATE `schedule`
                 SET `duration_minutes` = " . $defaultDuration . "
                 WHERE `duration_minutes` IS NULL
                    OR `duration_minutes` NOT IN (15, 20, 30, 45, 60, 90)"
            );

            @ $database->query(
                "UPDATE `schedule`
                 SET `end_time` = ADDTIME(`scheduletime`, SEC_TO_TIME(`duration_minutes` * 60))
                 WHERE `scheduledate` IS NOT NULL
                   AND `scheduletime` IS NOT NULL
                   AND `duration_minutes` IS NOT NULL
                   AND (`end_time` IS NULL OR `end_time` = '00:00:00')"
            );
        }

        if (
            doc_sathi_runtime_column_exists($database, 'appointment', 'status') &&
            doc_sathi_runtime_column_exists($database, 'appointment', 'completed_at')
        ) {
            @ $database->query(
                "UPDATE `appointment`
                 SET `status` = CASE
                     WHEN `completed_at` IS NOT NULL THEN 'completed'
                     ELSE 'confirmed'
                 END
                 WHERE `status` IS NULL OR `status` = ''"
            );
        }

        if (doc_sathi_runtime_column_exists($database, 'appointment', 'checkup_result')) {
            doc_sathi_runtime_nullify_empty_strings($database, 'appointment', 'checkup_result');
        }

        return true;
    }
}

doc_sathi_runtime_upgrade_schema($database);

?>
