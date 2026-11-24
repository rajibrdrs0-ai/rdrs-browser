-- =====================================================================
--  RTTI – Training Institute Management System
--  Database schema  (MySQL 8.0+ / MariaDB 10.6+, InnoDB, utf8mb4)
--  Database: rtti_training_management
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  LOCATION HIERARCHY  (Division > District > Upazila > Union > Village)
-- ---------------------------------------------------------------------
CREATE TABLE divisions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NULL,
  code VARCHAR(20) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_division_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE districts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  division_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NULL,
  code VARCHAR(20) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_district_name (name),
  CONSTRAINT fk_district_division FOREIGN KEY (division_id) REFERENCES divisions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE upazilas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_upazila (district_id, name),
  CONSTRAINT fk_upazila_district FOREIGN KEY (district_id) REFERENCES districts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE unions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  upazila_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_union (upazila_id, name),
  CONSTRAINT fk_union_upazila FOREIGN KEY (upazila_id) REFERENCES upazilas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE villages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  union_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_village (union_id, name),
  CONSTRAINT fk_village_union FOREIGN KEY (union_id) REFERENCES unions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  GENERAL MASTER DATA
-- ---------------------------------------------------------------------
CREATE TABLE genders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  name_bn VARCHAR(50) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gender (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE education_levels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_edu_level (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qualifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  education_level_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qualification (education_level_id, name),
  CONSTRAINT fk_qual_level FOREIGN KEY (education_level_id) REFERENCES education_levels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_acad_cat (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE academic_statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_acad_status (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trade_code (code),
  UNIQUE KEY uq_trade_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 'code' drives business logic (ACTIVE / INACTIVE / ARCHIVED)
CREATE TABLE course_statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(50) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT 'secondary',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cs_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 'code' drives business logic (UPCOMING / RUNNING / COMPLETED / CANCELLED / ON_HOLD)
CREATE TABLE batch_statuses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(50) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT 'secondary',
  allows_enrollment TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bs_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE designations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_designation (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_department (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_doc_cat (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grade_scales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grade VARCHAR(10) NOT NULL,
  min_percent DECIMAL(5,2) NOT NULL,
  max_percent DECIMAL(5,2) NOT NULL,
  grade_point DECIMAL(3,2) NOT NULL DEFAULT 0,
  is_pass TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grade (grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CENTERS  (Head Office + district training centers)
-- ---------------------------------------------------------------------
CREATE TABLE centers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_head_office TINYINT(1) NOT NULL DEFAULT 0,
  division_id INT UNSIGNED NULL,
  district_id INT UNSIGNED NOT NULL,
  upazila_id INT UNSIGNED NULL,
  address VARCHAR(255) NULL,
  contact_person VARCHAR(100) NULL,
  mobile VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_center_code (code),
  UNIQUE KEY uq_center_name (name),
  KEY idx_center_district (district_id),
  CONSTRAINT fk_center_division FOREIGN KEY (division_id) REFERENCES divisions(id),
  CONSTRAINT fk_center_district FOREIGN KEY (district_id) REFERENCES districts(id),
  CONSTRAINT fk_center_upazila FOREIGN KEY (upazila_id) REFERENCES upazilas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ACCOUNTS MASTER
-- ---------------------------------------------------------------------
CREATE TABLE payment_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  requires_reference TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pm (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bank (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cash / bank / mobile-banking accounts (optionally tied to a center)
CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  account_type ENUM('cash','bank','mobile') NOT NULL DEFAULT 'cash',
  bank_id INT UNSIGNED NULL,
  account_no VARCHAR(60) NULL,
  center_id INT UNSIGNED NULL,
  opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account (name),
  CONSTRAINT fk_account_bank FOREIGN KEY (bank_id) REFERENCES banks(id),
  CONSTRAINT fk_account_center FOREIGN KEY (center_id) REFERENCES centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_heads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  head_type ENUM('income','expense','asset','liability') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ah_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE income_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inc_cat (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exp_cat (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_heads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_category_id INT UNSIGNED NOT NULL,
  account_head_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exp_head (name),
  CONSTRAINT fk_eh_cat FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_eh_ah FOREIGN KEY (account_head_id) REFERENCES account_heads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE financial_years (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(20) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fy (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voucher_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  name VARCHAR(60) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vt (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SECURITY: USERS, ROLES, PERMISSIONS  (RBAC + center scope)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(60) NOT NULL,
  description VARCHAR(255) NULL,
  all_centers TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Head-office role: sees every center',
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_role_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(50) NOT NULL,
  action VARCHAR(30) NOT NULL,
  slug VARCHAR(90) NOT NULL,
  label VARCHAR(120) NOT NULL,
  UNIQUE KEY uq_perm_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  STAFF  (trainers are staff with is_trainer = 1)
-- ---------------------------------------------------------------------
CREATE TABLE staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  designation_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  center_id INT UNSIGNED NOT NULL,
  gender_id INT UNSIGNED NULL,
  mobile VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  nid VARCHAR(30) NULL,
  joining_date DATE NULL,
  photo VARCHAR(255) NULL,
  present_address VARCHAR(255) NULL,
  permanent_address VARCHAR(255) NULL,
  is_trainer TINYINT(1) NOT NULL DEFAULT 0,
  trainer_trade_id INT UNSIGNED NULL,
  employment_status ENUM('active','on_leave','resigned','terminated','retired') NOT NULL DEFAULT 'active',
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_emp_code (employee_code),
  KEY idx_staff_name (name),
  KEY idx_staff_mobile (mobile),
  KEY idx_staff_center (center_id),
  CONSTRAINT fk_staff_desig FOREIGN KEY (designation_id) REFERENCES designations(id),
  CONSTRAINT fk_staff_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_staff_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_staff_gender FOREIGN KEY (gender_id) REFERENCES genders(id),
  CONSTRAINT fk_staff_trade FOREIGN KEY (trainer_trade_id) REFERENCES trades(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(120) NULL,
  mobile VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  staff_id INT UNSIGNED NULL,
  default_center_id INT UNSIGNED NULL,
  language CHAR(2) NOT NULL DEFAULT 'en',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  session_token VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_user_email (email),
  CONSTRAINT fk_user_staff FOREIGN KEY (staff_id) REFERENCES staff(id),
  CONSTRAINT fk_user_center FOREIGN KEY (default_center_id) REFERENCES centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_centers (
  user_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, center_id),
  CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uc_center FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  STUDENTS  (one permanent lifetime ID per person)
-- ---------------------------------------------------------------------
CREATE TABLE students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  name_bn VARCHAR(150) NULL,
  father_name VARCHAR(150) NULL,
  mother_name VARCHAR(150) NULL,
  date_of_birth DATE NULL,
  gender_id INT UNSIGNED NULL,
  nid VARCHAR(30) NULL,
  birth_reg_no VARCHAR(30) NULL,
  mobile VARCHAR(20) NOT NULL,
  alt_mobile VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  present_address VARCHAR(255) NULL,
  permanent_address VARCHAR(255) NULL,
  district_id INT UNSIGNED NULL,
  upazila_id INT UNSIGNED NULL,
  education_level_id INT UNSIGNED NULL,
  qualification_details VARCHAR(255) NULL,
  occupation VARCHAR(100) NULL,
  photo VARCHAR(255) NULL,
  emergency_contact_name VARCHAR(120) NULL,
  emergency_contact_mobile VARCHAR(20) NULL,
  registration_date DATE NOT NULL,
  center_id INT UNSIGNED NOT NULL COMMENT 'Registration center',
  status ENUM('active','inactive','alumni','blacklisted') NOT NULL DEFAULT 'active',
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  deleted_by INT UNSIGNED NULL,
  UNIQUE KEY uq_student_code (student_code),
  KEY idx_student_name (name),
  KEY idx_student_father (father_name),
  KEY idx_student_mobile (mobile),
  KEY idx_student_nid (nid),
  KEY idx_student_brn (birth_reg_no),
  KEY idx_student_center (center_id),
  KEY idx_student_regdate (registration_date),
  CONSTRAINT fk_student_gender FOREIGN KEY (gender_id) REFERENCES genders(id),
  CONSTRAINT fk_student_district FOREIGN KEY (district_id) REFERENCES districts(id),
  CONSTRAINT fk_student_upazila FOREIGN KEY (upazila_id) REFERENCES upazilas(id),
  CONSTRAINT fk_student_edu FOREIGN KEY (education_level_id) REFERENCES education_levels(id),
  CONSTRAINT fk_student_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_student_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  document_category_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(80) NULL,
  file_size INT UNSIGNED NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  remarks VARCHAR(255) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_doc_student (student_id),
  CONSTRAINT fk_doc_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_doc_cat FOREIGN KEY (document_category_id) REFERENCES document_categories(id),
  CONSTRAINT fk_doc_verifier FOREIGN KEY (verified_by) REFERENCES users(id),
  CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ACADEMICS: COURSES, SUBJECTS, FEE STRUCTURE, BATCHES
-- ---------------------------------------------------------------------
CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  academic_category_id INT UNSIGNED NULL,
  trade_id INT UNSIGNED NOT NULL,
  description TEXT NULL,
  duration INT UNSIGNED NOT NULL DEFAULT 1,
  duration_unit ENUM('hours','days','weeks','months') NOT NULL DEFAULT 'months',
  fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  min_education_level_id INT UNSIGNED NULL,
  objectives TEXT NULL,
  outline TEXT NULL,
  pass_mark_percent DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  min_attendance_percent DECIMAL(5,2) NOT NULL DEFAULT 75.00,
  course_status_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_course_code (code),
  KEY idx_course_name (name),
  CONSTRAINT fk_course_cat FOREIGN KEY (academic_category_id) REFERENCES academic_categories(id),
  CONSTRAINT fk_course_trade FOREIGN KEY (trade_id) REFERENCES trades(id),
  CONSTRAINT fk_course_edu FOREIGN KEY (min_education_level_id) REFERENCES education_levels(id),
  CONSTRAINT fk_course_status FOREIGN KEY (course_status_id) REFERENCES course_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  theory_full_marks DECIMAL(6,2) NOT NULL DEFAULT 0,
  practical_full_marks DECIMAL(6,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_course_subject (course_id, name),
  CONSTRAINT fk_cs_course FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cd_course FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Center-specific fee overrides; falls back to courses.fee
CREATE TABLE fee_structures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NULL,
  fee DECIMAL(12,2) NOT NULL,
  effective_from DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_fs (course_id, center_id, effective_from),
  CONSTRAINT fk_fs_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_fs_center FOREIGN KEY (center_id) REFERENCES centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_code VARCHAR(40) NOT NULL,
  name VARCHAR(150) NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  trainer_id INT UNSIGNED NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  class_days VARCHAR(60) NULL,
  class_start_time TIME NULL,
  class_end_time TIME NULL,
  class_schedule VARCHAR(255) NULL,
  room VARCHAR(60) NULL,
  max_students INT UNSIGNED NOT NULL DEFAULT 25,
  batch_status_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NULL,
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_batch_code (batch_code),
  KEY idx_batch_name (name),
  KEY idx_batch_center (center_id),
  KEY idx_batch_dates (start_date, end_date),
  CONSTRAINT fk_batch_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_batch_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_batch_trainer FOREIGN KEY (trainer_id) REFERENCES staff(id),
  CONSTRAINT fk_batch_status FOREIGN KEY (batch_status_id) REFERENCES batch_statuses(id),
  CONSTRAINT chk_batch_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly class routine (trainer workload)
CREATE TABLE class_routines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sun .. 6=Sat',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  subject VARCHAR(150) NULL,
  trainer_id INT UNSIGNED NULL,
  room VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cr_batch (batch_id),
  CONSTRAINT fk_cr_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_cr_trainer FOREIGN KEY (trainer_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ENROLLMENTS  (student x batch, with fee terms & outcome)
-- ---------------------------------------------------------------------
CREATE TABLE enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_code VARCHAR(30) NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  enrollment_date DATE NOT NULL,
  course_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_reason VARCHAR(255) NULL,
  payable_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Maintained by fee collection transactions',
  due_amount DECIMAL(12,2) GENERATED ALWAYS AS (payable_amount - paid_amount) STORED,
  status ENUM('pending','active','completed','dropped','cancelled','transferred') NOT NULL DEFAULT 'active',
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  cancelled_by INT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  duplicate_override_reason VARCHAR(255) NULL,
  transferred_from_id INT UNSIGNED NULL,
  result_percent DECIMAL(6,2) NULL,
  result_grade VARCHAR(10) NULL,
  result_status ENUM('pass','fail','absent') NULL,
  completion_date DATE NULL,
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enrollment_code (enrollment_code),
  KEY idx_enr_student (student_id),
  KEY idx_enr_batch (batch_id, status),
  KEY idx_enr_center (center_id),
  KEY idx_enr_date (enrollment_date),
  CONSTRAINT fk_enr_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_enr_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_enr_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_enr_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_enr_transfer FOREIGN KEY (transferred_from_id) REFERENCES enrollments(id),
  CONSTRAINT chk_enr_amounts CHECK (discount >= 0 AND payable_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ASSIGNMENTS
-- ---------------------------------------------------------------------
CREATE TABLE assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NULL COMMENT 'NULL = all batches of the course',
  instructions TEXT NULL,
  deadline DATETIME NOT NULL,
  total_marks DECIMAL(6,2) NOT NULL DEFAULT 10,
  attachment VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_asg_batch (batch_id),
  CONSTRAINT fk_asg_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_asg_batch FOREIGN KEY (batch_id) REFERENCES batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assignment_submissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  submitted_at DATETIME NULL,
  submission_text TEXT NULL,
  file_path VARCHAR(255) NULL,
  marks DECIMAL(6,2) NULL,
  feedback TEXT NULL,
  status ENUM('pending','submitted','late','evaluated') NOT NULL DEFAULT 'pending',
  evaluated_by INT UNSIGNED NULL,
  evaluated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_submission (assignment_id, enrollment_id),
  CONSTRAINT fk_sub_asg FOREIGN KEY (assignment_id) REFERENCES assignments(id),
  CONSTRAINT fk_sub_enr FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ATTENDANCE  (one sheet per batch per day = one class session)
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  topic VARCHAR(255) NULL,
  trainer_id INT UNSIGNED NULL,
  taken_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_att (batch_id, attendance_date),
  CONSTRAINT fk_att_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_att_trainer FOREIGN KEY (trainer_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_details (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT UNSIGNED NOT NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  status ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
  remarks VARCHAR(255) NULL,
  UNIQUE KEY uq_att_det (attendance_id, enrollment_id),
  KEY idx_attdet_enr (enrollment_id),
  CONSTRAINT fk_attdet_att FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
  CONSTRAINT fk_attdet_enr FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff_attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
  remarks VARCHAR(255) NULL,
  taken_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_att (staff_id, attendance_date),
  CONSTRAINT fk_satt_staff FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  EXAMINATIONS & RESULTS
-- ---------------------------------------------------------------------
CREATE TABLE examinations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  exam_type ENUM('class_test','midterm','final','practical','assessment') NOT NULL DEFAULT 'final',
  exam_date DATE NOT NULL,
  theory_full_marks DECIMAL(6,2) NOT NULL DEFAULT 60 COMMENT 'Used when course has no subjects',
  practical_full_marks DECIMAL(6,2) NOT NULL DEFAULT 40,
  is_final TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Final exam drives course result & certificate',
  status ENUM('scheduled','marks_entry','published') NOT NULL DEFAULT 'scheduled',
  published_at DATETIME NULL,
  published_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_exam_batch (batch_id),
  CONSTRAINT fk_exam_batch FOREIGN KEY (batch_id) REFERENCES batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE examination_results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  examination_id INT UNSIGNED NOT NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = whole exam (no subjects)',
  theory_marks DECIMAL(6,2) NULL,
  practical_marks DECIMAL(6,2) NULL,
  is_absent TINYINT(1) NOT NULL DEFAULT 0,
  remarks VARCHAR(255) NULL,
  entered_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_result (examination_id, enrollment_id, subject_id),
  CONSTRAINT fk_er_exam FOREIGN KEY (examination_id) REFERENCES examinations(id),
  CONSTRAINT fk_er_enr FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  CERTIFICATES (unique number + public verification token / QR)
-- ---------------------------------------------------------------------
CREATE TABLE certificate_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  heading VARCHAR(200) NOT NULL DEFAULT 'Certificate of Completion',
  body_text TEXT NOT NULL,
  signatory1_name VARCHAR(120) NULL,
  signatory1_title VARCHAR(120) NULL,
  signatory2_name VARCHAR(120) NULL,
  signatory2_title VARCHAR(120) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ct_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certificate_no VARCHAR(40) NOT NULL,
  verification_token CHAR(32) NOT NULL,
  enrollment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  batch_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NULL,
  completion_date DATE NOT NULL,
  issue_date DATE NOT NULL,
  grade VARCHAR(10) NULL,
  status ENUM('issued','cancelled') NOT NULL DEFAULT 'issued',
  issued_by INT UNSIGNED NULL,
  cancelled_by INT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  cancel_reason VARCHAR(255) NULL,
  print_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cert_no (certificate_no),
  UNIQUE KEY uq_cert_token (verification_token),
  KEY idx_cert_enr (enrollment_id),
  CONSTRAINT fk_cert_enr FOREIGN KEY (enrollment_id) REFERENCES enrollments(id),
  CONSTRAINT fk_cert_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_cert_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_cert_batch FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_cert_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_cert_tpl FOREIGN KEY (template_id) REFERENCES certificate_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificate_prints (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certificate_id INT UNSIGNED NOT NULL,
  printed_by INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  printed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cp_cert FOREIGN KEY (certificate_id) REFERENCES certificates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  FINANCE: FEE COLLECTION (receipts) + LEDGER (immutable)
-- ---------------------------------------------------------------------
CREATE TABLE fee_collections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NOT NULL,
  transaction_ref VARCHAR(80) NULL COMMENT 'Bank / bKash / Nagad transaction ID',
  enrollment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  payment_date DATE NOT NULL,
  payable_snapshot DECIMAL(12,2) NOT NULL COMMENT 'Payable at time of receipt',
  previous_paid DECIMAL(12,2) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  due_after DECIMAL(12,2) NOT NULL,
  payment_method_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NULL,
  received_by INT UNSIGNED NOT NULL,
  remarks VARCHAR(255) NULL,
  status ENUM('posted','reversed') NOT NULL DEFAULT 'posted',
  reversed_by INT UNSIGNED NULL,
  reversed_at DATETIME NULL,
  reversal_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipt_no (receipt_no),
  KEY idx_fc_enr (enrollment_id),
  KEY idx_fc_student (student_id),
  KEY idx_fc_center_date (center_id, payment_date),
  CONSTRAINT fk_fc_enr FOREIGN KEY (enrollment_id) REFERENCES enrollments(id),
  CONSTRAINT fk_fc_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_fc_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_fc_pm FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
  CONSTRAINT fk_fc_account FOREIGN KEY (account_id) REFERENCES accounts(id),
  CONSTRAINT fk_fc_user FOREIGN KEY (received_by) REFERENCES users(id),
  CONSTRAINT chk_fc_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only student ledger. Never updated or deleted by the application.
CREATE TABLE payment_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  fee_collection_id INT UNSIGNED NULL,
  txn_date DATE NOT NULL,
  txn_type ENUM('charge','discount','payment','reversal','adjustment') NOT NULL,
  debit DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Increases what the student owes',
  credit DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Decreases what the student owes',
  narration VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pt_enr (enrollment_id),
  KEY idx_pt_student (student_id),
  KEY idx_pt_center_date (center_id, txn_date),
  CONSTRAINT fk_pt_enr FOREIGN KEY (enrollment_id) REFERENCES enrollments(id),
  CONSTRAINT fk_pt_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_pt_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_pt_fc FOREIGN KEY (fee_collection_id) REFERENCES fee_collections(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_no VARCHAR(30) NOT NULL,
  expense_date DATE NOT NULL,
  center_id INT UNSIGNED NOT NULL,
  expense_head_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_method_id INT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NULL,
  paid_to VARCHAR(150) NULL,
  supporting_document VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  prepared_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  rejection_reason VARCHAR(255) NULL,
  remarks VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_voucher (voucher_no),
  KEY idx_exp_center_date (center_id, expense_date),
  KEY idx_exp_status (status),
  CONSTRAINT fk_exp_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_exp_head FOREIGN KEY (expense_head_id) REFERENCES expense_heads(id),
  CONSTRAINT fk_exp_pm FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
  CONSTRAINT fk_exp_account FOREIGN KEY (account_id) REFERENCES accounts(id),
  CONSTRAINT fk_exp_prep FOREIGN KEY (prepared_by) REFERENCES users(id),
  CONSTRAINT fk_exp_appr FOREIGN KEY (approved_by) REFERENCES users(id),
  CONSTRAINT chk_exp_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  ASSETS
-- ---------------------------------------------------------------------
CREATE TABLE asset_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(10) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asset_cat (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  asset_category_id INT UNSIGNED NOT NULL,
  serial_no VARCHAR(100) NULL,
  brand_model VARCHAR(150) NULL,
  purchase_date DATE NULL,
  purchase_price DECIMAL(12,2) NULL,
  supplier VARCHAR(150) NULL,
  center_id INT UNSIGNED NOT NULL,
  assigned_staff_id INT UNSIGNED NULL,
  asset_condition ENUM('new','good','fair','poor','damaged') NOT NULL DEFAULT 'good',
  warranty_expiry DATE NULL,
  status ENUM('in_use','in_store','under_maintenance','disposed','lost') NOT NULL DEFAULT 'in_use',
  disposal_date DATE NULL,
  disposal_note VARCHAR(255) NULL,
  remarks TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_asset_code (asset_code),
  KEY idx_asset_center (center_id),
  KEY idx_asset_serial (serial_no),
  CONSTRAINT fk_asset_cat FOREIGN KEY (asset_category_id) REFERENCES asset_categories(id),
  CONSTRAINT fk_asset_center FOREIGN KEY (center_id) REFERENCES centers(id),
  CONSTRAINT fk_asset_staff FOREIGN KEY (assigned_staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_id INT UNSIGNED NOT NULL,
  movement_type ENUM('register','assign','transfer','return','dispose') NOT NULL,
  from_center_id INT UNSIGNED NULL,
  to_center_id INT UNSIGNED NULL,
  staff_id INT UNSIGNED NULL,
  movement_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_aa_asset (asset_id),
  CONSTRAINT fk_aa_asset FOREIGN KEY (asset_id) REFERENCES assets(id),
  CONSTRAINT fk_aa_from FOREIGN KEY (from_center_id) REFERENCES centers(id),
  CONSTRAINT fk_aa_to FOREIGN KEY (to_center_id) REFERENCES centers(id),
  CONSTRAINT fk_aa_staff FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_maintenance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_id INT UNSIGNED NOT NULL,
  maintenance_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  vendor VARCHAR(150) NULL,
  next_due_date DATE NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_am_asset FOREIGN KEY (asset_id) REFERENCES assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SYSTEM: SEQUENCES, SETTINGS, LOGS, NOTIFICATIONS
-- ---------------------------------------------------------------------
-- Gap-free number generator (row-locked inside transactions)
CREATE TABLE sequences (
  seq_key VARCHAR(60) NOT NULL PRIMARY KEY,
  last_value INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  setting_group VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  center_id INT UNSIGNED NULL,
  module VARCHAR(50) NOT NULL,
  action VARCHAR(30) NOT NULL,
  record_id VARCHAR(40) NULL,
  description VARCHAR(255) NULL,
  old_values MEDIUMTEXT NULL,
  new_values MEDIUMTEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_module (module, record_id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  username VARCHAR(60) NOT NULL,
  success TINYINT(1) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  note VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ll_user (user_id),
  KEY idx_ll_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL COMMENT 'NULL = broadcast to permission holders',
  center_id INT UNSIGNED NULL,
  permission_slug VARCHAR(90) NULL,
  title VARCHAR(150) NOT NULL,
  message VARCHAR(255) NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
