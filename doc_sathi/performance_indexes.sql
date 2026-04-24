USE doc_sathi;

ALTER TABLE schedule MODIFY docid int(11) DEFAULT NULL;

ALTER TABLE patient ADD UNIQUE INDEX unique_patient_email (pemail);
ALTER TABLE doctor ADD INDEX idx_doctor_status_name (verification_status, docname);
ALTER TABLE doctor ADD INDEX idx_doctor_status_specialty (verification_status, specialties);
ALTER TABLE schedule ADD INDEX idx_schedule_date_time (scheduledate, scheduletime);
ALTER TABLE schedule ADD INDEX idx_schedule_doctor_date_time (docid, scheduledate, scheduletime);
ALTER TABLE appointment ADD INDEX idx_appointment_schedule_patient (scheduleid, pid);
ALTER TABLE appointment ADD INDEX idx_appointment_patient_schedule (pid, scheduleid);
ALTER TABLE appointment ADD INDEX idx_appointment_schedule_number (scheduleid, apponum);
