-- Combined Core HR import for ProFreeHost / phpMyAdmin.
-- Select your hosted database first, then import this file.
-- Do not commit real credentials into this file.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ===== schema.sql =====
CREATE TABLE roles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(30) NOT NULL UNIQUE, description VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE permissions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL UNIQUE, description VARCHAR(255) NULL);
CREATE TABLE role_permissions (role_id INT UNSIGNED NOT NULL, permission_id INT UNSIGNED NOT NULL, PRIMARY KEY (role_id, permission_id), FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE);
CREATE TABLE departments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE positions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE branches (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, address VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE employees (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_number VARCHAR(30) NOT NULL UNIQUE, first_name VARCHAR(80) NOT NULL, middle_name VARCHAR(80) NULL, last_name VARCHAR(80) NOT NULL, suffix VARCHAR(20) NULL, date_of_birth DATE NULL, gender VARCHAR(30) NULL, civil_status VARCHAR(30) NULL, email VARCHAR(150) NULL UNIQUE, phone VARCHAR(40) NULL, address VARCHAR(255) NULL, department_id INT UNSIGNED NULL, position_id INT UNSIGNED NULL, branch_id INT UNSIGNED NULL, employment_status ENUM('Active','Probationary','On Leave','Separated') NOT NULL DEFAULT 'Active', date_hired DATE NULL, employment_type ENUM('Regular','Probationary','Contractual','Part-time') NOT NULL DEFAULT 'Regular', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_employee_status (employment_status), FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL, FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL, FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL);
CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NULL UNIQUE, role_id INT UNSIGNED NOT NULL, username VARCHAR(80) NOT NULL UNIQUE, email VARCHAR(150) NULL, password_hash VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, session_version INT UNSIGNED NOT NULL DEFAULT 1, last_login_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_users_email (email), FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL, FOREIGN KEY (role_id) REFERENCES roles(id));
CREATE TABLE user_roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_user_role (user_id, role_id), KEY idx_user_roles_user (user_id), KEY idx_user_roles_role (role_id), CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE);
CREATE TABLE employee_profiles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL UNIQUE, nationality VARCHAR(80) NULL, religion VARCHAR(80) NULL, notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE);
CREATE TABLE employee_contacts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, contact_type VARCHAR(30) NOT NULL, contact_value VARCHAR(150) NOT NULL, is_primary TINYINT(1) DEFAULT 0, FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE);
CREATE TABLE emergency_contacts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, relationship VARCHAR(50) NULL, phone VARCHAR(40) NOT NULL, address VARCHAR(255) NULL, FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE);
CREATE TABLE employment_records (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, status VARCHAR(30) NOT NULL, employment_type VARCHAR(30) NOT NULL, date_hired DATE NULL, date_ended DATE NULL, notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE);
CREATE TABLE employment_histories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, department_id INT UNSIGNED NULL, position_id INT UNSIGNED NULL, branch_id INT UNSIGNED NULL, effective_date DATE NOT NULL, end_date DATE NULL, reason VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE, FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL, FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL, FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL);
CREATE TABLE audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, action VARCHAR(80) NOT NULL, entity_type VARCHAR(80) NOT NULL, entity_id INT UNSIGNED NULL, module VARCHAR(80) NULL, result VARCHAR(80) NULL, details JSON NULL, ip_address VARCHAR(45) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_audit_created (created_at), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL);

ALTER TABLE departments ADD COLUMN code VARCHAR(30) NULL UNIQUE AFTER name;
ALTER TABLE positions ADD COLUMN code VARCHAR(30) NULL UNIQUE AFTER name, ADD COLUMN department_id INT UNSIGNED NULL AFTER code, ADD INDEX idx_position_department (department_id), ADD CONSTRAINT fk_position_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
ALTER TABLE branches ADD COLUMN code VARCHAR(30) NULL UNIQUE AFTER name, ADD COLUMN city VARCHAR(80) NULL AFTER address, ADD COLUMN province VARCHAR(80) NULL AFTER city;
ALTER TABLE employees ADD COLUMN nationality VARCHAR(80) NULL AFTER civil_status, ADD COLUMN city VARCHAR(80) NULL AFTER address, ADD COLUMN province VARCHAR(80) NULL AFTER city, ADD COLUMN postal_code VARCHAR(20) NULL AFTER province, ADD COLUMN probation_end_date DATE NULL AFTER date_hired;
ALTER TABLE employment_histories ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT 'UPDATE' AFTER employee_id, ADD COLUMN previous_department_id INT UNSIGNED NULL AFTER event_type, ADD COLUMN previous_position_id INT UNSIGNED NULL AFTER previous_department_id, ADD COLUMN previous_branch_id INT UNSIGNED NULL AFTER previous_position_id, ADD COLUMN previous_status VARCHAR(30) NULL AFTER previous_branch_id, ADD COLUMN new_status VARCHAR(30) NULL AFTER previous_status, ADD COLUMN performed_by INT UNSIGNED NULL AFTER reason, ADD INDEX idx_history_employee_date (employee_id, effective_date), ADD CONSTRAINT fk_history_performed_by FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE document_types (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, code VARCHAR(40) NOT NULL UNIQUE, description VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE document_templates (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_type_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, code VARCHAR(40) NOT NULL UNIQUE, description VARCHAR(255) NULL, content LONGTEXT NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (document_type_id) REFERENCES document_types(id), FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE employee_documents (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, document_type_id INT UNSIGNED NOT NULL, title VARCHAR(180) NOT NULL, description TEXT NULL, issue_date DATE NULL, effective_date DATE NULL, expiration_date DATE NULL, status ENUM('ACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE', created_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_documents_employee (employee_id), FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE, FOREIGN KEY (document_type_id) REFERENCES document_types(id), FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE document_drafts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, document_type_id INT UNSIGNED NOT NULL, template_id INT UNSIGNED NULL, title VARCHAR(180) NOT NULL, content LONGTEXT NOT NULL, status ENUM('DRAFT','FOR_REVIEW','APPROVED','FINALIZED','ARCHIVED') NOT NULL DEFAULT 'DRAFT', rejection_reason VARCHAR(255) NULL, created_by INT UNSIGNED NULL, reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, finalized_by INT UNSIGNED NULL, finalized_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE, FOREIGN KEY (document_type_id) REFERENCES document_types(id), FOREIGN KEY (template_id) REFERENCES document_templates(id) ON DELETE SET NULL, FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY (finalized_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE document_versions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, draft_id INT UNSIGNED NOT NULL, version_number INT UNSIGNED NOT NULL, content LONGTEXT NOT NULL, change_description VARCHAR(255) NULL, created_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_draft_version (draft_id, version_number), FOREIGN KEY (draft_id) REFERENCES document_drafts(id) ON DELETE CASCADE, FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE document_reviews (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, draft_id INT UNSIGNED NOT NULL, action ENUM('SUBMITTED','APPROVED','REJECTED','FINALIZED','ARCHIVED') NOT NULL, reason VARCHAR(255) NULL, performed_by INT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (draft_id) REFERENCES document_drafts(id) ON DELETE CASCADE, FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE profile_change_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, employee_id INT UNSIGNED NOT NULL, requester_user_id INT UNSIGNED NOT NULL, request_type VARCHAR(50) NOT NULL, requested_field VARCHAR(50) NOT NULL, old_value TEXT NULL, requested_value TEXT NOT NULL, reason TEXT NOT NULL, supporting_information TEXT NULL, status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING', reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, review_reason TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_change_request_status (status), INDEX idx_change_request_employee (employee_id), FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE, FOREIGN KEY (requester_user_id) REFERENCES users(id), FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, title VARCHAR(150) NOT NULL, message TEXT NOT NULL, type VARCHAR(40) NOT NULL DEFAULT 'INFO', related_record_id BIGINT UNSIGNED NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL, INDEX idx_notification_user (user_id,is_read,created_at), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE);
CREATE TABLE profile_generations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  generated_content JSON NOT NULL,
  status ENUM('GENERATED','REVIEWED','APPROVED','REJECTED') NOT NULL DEFAULT 'GENERATED',
  generated_by INT UNSIGNED NULL,
  model VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  unique_key VARCHAR(120) NULL,
  INDEX idx_profile_generation_employee (employee_id, created_at),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE ai_generation_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NULL,
  feature VARCHAR(80) NOT NULL,
  model VARCHAR(120) NULL,
  status VARCHAR(40) NOT NULL,
  generation_time DATETIME NULL,
  request_identifier VARCHAR(120) NULL,
  error_type VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_generation_employee (employee_id, created_at),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- ===== seed.sql =====
INSERT IGNORE INTO roles (name, description) VALUES ('ADMIN','Full system access'),('HR','Human resource management access'),('EMPLOYEE','Employee self-service access');
INSERT IGNORE INTO permissions (name, description) VALUES ('manage_employees','Create and update employee records'),('manage_master_data','Manage departments, positions and branches'),('view_dashboard','View HR dashboard');
INSERT IGNORE INTO departments (name) VALUES ('Human Resources'),('Finance'),('Operations'),('Information Technology');
INSERT IGNORE INTO positions (name) VALUES ('HR Officer'),('HR Manager'),('Accountant'),('Operations Officer'),('IT Specialist'),('Branch Manager');
INSERT IGNORE INTO branches (name,address) VALUES ('Main Office','Makati City'),('Quezon City Branch','Quezon City'),('Makati Branch','Makati City');
INSERT IGNORE INTO employees (employee_number,first_name,middle_name,last_name,email,phone,address,department_id,position_id,branch_id,employment_status,date_hired,employment_type) VALUES ('EMP-0001','Amara','Santos','Reyes','amara.reyes@example.test','09170000001','Makati City',1,2,1,'Active','2021-02-15','Regular'),('EMP-0002','Benjie','Cruz','Navarro','benjie.navarro@example.test','09170000002','Quezon City',2,3,2,'Active','2022-06-01','Regular'),('EMP-0003','Celeste','Lim','Garcia','celeste.garcia@example.test','09170000003','Makati City',3,4,3,'Probationary','2026-01-10','Probationary'),('EMP-0004','Daniel','Tan','Mendoza','daniel.mendoza@example.test','09170000004','Pasig City',4,5,1,'Active','2023-03-20','Regular'),('EMP-0005','Elena','Dela Cruz','Bautista','elena.bautista@example.test','09170000005','Taguig City',1,1,1,'Active','2024-04-08','Regular'),('EMP-0006','Felix','Ramos','Villanueva','felix.villanueva@example.test','09170000006','Marikina City',3,4,2,'Active','2020-11-02','Regular'),('EMP-0007','Grace','Ocampo','Serrano','grace.serrano@example.test','09170000007','Pasay City',2,3,3,'On Leave','2022-09-14','Regular'),('EMP-0008','Hugo','Magsaysay','Rivera','hugo.rivera@example.test','09170000008','Manila City',4,5,1,'Active','2025-01-06','Probationary'),('EMP-0009','Iris','Co','Fernandez','iris.fernandez@example.test','09170000009','Caloocan City',3,6,2,'Active','2023-07-17','Regular'),('EMP-0010','Jonas','Uy','Castillo','jonas.castillo@example.test','09170000010','Mandaluyong City',1,1,3,'Active','2025-05-12','Contractual');
INSERT IGNORE INTO users (employee_id,role_id,username,password_hash) SELECT NULL,id,'admin', '$2y$10$0/sGaH1mgio13tMZoPDtC..DzhhvjNeIawQVbhsDibXbnzQFVHlHu' FROM roles WHERE name='ADMIN';
INSERT IGNORE INTO users (employee_id,role_id,username,password_hash) SELECT 1,id,'hr.manager', '$2y$10$0/sGaH1mgio13tMZoPDtC..DzhhvjNeIawQVbhsDibXbnzQFVHlHu' FROM roles WHERE name='HR';
INSERT IGNORE INTO users (employee_id,role_id,username,password_hash) SELECT 2,id,'employee.benjie', '$2y$10$0/sGaH1mgio13tMZoPDtC..DzhhvjNeIawQVbhsDibXbnzQFVHlHu' FROM roles WHERE name='EMPLOYEE';
INSERT IGNORE INTO users (employee_id,role_id,username,password_hash) SELECT 3,id,'employee.celeste', '$2y$10$0/sGaH1mgio13tMZoPDtC..DzhhvjNeIawQVbhsDibXbnzQFVHlHu' FROM roles WHERE name='EMPLOYEE';

-- ===== phase2-seed.sql =====
UPDATE departments SET code = CASE name WHEN 'Human Resources' THEN 'HR' WHEN 'Finance' THEN 'FIN' WHEN 'Operations' THEN 'OPS' WHEN 'Information Technology' THEN 'IT' ELSE CONCAT('D-', id) END WHERE code IS NULL;
UPDATE positions SET code = CONCAT('POS-', id) WHERE code IS NULL;
UPDATE branches SET code = CASE name WHEN 'Main Office' THEN 'MAIN' WHEN 'Quezon City Branch' THEN 'QC' WHEN 'Makati Branch' THEN 'MKT' ELSE CONCAT('BR-', id) END WHERE code IS NULL;
INSERT IGNORE INTO document_types (name,code,description) VALUES ('Certificate of Employment','COE','Employment verification document'),('Appointment Letter','APPT','Appointment and hiring document'),('Promotion Letter','PROMO','Promotion documentation'),('Transfer Letter','TRANSFER','Employee transfer documentation'),('HR Memorandum','MEMO','General HR memorandum'),('Notice Letter','NOTICE','Formal employee notice');
INSERT IGNORE INTO document_templates (document_type_id,name,code,description,content,created_by) SELECT id,'Certificate of Employment Template','TPL-COE','Standard COE','This certifies that {{employee.first_name}} {{employee.last_name}} ({{employee.employee_id}}) is employed as {{employee.position}} in {{employee.department}} at {{employee.branch}}. Date hired: {{employee.date_hired}}.',NULL FROM document_types WHERE code='COE';
INSERT IGNORE INTO document_templates (document_type_id,name,code,description,content,created_by) SELECT id,'Promotion Letter Template','TPL-PROMO','Promotion draft','Dear {{employee.first_name}},\n\nWe are pleased to confirm your employment update at {{employee.branch}}. Your current position is {{employee.position}} under {{employee.department}}.',NULL FROM document_types WHERE code='PROMO';
INSERT IGNORE INTO employment_histories (employee_id,event_type,effective_date,new_status,reason) SELECT id,'HIRED',date_hired,employment_status,'Initial development seed record' FROM employees WHERE date_hired IS NOT NULL AND NOT EXISTS (SELECT 1 FROM employment_histories h WHERE h.employee_id=employees.id);
INSERT INTO employee_documents (employee_id,document_type_id,title,description,issue_date,created_by) SELECT e.id,t.id,'Employment Certificate - Sample','Fictional sample record','2025-01-15',NULL FROM employees e JOIN document_types t ON t.code='COE' WHERE e.employee_number='EMP-0001' AND NOT EXISTS (SELECT 1 FROM employee_documents d WHERE d.title='Employment Certificate - Sample');
INSERT INTO employee_documents (employee_id,document_type_id,title,description,issue_date,created_by) SELECT e.id,t.id,'Appointment Letter - Sample','Fictional sample record','2024-04-08',NULL FROM employees e JOIN document_types t ON t.code='APPT' WHERE e.employee_number='EMP-0005' AND NOT EXISTS (SELECT 1 FROM employee_documents d WHERE d.title='Appointment Letter - Sample');
INSERT INTO document_drafts (employee_id,document_type_id,template_id,title,content,status,created_by) SELECT e.id,t.id,tt.id,'Sample COE Draft','This certifies that Amara Reyes is employed by the organization.','FOR_REVIEW',NULL FROM employees e JOIN document_types t ON t.code='COE' JOIN document_templates tt ON tt.code='TPL-COE' WHERE e.employee_number='EMP-0001' AND NOT EXISTS (SELECT 1 FROM document_drafts d WHERE d.title='Sample COE Draft');
INSERT INTO document_drafts (employee_id,document_type_id,template_id,title,content,status,created_by) SELECT e.id,t.id,tt.id,'Sample Promotion Draft','Dear Benjie, this letter confirms your promotion.','APPROVED',NULL FROM employees e JOIN document_types t ON t.code='PROMO' JOIN document_templates tt ON tt.code='TPL-PROMO' WHERE e.employee_number='EMP-0002' AND NOT EXISTS (SELECT 1 FROM document_drafts d WHERE d.title='Sample Promotion Draft');
INSERT INTO document_versions (draft_id,version_number,content,change_description,created_by) SELECT d.id,1,d.content,'Initial sample draft',NULL FROM document_drafts d WHERE d.title IN ('Sample COE Draft','Sample Promotion Draft') AND NOT EXISTS (SELECT 1 FROM document_versions v WHERE v.draft_id=d.id AND v.version_number=1);
INSERT INTO document_versions (draft_id,version_number,content,change_description,created_by) SELECT d.id,2,CONCAT(d.content,' Revised for HR review.'),'HR sample revision',NULL FROM document_drafts d WHERE d.title='Sample Promotion Draft' AND NOT EXISTS (SELECT 1 FROM document_versions v WHERE v.draft_id=d.id AND v.version_number=2);

-- ===== phase3-seed.sql =====
INSERT INTO profile_change_requests (employee_id,requester_user_id,request_type,requested_field,old_value,requested_value,reason,status) SELECT e.id,u.id,'CONTACT_UPDATE','phone',e.phone,'09179999999','Fictional pending ESS sample','PENDING' FROM employees e JOIN users u ON u.employee_id=e.id WHERE u.username='employee.benjie' AND NOT EXISTS (SELECT 1 FROM profile_change_requests r WHERE r.request_type='CONTACT_UPDATE' AND r.employee_id=e.id);
INSERT INTO profile_change_requests (employee_id,requester_user_id,request_type,requested_field,old_value,requested_value,reason,status,reviewed_by,reviewed_at,review_reason) SELECT e.id,u.id,'CONTACT_UPDATE','address',e.address,'Updated fictional address','Fictional approved ESS sample','APPROVED',hr.id,NOW(),'Approved sample request' FROM employees e JOIN users u ON u.employee_id=e.id JOIN users hr ON hr.username='hr.manager' WHERE u.username='employee.celeste' AND NOT EXISTS (SELECT 1 FROM profile_change_requests r WHERE r.request_type='CONTACT_UPDATE' AND r.employee_id=e.id AND r.status='APPROVED');
INSERT INTO notifications (user_id,title,message,type,related_record_id) SELECT u.id,'Profile request update','Your profile change request has been approved.','SUCCESS',r.id FROM users u JOIN profile_change_requests r ON r.requester_user_id=u.id WHERE u.username='employee.celeste' AND r.status='APPROVED' AND NOT EXISTS (SELECT 1 FROM notifications n WHERE n.related_record_id=r.id);

-- ===== security-migration.sql =====
-- Core HR basic security hardening.
-- Safe to run more than once on MariaDB 10.11. Does not delete existing rows.


ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER username,
  ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active;

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);

UPDATE users u
JOIN employees e ON e.id = u.employee_id
SET u.email = e.email
WHERE (u.email IS NULL OR u.email = '') AND e.email IS NOT NULL AND e.email <> '';

CREATE TABLE IF NOT EXISTS otp_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose VARCHAR(40) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  request_ip VARCHAR(45) NULL,
  INDEX idx_otp_user_purpose (user_id, purpose, created_at),
  INDEX idx_otp_expires (expires_at),
  INDEX idx_otp_ip_created (request_ip, created_at),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  request_ip VARCHAR(45) NULL,
  INDEX idx_reset_user (user_id, created_at),
  INDEX idx_reset_hash (token_hash),
  INDEX idx_reset_expires (expires_at),
  INDEX idx_reset_ip (request_ip, created_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  ip_address VARCHAR(45) NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_user_time (username, created_at),
  INDEX idx_login_ip_time (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- After import, set: UPDATE users SET email = 'your-admin@example.com' WHERE username = 'admin';
