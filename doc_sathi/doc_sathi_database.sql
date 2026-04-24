-- Create and select the database
CREATE DATABASE IF NOT EXISTS doc_sathi;
USE doc_sathi;

-- Rebuild tables cleanly when importing this schema file.
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS appointment;
DROP TABLE IF EXISTS schedule;
DROP TABLE IF EXISTS doctor_documents;
DROP TABLE IF EXISTS doctor;
DROP TABLE IF EXISTS patient;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS specialties;
DROP TABLE IF EXISTS webuser;

-- Create a table in database for login routing
CREATE TABLE IF NOT EXISTS webuser (
  email varchar(255) NOT NULL,
  usertype char(1) NOT NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a table in database for "Patient"
CREATE TABLE IF NOT EXISTS patient (
  pid int(11) NOT NULL AUTO_INCREMENT,
  pemail varchar(255) DEFAULT NULL,
  pname varchar(255) DEFAULT NULL,
  ppassword varchar(255) DEFAULT NULL,
  paddress varchar(255) DEFAULT NULL,
  pdob date DEFAULT NULL,
  gender enum('male','female','other') DEFAULT NULL,
  pnum varchar(15) DEFAULT NULL,
  PRIMARY KEY (pid),
  UNIQUE KEY unique_patient_email (pemail),
  UNIQUE KEY unique_patient_phone (pnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a table in database for "admin"
CREATE TABLE IF NOT EXISTS admin (
  aid int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  aemail varchar(255) NOT NULL,
  apassword varchar(255) DEFAULT NULL,
  UNIQUE KEY unique_admin_email (aemail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin (aemail, apassword) VALUES
('admin1@doc.com', '$2y$10$1GP/F.yfonWaB9323QHfCO3G9lWt/Bfc924jU05Lz1Uxu6HC2Ph1S');

INSERT INTO webuser (email, usertype) VALUES
('admin1@doc.com', 'a');

-- Table structure for table `specialties`
CREATE TABLE IF NOT EXISTS specialties (
  id int(2) NOT NULL,
  sname varchar(50) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `specialties`
INSERT INTO specialties (id, sname) VALUES
(1, 'Cardiology'),
(2, 'Neurology'),
(3, 'Pediatrics'),
(4, 'Dermatology'),
(5, 'Orthopedics'),
(6, 'Gynecology'),
(7, 'Psychiatry'),
(8, 'General Surgery'),
(9, 'Ophthalmology'),
(10, 'Internal Medicine');

-- Create a table in database for "Doctor"
CREATE TABLE IF NOT EXISTS doctor (
  docid int(11) NOT NULL AUTO_INCREMENT,
  docemail varchar(255) NOT NULL,
  docname varchar(255) DEFAULT NULL,
  docpassword varchar(255) DEFAULT NULL,
  doctel varchar(15) DEFAULT NULL,
  docaddress varchar(255) DEFAULT NULL,
  docdob date DEFAULT NULL,
  gender enum('male','female','other') DEFAULT NULL,
  specialties int(2) DEFAULT NULL,
  license_number varchar(100) DEFAULT NULL,
  clinic_name varchar(255) DEFAULT NULL,
  qualification varchar(255) DEFAULT NULL,
  experience_years int(3) DEFAULT NULL,
  website varchar(255) DEFAULT NULL,
  profile_photo varchar(255) DEFAULT NULL,
  bio text DEFAULT NULL,
  verification_status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  verification_submitted_at datetime DEFAULT NULL,
  verification_reviewed_at datetime DEFAULT NULL,
  verification_document varchar(255) DEFAULT NULL,
  certification_file varchar(255) DEFAULT NULL,
  verified_by int(11) DEFAULT NULL,
  verified_at datetime DEFAULT NULL,
  admin_remarks text DEFAULT NULL,
  rejection_reason text DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (docid),
  UNIQUE KEY unique_doctor_email (docemail),
  UNIQUE KEY unique_doctor_phone (doctel),
  UNIQUE KEY unique_doctor_license (license_number),
  KEY specialties (specialties),
  KEY idx_doctor_verified_by (verified_by),
  KEY idx_doctor_status_name (verification_status, docname),
  KEY idx_doctor_status_specialty (verification_status, specialties),
  CONSTRAINT fk_doctor_specialty
    FOREIGN KEY (specialties) REFERENCES specialties (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_doctor_verified_by
    FOREIGN KEY (verified_by) REFERENCES admin (aid)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Table structure for table `schedule`
CREATE TABLE IF NOT EXISTS schedule (
  scheduleid int(11) NOT NULL AUTO_INCREMENT,
  docid int(11) DEFAULT NULL,
  title varchar(255) DEFAULT NULL,
  scheduledate date DEFAULT NULL,
  scheduletime time DEFAULT NULL,
  duration_minutes smallint(5) unsigned NOT NULL DEFAULT 30,
  end_time time DEFAULT NULL,
  nop int(4) DEFAULT NULL,
  PRIMARY KEY (scheduleid),
  KEY docid (docid),
  KEY idx_schedule_date_time (scheduledate, scheduletime),
  KEY idx_schedule_doctor_date_time (docid, scheduledate, scheduletime),
  KEY idx_schedule_doctor_window (docid, scheduledate, scheduletime, end_time),
  CONSTRAINT fk_schedule_doctor
    FOREIGN KEY (docid) REFERENCES doctor (docid)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `appointment`
CREATE TABLE IF NOT EXISTS appointment (
  appoid int(11) NOT NULL AUTO_INCREMENT,
  pid int(11) DEFAULT NULL,
  apponum int(3) DEFAULT NULL,
  scheduleid int(11) DEFAULT NULL,
  appodate date DEFAULT NULL,
  status enum('confirmed','completed','cancelled') NOT NULL DEFAULT 'confirmed',
  completed_at datetime DEFAULT NULL,
  completed_by int(11) DEFAULT NULL,
  checkup_result text DEFAULT NULL,
  PRIMARY KEY (appoid),
  KEY pid (pid),
  KEY scheduleid (scheduleid),
  KEY idx_appointment_completed_by (completed_by),
  KEY idx_appointment_schedule_patient (scheduleid, pid),
  KEY idx_appointment_patient_schedule (pid, scheduleid),
  KEY idx_appointment_schedule_number (scheduleid, apponum),
  KEY idx_appointment_status (status),
  KEY idx_appointment_schedule_status (scheduleid, status),
  CONSTRAINT fk_appointment_patient
    FOREIGN KEY (pid) REFERENCES patient (pid)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_appointment_schedule
    FOREIGN KEY (scheduleid) REFERENCES schedule (scheduleid)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_appointment_completed_by
    FOREIGN KEY (completed_by) REFERENCES doctor (docid)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
