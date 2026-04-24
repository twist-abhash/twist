USE doc_sathi;
SET NAMES utf8mb4;

SET @demo_password_hash := '$2y$10$BFTL8f.IB1sNv1mjqfWL8u9waUGcCmqs.eyOMZjPcm2cBj50hdk7C';
SET @demo_verified_by := COALESCE((SELECT aid FROM admin ORDER BY aid LIMIT 1), 1);

START TRANSACTION;

INSERT INTO webuser (email, usertype) VALUES
('demo.dr1@docsathi.test', 'd'),
('demo.dr2@docsathi.test', 'd'),
('demo.dr3@docsathi.test', 'd'),
('demo.dr4@docsathi.test', 'd'),
('demo.dr5@docsathi.test', 'd'),
('demo.dr6@docsathi.test', 'd'),
('demo.dr7@docsathi.test', 'd'),
('demo.dr8@docsathi.test', 'd'),
('demo.dr9@docsathi.test', 'd'),
('demo.dr10@docsathi.test', 'd'),
('demo.patient1@docsathi.test', 'p'),
('demo.patient2@docsathi.test', 'p'),
('demo.patient3@docsathi.test', 'p'),
('demo.patient4@docsathi.test', 'p'),
('demo.patient5@docsathi.test', 'p'),
('demo.patient6@docsathi.test', 'p'),
('demo.patient7@docsathi.test', 'p'),
('demo.patient8@docsathi.test', 'p'),
('demo.patient9@docsathi.test', 'p'),
('demo.patient10@docsathi.test', 'p'),
('demo.patient11@docsathi.test', 'p'),
('demo.patient12@docsathi.test', 'p'),
('demo.patient13@docsathi.test', 'p'),
('demo.patient14@docsathi.test', 'p'),
('demo.patient15@docsathi.test', 'p')
ON DUPLICATE KEY UPDATE usertype = VALUES(usertype);

INSERT INTO doctor (
  docid, docemail, docname, docpassword, doctel, docaddress, docdob, gender, specialties,
  license_number, clinic_name, qualification, experience_years, website,
  profile_photo, bio, verification_status, verification_submitted_at,
  verification_reviewed_at, verification_document, certification_file,
  verified_by, verified_at, admin_remarks, rejection_reason
) VALUES
(1001, 'demo.dr1@docsathi.test', 'Dr. Aarav Shrestha', @demo_password_hash, '9806001001', 'Kathmandu', '1981-03-12', 'male', 1, 'NMC-DEMO-1001', 'Himal Cardio Clinic', 'MBBS, MD Cardiology', 16, 'https://docsathi.test/dr-aarav-shrestha', NULL, 'Experienced cardiologist focused on preventive heart care and hypertension management.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1001.pdf', 'uploads/certifications/demo_doctor_1001.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1002, 'demo.dr2@docsathi.test', 'Dr. Sushil Koirala', @demo_password_hash, '9806001002', 'Lalitpur', '1978-08-21', 'male', 2, 'NMC-DEMO-1002', 'Patan Neuro Center', 'MBBS, MD Neurology', 18, 'https://docsathi.test/dr-sushil-koirala', NULL, 'Neurologist handling migraine, seizure, and nerve disorder consultations.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1002.pdf', 'uploads/certifications/demo_doctor_1002.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1003, 'demo.dr3@docsathi.test', 'Dr. Manisha Adhikari', @demo_password_hash, '9806001003', 'Pokhara', '1985-01-30', 'female', 3, 'NMC-DEMO-1003', 'Fewa Child Care', 'MBBS, MD Pediatrics', 12, 'https://docsathi.test/dr-manisha-adhikari', NULL, 'Pediatrician supporting child wellness, vaccination, and growth monitoring.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1003.pdf', 'uploads/certifications/demo_doctor_1003.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1004, 'demo.dr4@docsathi.test', 'Dr. Ramesh Gurung', @demo_password_hash, '9806001004', 'Dharan', '1983-06-05', 'male', 4, 'NMC-DEMO-1004', 'Dharan Skin Clinic', 'MBBS, MD Dermatology', 14, 'https://docsathi.test/dr-ramesh-gurung', NULL, 'Dermatologist treating acne, allergy, hair loss, and skin infections.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1004.pdf', 'uploads/certifications/demo_doctor_1004.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1005, 'demo.dr5@docsathi.test', 'Dr. Pratiksha Thapa', @demo_password_hash, '9806001005', 'Biratnagar', '1980-11-14', 'female', 5, 'NMC-DEMO-1005', 'Koshi Ortho Care', 'MBBS, MS Orthopedics', 17, 'https://docsathi.test/dr-pratiksha-thapa', NULL, 'Orthopedic surgeon focused on joint pain, fracture care, and sports injuries.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1005.pdf', 'uploads/certifications/demo_doctor_1005.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1006, 'demo.dr6@docsathi.test', 'Dr. Bikash Rai', @demo_password_hash, '9806001006', 'Chitwan', '1986-09-09', 'male', 6, 'NMC-DEMO-1006', 'Narayani Women Clinic', 'MBBS, MD Gynecology', 11, 'https://docsathi.test/dr-bikash-rai', NULL, 'Gynecology consultant for reproductive health, pregnancy care, and wellness checks.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1006.pdf', 'uploads/certifications/demo_doctor_1006.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1007, 'demo.dr7@docsathi.test', 'Dr. Nirmala Tamang', @demo_password_hash, '9806001007', 'Bhaktapur', '1982-04-27', 'female', 7, 'NMC-DEMO-1007', 'Bhaktapur Mind Care', 'MBBS, MD Psychiatry', 15, 'https://docsathi.test/dr-nirmala-tamang', NULL, 'Psychiatrist supporting anxiety, depression, sleep, and stress-related care.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1007.pdf', 'uploads/certifications/demo_doctor_1007.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1008, 'demo.dr8@docsathi.test', 'Dr. Rajan Bhandari', @demo_password_hash, '9806001008', 'Butwal', '1979-12-03', 'male', 8, 'NMC-DEMO-1008', 'Lumbini Surgical Center', 'MBBS, MS General Surgery', 19, 'https://docsathi.test/dr-rajan-bhandari', NULL, 'General surgeon for abdominal, hernia, wound, and minor surgical consultations.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1008.pdf', 'uploads/certifications/demo_doctor_1008.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1009, 'demo.dr9@docsathi.test', 'Dr. Sunita Poudel', @demo_password_hash, '9806001009', 'Nepalgunj', '1984-07-18', 'female', 9, 'NMC-DEMO-1009', 'Bheri Eye Clinic', 'MBBS, MS Ophthalmology', 13, 'https://docsathi.test/dr-sunita-poudel', NULL, 'Ophthalmologist for eye checkups, vision issues, allergies, and diabetic eye care.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1009.pdf', 'uploads/certifications/demo_doctor_1009.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL),
(1010, 'demo.dr10@docsathi.test', 'Dr. Deepak Basnet', @demo_password_hash, '9806001010', 'Hetauda', '1981-10-25', 'male', 10, 'NMC-DEMO-1010', 'Makwanpur Internal Medicine', 'MBBS, MD Internal Medicine', 16, 'https://docsathi.test/dr-deepak-basnet', NULL, 'Internal medicine physician for diabetes, blood pressure, fever, and chronic disease care.', 'approved', NOW(), NOW(), 'uploads/verifications/demo_doctor_1010.pdf', 'uploads/certifications/demo_doctor_1010.pdf', @demo_verified_by, NOW(), 'Demo doctor auto-approved for testing.', NULL)
ON DUPLICATE KEY UPDATE
  docemail = VALUES(docemail),
  docname = VALUES(docname),
  docpassword = VALUES(docpassword),
  doctel = VALUES(doctel),
  docaddress = VALUES(docaddress),
  docdob = VALUES(docdob),
  gender = VALUES(gender),
  specialties = VALUES(specialties),
  license_number = VALUES(license_number),
  clinic_name = VALUES(clinic_name),
  qualification = VALUES(qualification),
  experience_years = VALUES(experience_years),
  website = VALUES(website),
  bio = VALUES(bio),
  verification_status = 'approved',
  verification_submitted_at = VALUES(verification_submitted_at),
  verification_reviewed_at = VALUES(verification_reviewed_at),
  verification_document = VALUES(verification_document),
  certification_file = VALUES(certification_file),
  verified_by = VALUES(verified_by),
  verified_at = VALUES(verified_at),
  admin_remarks = VALUES(admin_remarks),
  rejection_reason = NULL;

INSERT INTO patient (pid, pemail, pname, ppassword, paddress, pdob, gender, pnum) VALUES
(2001, 'demo.patient1@docsathi.test', 'Aayush Shrestha', @demo_password_hash, 'Kathmandu', '1998-02-17', 'male', '9816002001'),
(2002, 'demo.patient2@docsathi.test', 'Anjali Karki', @demo_password_hash, 'Lalitpur', '1995-05-24', 'female', '9816002002'),
(2003, 'demo.patient3@docsathi.test', 'Bikram Lama', @demo_password_hash, 'Bhaktapur', '1992-09-08', 'male', '9816002003'),
(2004, 'demo.patient4@docsathi.test', 'Sita Magar', @demo_password_hash, 'Pokhara', '1989-12-13', 'female', '9816002004'),
(2005, 'demo.patient5@docsathi.test', 'Nabin Subedi', @demo_password_hash, 'Biratnagar', '1997-01-29', 'male', '9816002005'),
(2006, 'demo.patient6@docsathi.test', 'Rojina Rai', @demo_password_hash, 'Dharan', '1994-03-19', 'female', '9816002006'),
(2007, 'demo.patient7@docsathi.test', 'Prabin Gurung', @demo_password_hash, 'Chitwan', '1991-07-22', 'male', '9816002007'),
(2008, 'demo.patient8@docsathi.test', 'Sarmila Thapa', @demo_password_hash, 'Butwal', '1999-10-04', 'female', '9816002008'),
(2009, 'demo.patient9@docsathi.test', 'Kiran Bista', @demo_password_hash, 'Nepalgunj', '1993-11-30', 'male', '9816002009'),
(2010, 'demo.patient10@docsathi.test', 'Pooja Tamang', @demo_password_hash, 'Hetauda', '1996-06-15', 'female', '9816002010'),
(2011, 'demo.patient11@docsathi.test', 'Roshan Adhikari', @demo_password_hash, 'Janakpur', '1990-08-27', 'male', '9816002011'),
(2012, 'demo.patient12@docsathi.test', 'Menuka Poudel', @demo_password_hash, 'Dhangadhi', '1988-04-02', 'female', '9816002012'),
(2013, 'demo.patient13@docsathi.test', 'Sudip Koirala', @demo_password_hash, 'Bharatpur', '1997-09-11', 'male', '9816002013'),
(2014, 'demo.patient14@docsathi.test', 'Laxmi Nepali', @demo_password_hash, 'Tansen', '1995-12-06', 'female', '9816002014'),
(2015, 'demo.patient15@docsathi.test', 'Gita Bhandari', @demo_password_hash, 'Ilam', '1992-02-23', 'female', '9816002015')
ON DUPLICATE KEY UPDATE
  pemail = VALUES(pemail),
  pname = VALUES(pname),
  ppassword = VALUES(ppassword),
  paddress = VALUES(paddress),
  pdob = VALUES(pdob),
  gender = VALUES(gender),
  pnum = VALUES(pnum);

INSERT INTO schedule (scheduleid, docid, title, scheduledate, scheduletime, nop) VALUES
(3001, '1001', 'Cardiology Morning Consultation', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', 12),
(3002, '1001', 'Heart Health Follow Up', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:30:00', 10),
(3003, '1001', 'Hypertension Review', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', 14),
(3004, '1001', 'Chest Pain Screening', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '10:00:00', 8),
(3005, '1001', 'Preventive Cardiology Clinic', DATE_ADD(CURDATE(), INTERVAL 13 DAY), '15:30:00', 12),
(3006, '1002', 'Neurology Morning Consultation', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:30:00', 10),
(3007, '1002', 'Migraine Care Session', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '13:00:00', 12),
(3008, '1002', 'Seizure Follow Up', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '16:00:00', 8),
(3009, '1002', 'Nerve Pain Review', DATE_ADD(CURDATE(), INTERVAL 11 DAY), '10:30:00', 10),
(3010, '1002', 'Memory Clinic', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '12:00:00', 9),
(3011, '1003', 'Pediatrics General Checkup', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', 15),
(3012, '1003', 'Child Vaccination Review', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', 15),
(3013, '1003', 'Growth Monitoring Clinic', DATE_ADD(CURDATE(), INTERVAL 9 DAY), '14:30:00', 12),
(3014, '1003', 'Child Fever Consultation', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '10:00:00', 14),
(3015, '1003', 'Newborn Care Session', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '16:30:00', 10),
(3016, '1004', 'Dermatology Skin Consultation', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00', 12),
(3017, '1004', 'Acne Treatment Review', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '13:30:00', 10),
(3018, '1004', 'Allergy and Rash Clinic', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '15:00:00', 12),
(3019, '1004', 'Hair Loss Consultation', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '11:00:00', 8),
(3020, '1004', 'Skin Infection Follow Up', DATE_ADD(CURDATE(), INTERVAL 13 DAY), '16:00:00', 10),
(3021, '1005', 'Orthopedics Joint Pain Clinic', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', 12),
(3022, '1005', 'Fracture Follow Up', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '12:30:00', 10),
(3023, '1005', 'Back Pain Consultation', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '14:00:00', 12),
(3024, '1005', 'Sports Injury Review', DATE_ADD(CURDATE(), INTERVAL 11 DAY), '10:00:00', 8),
(3025, '1005', 'Knee and Shoulder Clinic', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '15:30:00', 10),
(3026, '1006', 'Gynecology Wellness Clinic', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:30:00', 12),
(3027, '1006', 'Pregnancy Care Session', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:30:00', 14),
(3028, '1006', 'Reproductive Health Review', DATE_ADD(CURDATE(), INTERVAL 9 DAY), '13:30:00', 10),
(3029, '1006', 'Menstrual Health Clinic', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '15:00:00', 12),
(3030, '1006', 'Postnatal Follow Up', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '10:30:00', 8),
(3031, '1007', 'Psychiatry Stress Care', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', 10),
(3032, '1007', 'Anxiety Consultation', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', 8),
(3033, '1007', 'Sleep Health Clinic', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '16:00:00', 10),
(3034, '1007', 'Depression Follow Up', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '09:30:00', 8),
(3035, '1007', 'Mind Care Review', DATE_ADD(CURDATE(), INTERVAL 13 DAY), '12:30:00', 10),
(3036, '1008', 'General Surgery Consultation', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:00:00', 10),
(3037, '1008', 'Hernia Screening', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '13:00:00', 8),
(3038, '1008', 'Wound Care Follow Up', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '15:30:00', 10),
(3039, '1008', 'Abdominal Pain Review', DATE_ADD(CURDATE(), INTERVAL 11 DAY), '11:30:00', 12),
(3040, '1008', 'Minor Surgery Assessment', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '16:30:00', 8),
(3041, '1009', 'Ophthalmology Eye Checkup', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', 14),
(3042, '1009', 'Vision Problem Review', DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', 12),
(3043, '1009', 'Eye Allergy Clinic', DATE_ADD(CURDATE(), INTERVAL 9 DAY), '14:00:00', 10),
(3044, '1009', 'Diabetic Eye Screening', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '10:30:00', 10),
(3045, '1009', 'Dry Eye Follow Up', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '15:00:00', 12),
(3046, '1010', 'Internal Medicine Consultation', DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:30:00', 12),
(3047, '1010', 'Diabetes Care Session', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '12:00:00', 10),
(3048, '1010', 'Blood Pressure Review', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:30:00', 12),
(3049, '1010', 'Fever and Infection Clinic', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '10:00:00', 14),
(3050, '1010', 'Chronic Disease Follow Up', DATE_ADD(CURDATE(), INTERVAL 13 DAY), '16:00:00', 10)
ON DUPLICATE KEY UPDATE
  docid = VALUES(docid),
  title = VALUES(title),
  scheduledate = VALUES(scheduledate),
  scheduletime = VALUES(scheduletime),
  nop = VALUES(nop);

UPDATE schedule
SET duration_minutes = 30,
    end_time = ADDTIME(scheduletime, '00:30:00')
WHERE scheduleid BETWEEN 3001 AND 3050;

COMMIT;
