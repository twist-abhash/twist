USE doc_sathi;

SET @schema_name := DATABASE();

ALTER TABLE webuser ENGINE=InnoDB;
ALTER TABLE patient ENGINE=InnoDB;
ALTER TABLE admin ENGINE=InnoDB;
ALTER TABLE specialties ENGINE=InnoDB;
ALTER TABLE doctor ENGINE=InnoDB;
ALTER TABLE schedule ENGINE=InnoDB;
ALTER TABLE appointment ENGINE=InnoDB;

ALTER TABLE admin MODIFY aid int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE doctor MODIFY specialties int(2) DEFAULT NULL;
ALTER TABLE doctor MODIFY verified_by int(11) DEFAULT NULL;
ALTER TABLE schedule MODIFY docid int(11) DEFAULT NULL;
ALTER TABLE appointment MODIFY pid int(11) DEFAULT NULL;
ALTER TABLE appointment MODIFY scheduleid int(11) DEFAULT NULL;
ALTER TABLE appointment MODIFY completed_by int(11) DEFAULT NULL;

UPDATE doctor d
LEFT JOIN specialties s ON s.id = d.specialties
SET d.specialties = NULL
WHERE d.specialties IS NOT NULL
  AND s.id IS NULL;

UPDATE doctor d
LEFT JOIN admin a ON a.aid = d.verified_by
SET d.verified_by = NULL
WHERE d.verified_by IS NOT NULL
  AND a.aid IS NULL;

UPDATE schedule s
LEFT JOIN doctor d ON d.docid = s.docid
SET s.docid = NULL
WHERE s.docid IS NOT NULL
  AND d.docid IS NULL;

UPDATE appointment a
LEFT JOIN patient p ON p.pid = a.pid
SET a.pid = NULL
WHERE a.pid IS NOT NULL
  AND p.pid IS NULL;

UPDATE appointment a
LEFT JOIN schedule s ON s.scheduleid = a.scheduleid
SET a.scheduleid = NULL
WHERE a.scheduleid IS NOT NULL
  AND s.scheduleid IS NULL;

UPDATE appointment a
LEFT JOIN doctor d ON d.docid = a.completed_by
SET a.completed_by = NULL
WHERE a.completed_by IS NOT NULL
  AND d.docid IS NULL;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'doctor'
          AND INDEX_NAME = 'idx_doctor_verified_by'
    ) = 0,
    'ALTER TABLE doctor ADD INDEX idx_doctor_verified_by (verified_by)',
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
          AND INDEX_NAME = 'idx_appointment_completed_by'
    ) = 0,
    'ALTER TABLE appointment ADD INDEX idx_appointment_completed_by (completed_by)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND CONSTRAINT_NAME = 'fk_doctor_specialty'
    ) = 0,
    'ALTER TABLE doctor ADD CONSTRAINT fk_doctor_specialty FOREIGN KEY (specialties) REFERENCES specialties (id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND CONSTRAINT_NAME = 'fk_doctor_verified_by'
    ) = 0,
    'ALTER TABLE doctor ADD CONSTRAINT fk_doctor_verified_by FOREIGN KEY (verified_by) REFERENCES admin (aid) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND CONSTRAINT_NAME = 'fk_schedule_doctor'
    ) = 0,
    'ALTER TABLE schedule ADD CONSTRAINT fk_schedule_doctor FOREIGN KEY (docid) REFERENCES doctor (docid) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND CONSTRAINT_NAME = 'fk_appointment_patient'
    ) = 0,
    'ALTER TABLE appointment ADD CONSTRAINT fk_appointment_patient FOREIGN KEY (pid) REFERENCES patient (pid) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND CONSTRAINT_NAME = 'fk_appointment_schedule'
    ) = 0,
    'ALTER TABLE appointment ADD CONSTRAINT fk_appointment_schedule FOREIGN KEY (scheduleid) REFERENCES schedule (scheduleid) ON UPDATE CASCADE ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @schema_name
          AND CONSTRAINT_NAME = 'fk_appointment_completed_by'
    ) = 0,
    'ALTER TABLE appointment ADD CONSTRAINT fk_appointment_completed_by FOREIGN KEY (completed_by) REFERENCES doctor (docid) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
