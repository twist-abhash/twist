USE doc_sathi;

SET @schema_name := DATABASE();

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'schedule'
          AND COLUMN_NAME = 'duration_minutes'
    ) = 0,
    'ALTER TABLE schedule ADD COLUMN duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER scheduletime',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'schedule'
          AND COLUMN_NAME = 'end_time'
    ) = 0,
    'ALTER TABLE schedule ADD COLUMN end_time TIME DEFAULT NULL AFTER duration_minutes',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'schedule'
          AND INDEX_NAME = 'idx_schedule_doctor_window'
    ) = 0,
    'ALTER TABLE schedule ADD INDEX idx_schedule_doctor_window (docid, scheduledate, scheduletime, end_time)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'appointment'
          AND COLUMN_NAME = 'status'
    ) = 0,
    'ALTER TABLE appointment ADD COLUMN status ENUM(''confirmed'',''completed'',''cancelled'') NOT NULL DEFAULT ''confirmed'' AFTER appodate',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'appointment'
          AND COLUMN_NAME = 'completed_at'
    ) = 0,
    'ALTER TABLE appointment ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'appointment'
          AND COLUMN_NAME = 'completed_by'
    ) = 0,
    'ALTER TABLE appointment ADD COLUMN completed_by INT(11) DEFAULT NULL AFTER completed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'appointment'
          AND INDEX_NAME = 'idx_appointment_status'
    ) = 0,
    'ALTER TABLE appointment ADD INDEX idx_appointment_status (status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'appointment'
          AND INDEX_NAME = 'idx_appointment_schedule_status'
    ) = 0,
    'ALTER TABLE appointment ADD INDEX idx_appointment_schedule_status (scheduleid, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS doctor_documents (
  doctor_document_id int(11) NOT NULL AUTO_INCREMENT,
  docid int(11) NOT NULL,
  document_category enum('cv','education','experience','license') NOT NULL,
  original_name varchar(255) NOT NULL,
  file_path varchar(255) NOT NULL,
  uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (doctor_document_id),
  KEY idx_doctor_documents_doctor_category (docid, document_category),
  CONSTRAINT fk_doctor_documents_doctor
    FOREIGN KEY (docid) REFERENCES doctor (docid)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE patient
SET pemail = NULL
WHERE pemail IS NOT NULL
  AND TRIM(pemail) = '';

UPDATE patient
SET pnum = NULL
WHERE pnum IS NOT NULL
  AND TRIM(pnum) = '';

UPDATE doctor
SET doctel = NULL
WHERE doctel IS NOT NULL
  AND TRIM(doctel) = '';

UPDATE doctor
SET license_number = NULL
WHERE license_number IS NOT NULL
  AND TRIM(license_number) = '';

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'patient'
          AND COLUMN_NAME = 'pemail'
          AND NON_UNIQUE = 0
    ) = 0
    AND (
        SELECT COUNT(*)
        FROM (
            SELECT pemail
            FROM patient
            WHERE pemail IS NOT NULL
              AND TRIM(pemail) <> ''
            GROUP BY pemail
            HAVING COUNT(*) > 1
        ) AS duplicate_patient_emails
    ) = 0,
    'ALTER TABLE patient ADD UNIQUE KEY unique_patient_email (pemail)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'patient'
          AND COLUMN_NAME = 'pnum'
          AND NON_UNIQUE = 0
    ) = 0
    AND (
        SELECT COUNT(*)
        FROM (
            SELECT pnum
            FROM patient
            WHERE pnum IS NOT NULL
              AND TRIM(pnum) <> ''
            GROUP BY pnum
            HAVING COUNT(*) > 1
        ) AS duplicate_patient_phones
    ) = 0,
    'ALTER TABLE patient ADD UNIQUE KEY unique_patient_phone (pnum)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'doctor'
          AND COLUMN_NAME = 'docemail'
          AND NON_UNIQUE = 0
    ) = 0
    AND (
        SELECT COUNT(*)
        FROM (
            SELECT docemail
            FROM doctor
            WHERE docemail IS NOT NULL
            GROUP BY docemail
            HAVING COUNT(*) > 1
        ) AS duplicate_doctor_emails
    ) = 0,
    'ALTER TABLE doctor ADD UNIQUE KEY unique_doctor_email (docemail)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'doctor'
          AND COLUMN_NAME = 'doctel'
          AND NON_UNIQUE = 0
    ) = 0
    AND (
        SELECT COUNT(*)
        FROM (
            SELECT doctel
            FROM doctor
            WHERE doctel IS NOT NULL
              AND TRIM(doctel) <> ''
            GROUP BY doctel
            HAVING COUNT(*) > 1
        ) AS duplicate_doctor_phones
    ) = 0,
    'ALTER TABLE doctor ADD UNIQUE KEY unique_doctor_phone (doctel)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'doctor'
          AND COLUMN_NAME = 'license_number'
          AND NON_UNIQUE = 0
    ) = 0
    AND (
        SELECT COUNT(*)
        FROM (
            SELECT license_number
            FROM doctor
            WHERE license_number IS NOT NULL
              AND TRIM(license_number) <> ''
            GROUP BY license_number
            HAVING COUNT(*) > 1
        ) AS duplicate_doctor_licenses
    ) = 0,
    'ALTER TABLE doctor ADD UNIQUE KEY unique_doctor_license (license_number)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE schedule
SET duration_minutes = 30
WHERE duration_minutes IS NULL
   OR duration_minutes NOT IN (15, 20, 30, 45, 60, 90);

UPDATE schedule
SET end_time = ADDTIME(scheduletime, SEC_TO_TIME(duration_minutes * 60))
WHERE scheduledate IS NOT NULL
  AND scheduletime IS NOT NULL
  AND duration_minutes IS NOT NULL
  AND (end_time IS NULL OR end_time = '00:00:00');

UPDATE appointment
SET status = CASE
    WHEN completed_at IS NOT NULL THEN 'completed'
    ELSE 'confirmed'
END
WHERE status IS NULL OR status = '';
