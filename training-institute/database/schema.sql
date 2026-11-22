-- =====================================================================
--  RDRS Technical Training Institute (RTTI) — Management System
--  MySQL 8.0+ schema  (InnoDB · utf8mb4 · FK · CHECK · Triggers · Views)
--
--  Replaces the PHPRunner "RDRSTTI" database. Main fixes:
--   * Real user roles with district / center data scoping
--   * Hashed passwords only (old `shw_password` plain-text column removed)
--   * Every center / course / student reference is a foreign key, not text
--   * New modules: student attendance, class routine, assessments & results,
--     fee invoices + money receipts + dues, waiver approval, center cash
--     ledger, staff HR, assets, job placement, notices, audit trail
--
--  Install:  mysql -u root -p < database/schema.sql
--  Audit context per connection: SET @app_user_id, @app_center_id, @app_ip
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `rtti_management`
  DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci;
USE `rtti_management`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+06:00';
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- =====================================================================
-- 1. GEOGRAPHY
-- =====================================================================
DROP TABLE IF EXISTS `divisions`;
CREATE TABLE `divisions` (
  `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_en`    VARCHAR(60) NOT NULL,
  `name_bn`    VARCHAR(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_divisions_name` (`name_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `districts`;
CREATE TABLE `districts` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` TINYINT UNSIGNED NOT NULL,
  `name_en`     VARCHAR(60) NOT NULL,
  `name_bn`     VARCHAR(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_districts_name` (`name_en`),
  KEY `idx_districts_division` (`division_id`),
  CONSTRAINT `fk_districts_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `upazilas`;
CREATE TABLE `upazilas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `district_id` SMALLINT UNSIGNED NOT NULL,
  `name_en`     VARCHAR(80) NOT NULL,
  `name_bn`     VARCHAR(80) NOT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_upazilas_name` (`district_id`, `name_en`),
  CONSTRAINT `fk_upazilas_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces loc_dis (district + upazila text pairs)';

-- =====================================================================
-- 2. ORGANISATION
-- =====================================================================
DROP TABLE IF EXISTS `training_centers`;
CREATE TABLE `training_centers` (
  `id`            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(12)  NOT NULL COMMENT 'Used in registration/receipt numbers, e.g. RNG',
  `name_en`       VARCHAR(150) NOT NULL,
  `name_bn`       VARCHAR(150) NOT NULL,
  `center_type`   ENUM('head_office','training_center') NOT NULL DEFAULT 'training_center',
  `district_id`   SMALLINT UNSIGNED NOT NULL,
  `upazila_id`    INT UNSIGNED NULL,
  `address`       VARCHAR(255) NOT NULL,
  `phone`         VARCHAR(20)  NULL,
  `email`         VARCHAR(150) NULL,
  `manager_user_id` INT UNSIGNED NULL,
  `registration_no` VARCHAR(60) NULL COMMENT 'BTEB / NSDA registration of the center',
  `seat_capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `has_hostel`    TINYINT(1) NOT NULL DEFAULT 0,
  `established_on` DATE NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_centers_code` (`code`),
  KEY `idx_centers_district` (`district_id`, `is_active`),
  CONSTRAINT `fk_centers_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_centers_upazila`  FOREIGN KEY (`upazila_id`)  REFERENCES `upazilas` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_centers_manager`  FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces center_name / academic (free-text center names)';

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `center_id`  SMALLINT UNSIGNED NOT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `room_type`  ENUM('classroom','lab','workshop','hall') NOT NULL DEFAULT 'classroom',
  `capacity`   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rooms_name` (`center_id`, `name`),
  CONSTRAINT `fk_rooms_center` FOREIGN KEY (`center_id`) REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fiscal_years`;
CREATE TABLE `fiscal_years` (
  `id`         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date`   DATE NOT NULL,
  `is_closed`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fy_name` (`name`),
  CONSTRAINT `chk_fy_dates` CHECK (`end_date` > `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. USERS, ROLES & PERMISSIONS
--    scope: global   = all centers (Head Office)
--           district = centers inside the user's assigned district(s)
--           center   = the user's assigned center(s)
--           self     = own records only (trainer: own batches, student: own data)
-- =====================================================================
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(40) NOT NULL,
  `name_en`     VARCHAR(80) NOT NULL,
  `name_bn`     VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NULL,
  `scope`       ENUM('global','district','center','self') NOT NULL DEFAULT 'center',
  `level`       TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'Lower = more senior. A user may only manage users of a higher level number.',
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`     VARCHAR(80)  NOT NULL,
  `module`   VARCHAR(40)  NOT NULL,
  `name_en`  VARCHAR(120) NOT NULL,
  `name_bn`  VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id`       TINYINT UNSIGNED  NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles` (`id`)       ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`              TINYINT UNSIGNED NOT NULL,
  `center_id`            SMALLINT UNSIGNED NULL COMMENT 'Home center (center-scoped roles, trainers, students)',
  `district_id`          SMALLINT UNSIGNED NULL COMMENT 'Home district (district coordinators)',
  `staff_id`             INT UNSIGNED NULL,
  `student_id`           INT UNSIGNED NULL,
  `name`                 VARCHAR(120) NOT NULL,
  `name_bn`              VARCHAR(120) NULL,
  `username`             VARCHAR(50)  NOT NULL,
  `email`                VARCHAR(150) NULL,
  `mobile`               VARCHAR(20)  NOT NULL,
  `password_hash`        VARCHAR(255) NOT NULL COMMENT 'Argon2id/bcrypt only — never plain text',
  `locale`               ENUM('bn','en') NOT NULL DEFAULT 'bn',
  `status`               ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `failed_login_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`         DATETIME NULL,
  `last_login_at`        DATETIME NULL,
  `last_login_ip`        VARCHAR(45) NULL,
  `password_changed_at`  DATETIME NULL,
  `created_by`           INT UNSIGNED NULL,
  `updated_by`           INT UNSIGNED NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`           TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  UNIQUE KEY `uq_users_staff` (`staff_id`),
  UNIQUE KEY `uq_users_student` (`student_id`),
  KEY `idx_users_role` (`role_id`, `status`),
  KEY `idx_users_center` (`center_id`),
  KEY `idx_users_district` (`district_id`),
  CONSTRAINT `fk_users_role`     FOREIGN KEY (`role_id`)     REFERENCES `roles` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_users_center`   FOREIGN KEY (`center_id`)   REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_users_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_users_staff`    FOREIGN KEY (`staff_id`)    REFERENCES `staff` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_users_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`)         ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_users_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_users_updater`  FOREIGN KEY (`updated_by`)  REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `user_id`       INT UNSIGNED NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  `is_granted`    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = extra grant, 0 = explicit revoke',
  `granted_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `permission_id`),
  CONSTRAINT `fk_up_user`       FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_up_granter`    FOREIGN KEY (`granted_by`)    REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-user overrides on top of the role';

DROP TABLE IF EXISTS `user_district_access`;
CREATE TABLE `user_district_access` (
  `user_id`     INT UNSIGNED NOT NULL,
  `district_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `district_id`),
  CONSTRAINT `fk_uda_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_uda_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extra districts for district-scoped users';

DROP TABLE IF EXISTS `user_center_access`;
CREATE TABLE `user_center_access` (
  `user_id`   INT UNSIGNED NOT NULL,
  `center_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `center_id`),
  CONSTRAINT `fk_uca_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_uca_center` FOREIGN KEY (`center_id`) REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extra centers for center-scoped users';

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`   VARCHAR(150) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `successful`   TINYINT(1)   NOT NULL DEFAULT 0,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_identifier` (`identifier`, `attempted_at`),
  KEY `idx_la_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_token` (`token_hash`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces users.reset_password_token (now hashed + expiring)';

-- =====================================================================
-- 4. HR — STAFF & TRAINERS
-- =====================================================================
DROP TABLE IF EXISTS `designations`;
CREATE TABLE `designations` (
  `id`        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_en`   VARCHAR(80) NOT NULL,
  `name_bn`   VARCHAR(80) NOT NULL,
  `is_trainer` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_designations_name` (`name_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_code`   VARCHAR(20)  NOT NULL,
  `center_id`       SMALLINT UNSIGNED NOT NULL,
  `designation_id`  SMALLINT UNSIGNED NOT NULL,
  `name`            VARCHAR(120) NOT NULL,
  `name_bn`         VARCHAR(120) NULL,
  `gender`          ENUM('male','female','other') NOT NULL,
  `date_of_birth`   DATE NULL,
  `nid_no`          VARCHAR(20)  NULL,
  `mobile`          VARCHAR(20)  NOT NULL,
  `email`           VARCHAR(150) NULL,
  `address`         VARCHAR(255) NULL,
  `qualification`   VARCHAR(150) NULL,
  `trainer_certificate_no` VARCHAR(60) NULL COMMENT 'NSDA/BTEB trainer certification',
  `employment_type` ENUM('permanent','contractual','part_time','guest') NOT NULL DEFAULT 'permanent',
  `joining_date`    DATE NOT NULL,
  `leaving_date`    DATE NULL,
  `monthly_salary`  DECIMAL(12,2) NULL,
  `photo_path`      VARCHAR(255) NULL,
  `status`          ENUM('active','on_leave','resigned','terminated') NOT NULL DEFAULT 'active',
  `created_by`      INT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_code` (`employee_code`),
  UNIQUE KEY `uq_staff_mobile` (`mobile`),
  KEY `idx_staff_center` (`center_id`, `status`),
  CONSTRAINT `fk_staff_center`      FOREIGN KEY (`center_id`)      REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_staff_designation` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_staff_creator`     FOREIGN KEY (`created_by`)     REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces staff (center & designation were text)';

DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE `staff_attendance` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id`    INT UNSIGNED NOT NULL,
  `center_id`   SMALLINT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status`      ENUM('present','absent','late','leave','holiday','field_visit') NOT NULL,
  `check_in`    TIME NULL,
  `check_out`   TIME NULL,
  `remarks`     VARCHAR(150) NULL,
  `recorded_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_att_day` (`staff_id`, `attendance_date`),
  KEY `idx_staff_att_center` (`center_id`, `attendance_date`),
  CONSTRAINT `fk_satt_staff`    FOREIGN KEY (`staff_id`)    REFERENCES `staff` (`id`)            ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_satt_center`   FOREIGN KEY (`center_id`)   REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_satt_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One row per staff per day (old table allowed duplicates)';

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id`    INT UNSIGNED NOT NULL,
  `leave_type`  ENUM('casual','sick','annual','maternity','unpaid','other') NOT NULL,
  `from_date`   DATE NOT NULL,
  `to_date`     DATE NOT NULL,
  `reason`      VARCHAR(255) NOT NULL,
  `status`      ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_staff` (`staff_id`, `status`),
  CONSTRAINT `fk_leave_staff`    FOREIGN KEY (`staff_id`)    REFERENCES `staff` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_leave_dates`   CHECK (`to_date` >= `from_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. ACADEMIC SETUP — TRADES, COURSES, FEES, BATCHES, ROUTINE
-- =====================================================================
DROP TABLE IF EXISTS `trades`;
CREATE TABLE `trades` (
  `id`        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`      VARCHAR(12) NOT NULL,
  `name_en`   VARCHAR(120) NOT NULL,
  `name_bn`   VARCHAR(120) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trades_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces trade_info';

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(20)  NOT NULL,
  `trade_id`          SMALLINT UNSIGNED NOT NULL,
  `name_en`           VARCHAR(150) NOT NULL,
  `name_bn`           VARCHAR(150) NOT NULL,
  `course_type`       ENUM('short','long','nsda_cbt','bteb','customized') NOT NULL DEFAULT 'short',
  `nsqf_level`        TINYINT UNSIGNED NULL COMMENT 'National Skills Qualification Framework level 1-6',
  `duration_months`   DECIMAL(4,1) NOT NULL,
  `total_hours`       SMALLINT UNSIGNED NOT NULL,
  `min_qualification` VARCHAR(120) NULL,
  `min_age`           TINYINT UNSIGNED NULL,
  `max_age`           TINYINT UNSIGNED NULL,
  `min_attendance_percent` DECIMAL(5,2) NOT NULL DEFAULT 80.00 COMMENT 'Required to sit the final assessment',
  `pass_mark_percent` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  `description`       TEXT NULL,
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_code` (`code`),
  KEY `idx_courses_trade` (`trade_id`),
  CONSTRAINT `fk_courses_trade` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_courses_values` CHECK (`duration_months` > 0 AND `total_hours` > 0
                                        AND `min_attendance_percent` BETWEEN 0 AND 100 AND `pass_mark_percent` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Central course catalogue (old table copied courses per center as text)';

DROP TABLE IF EXISTS `course_modules`;
CREATE TABLE `course_modules` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`   INT UNSIGNED NOT NULL,
  `sequence_no` SMALLINT UNSIGNED NOT NULL,
  `title_en`    VARCHAR(200) NOT NULL,
  `title_bn`    VARCHAR(200) NULL,
  `theory_hours`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `practical_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_modules_seq` (`course_id`, `sequence_no`),
  CONSTRAINT `fk_cm_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Curriculum units / competencies';

DROP TABLE IF EXISTS `fee_heads`;
CREATE TABLE `fee_heads` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(20) NOT NULL,
  `name_en`     VARCHAR(80) NOT NULL,
  `name_bn`     VARCHAR(80) NOT NULL,
  `is_refundable` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_heads_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admission, course, residential, exam, certificate …';

DROP TABLE IF EXISTS `course_fees`;
CREATE TABLE `course_fees` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`   INT UNSIGNED NOT NULL,
  `fee_head_id` SMALLINT UNSIGNED NOT NULL,
  `center_id`   SMALLINT UNSIGNED NULL COMMENT 'NULL = default for all centers; a row with center_id overrides it',
  `amount`      DECIMAL(12,2) NOT NULL,
  `is_optional` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'e.g. residential fee only for hostel students',
  `effective_from` DATE NOT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_fee` (`course_id`, `fee_head_id`, `center_id`, `effective_from`),
  CONSTRAINT `fk_cf_course`   FOREIGN KEY (`course_id`)   REFERENCES `courses` (`id`)          ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_cf_head`     FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_cf_center`   FOREIGN KEY (`center_id`)   REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_cf_amount`  CHECK (`amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_code`      VARCHAR(30) NOT NULL COMMENT 'CENTER-COURSE-YYYY-NN',
  `center_id`       SMALLINT UNSIGNED NOT NULL,
  `course_id`       INT UNSIGNED NOT NULL,
  `name`            VARCHAR(100) NOT NULL,
  `shift`           ENUM('morning','day','evening','weekend') NOT NULL DEFAULT 'morning',
  `start_date`      DATE NOT NULL,
  `end_date`        DATE NOT NULL,
  `class_start_time` TIME NULL,
  `class_end_time`   TIME NULL,
  `class_days`      SET('sat','sun','mon','tue','wed','thu','fri') NOT NULL DEFAULT 'sat,sun,mon,tue,wed',
  `capacity`        SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  `room_id`         INT UNSIGNED NULL,
  `lead_trainer_id` INT UNSIGNED NULL,
  `funding_source`  VARCHAR(120) NULL COMMENT 'Self-financed, donor project, government scheme …',
  `status`          ENUM('planned','admission_open','running','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_by`      INT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batches_code` (`batch_code`),
  KEY `idx_batches_center` (`center_id`, `status`, `start_date`),
  KEY `idx_batches_course` (`course_id`),
  KEY `idx_batches_trainer` (`lead_trainer_id`),
  CONSTRAINT `fk_batches_center`  FOREIGN KEY (`center_id`)       REFERENCES `training_centers` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_batches_course`  FOREIGN KEY (`course_id`)       REFERENCES `courses` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_batches_room`    FOREIGN KEY (`room_id`)         REFERENCES `rooms` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_batches_trainer` FOREIGN KEY (`lead_trainer_id`) REFERENCES `staff` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_batches_creator` FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_batches_dates`  CHECK (`end_date` >= `start_date` AND `capacity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces batch + course_batch + batch_status';

DROP TABLE IF EXISTS `batch_trainers`;
CREATE TABLE `batch_trainers` (
  `batch_id`  INT UNSIGNED NOT NULL,
  `staff_id`  INT UNSIGNED NOT NULL,
  `role`      ENUM('lead','assistant','guest') NOT NULL DEFAULT 'assistant',
  PRIMARY KEY (`batch_id`, `staff_id`),
  KEY `idx_bt_staff` (`staff_id`),
  CONSTRAINT `fk_bt_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_bt_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`)   ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trainers see only batches listed here (role scope = self)';

DROP TABLE IF EXISTS `class_routines`;
CREATE TABLE `class_routines` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`   INT UNSIGNED NOT NULL,
  `weekday`    ENUM('sat','sun','mon','tue','wed','thu','fri') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time`   TIME NOT NULL,
  `module_id`  INT UNSIGNED NULL,
  `staff_id`   INT UNSIGNED NULL,
  `room_id`    INT UNSIGNED NULL,
  `session_type` ENUM('theory','practical','both') NOT NULL DEFAULT 'both',
  PRIMARY KEY (`id`),
  KEY `idx_routine_batch` (`batch_id`, `weekday`),
  KEY `idx_routine_staff` (`staff_id`, `weekday`),
  CONSTRAINT `fk_cr_batch`  FOREIGN KEY (`batch_id`)  REFERENCES `batches` (`id`)        ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_cr_module` FOREIGN KEY (`module_id`) REFERENCES `course_modules` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_cr_staff`  FOREIGN KEY (`staff_id`)  REFERENCES `staff` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_cr_room`   FOREIGN KEY (`room_id`)   REFERENCES `rooms` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_cr_time`  CHECK (`end_time` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Weekly class routine';

DROP TABLE IF EXISTS `class_sessions`;
CREATE TABLE `class_sessions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`     INT UNSIGNED NOT NULL,
  `session_date` DATE NOT NULL,
  `start_time`   TIME NULL,
  `end_time`     TIME NULL,
  `module_id`    INT UNSIGNED NULL,
  `staff_id`     INT UNSIGNED NULL COMMENT 'Trainer who took the class',
  `topic`        VARCHAR(200) NULL,
  `status`       ENUM('held','cancelled','holiday') NOT NULL DEFAULT 'held',
  `is_locked`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Attendance locked after approval',
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_session` (`batch_id`, `session_date`, `start_time`),
  KEY `idx_cs_staff` (`staff_id`, `session_date`),
  CONSTRAINT `fk_cs_batch`   FOREIGN KEY (`batch_id`)   REFERENCES `batches` (`id`)        ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_cs_module`  FOREIGN KEY (`module_id`)  REFERENCES `course_modules` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_cs_staff`   FOREIGN KEY (`staff_id`)   REFERENCES `staff` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_cs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Classes actually held (attendance is taken per session)';

-- =====================================================================
-- 6. STUDENTS (TRAINEES) & ENROLMENT
-- =====================================================================
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_no`    VARCHAR(25)  NOT NULL COMMENT 'CENTER-YYYY-NNNNN, permanent per person',
  `center_id`          SMALLINT UNSIGNED NOT NULL COMMENT 'Registering center',
  `name_en`            VARCHAR(120) NOT NULL,
  `name_bn`            VARCHAR(120) NULL,
  `gender`             ENUM('male','female','other') NOT NULL,
  `date_of_birth`      DATE NOT NULL,
  `nid_no`             VARCHAR(20)  NULL,
  `birth_reg_no`       VARCHAR(20)  NULL,
  `mobile`             VARCHAR(20)  NOT NULL,
  `email`              VARCHAR(150) NULL,
  `religion`           ENUM('islam','hinduism','buddhism','christianity','other') NULL,
  `marital_status`     ENUM('single','married','widowed','divorced') NULL,
  `father_name`        VARCHAR(120) NULL,
  `father_mobile`      VARCHAR(20)  NULL,
  `mother_name`        VARCHAR(120) NULL,
  `mother_mobile`      VARCHAR(20)  NULL,
  `guardian_name`      VARCHAR(120) NULL,
  `guardian_mobile`    VARCHAR(20)  NULL,
  `guardian_relation`  VARCHAR(40)  NULL,
  `permanent_upazila_id` INT UNSIGNED NULL,
  `permanent_address`  VARCHAR(255) NULL,
  `present_upazila_id` INT UNSIGNED NULL,
  `present_address`    VARCHAR(255) NULL,
  `highest_education`  VARCHAR(80)  NULL,
  `disability`         ENUM('none','visual','hearing','physical','intellectual','multiple','other') NOT NULL DEFAULT 'none',
  `is_ethnic_minority` TINYINT(1) NOT NULL DEFAULT 0,
  `household_income_monthly` DECIMAL(12,2) NULL COMMENT 'For donor / poverty-targeting reports',
  `employment_status_before` ENUM('unemployed','employed','self_employed','student','other') NULL,
  `photo_path`         VARCHAR(255) NULL,
  `status`             ENUM('active','inactive','blacklisted') NOT NULL DEFAULT 'active',
  `created_by`         INT UNSIGNED NULL,
  `updated_by`         INT UNSIGNED NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_regno` (`registration_no`),
  UNIQUE KEY `uq_students_nid` (`nid_no`),
  UNIQUE KEY `uq_students_brn` (`birth_reg_no`),
  KEY `idx_students_center` (`center_id`, `status`),
  KEY `idx_students_mobile` (`mobile`),
  KEY `idx_students_name` (`name_en`),
  CONSTRAINT `fk_students_center`  FOREIGN KEY (`center_id`)            REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_students_perm_up` FOREIGN KEY (`permanent_upazila_id`) REFERENCES `upazilas` (`id`)         ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_students_pres_up` FOREIGN KEY (`present_upazila_id`)   REFERENCES `upazilas` (`id`)         ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_students_creator` FOREIGN KEY (`created_by`)           REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_students_updater` FOREIGN KEY (`updated_by`)           REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_students_identity` CHECK (`nid_no` IS NOT NULL OR `birth_reg_no` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Replaces student (one person record; courses are enrolments, not copied columns)';

DROP TABLE IF EXISTS `student_educations`;
CREATE TABLE `student_educations` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`   INT UNSIGNED NOT NULL,
  `exam_name`    VARCHAR(80)  NOT NULL COMMENT 'PSC, JSC, SSC, HSC, Diploma, Degree …',
  `institution`  VARCHAR(150) NULL,
  `board`        VARCHAR(60)  NULL,
  `passing_year` YEAR NULL,
  `result`       VARCHAR(30)  NULL,
  PRIMARY KEY (`id`),
  KEY `idx_se_student` (`student_id`),
  CONSTRAINT `fk_se_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces acca_info';

DROP TABLE IF EXISTS `student_documents`;
CREATE TABLE `student_documents` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED NOT NULL,
  `doc_type`    ENUM('photo','nid','birth_certificate','academic_certificate','medical','other') NOT NULL,
  `title`       VARCHAR(120) NULL,
  `file_path`   VARCHAR(255) NOT NULL COMMENT 'Stored under storage/uploads (not web-accessible)',
  `mime_type`   VARCHAR(60)  NOT NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sd_student` (`student_id`),
  CONSTRAINT `fk_sd_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sd_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces student_docs';

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`      INT UNSIGNED NOT NULL,
  `batch_id`        INT UNSIGNED NOT NULL,
  `center_id`       SMALLINT UNSIGNED NOT NULL COMMENT 'Denormalised from batch for fast scoping',
  `roll_no`         VARCHAR(20) NOT NULL,
  `admission_date`  DATE NOT NULL,
  `is_residential`  TINYINT(1) NOT NULL DEFAULT 0,
  `gross_fee`       DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of applicable course_fees at admission',
  `waiver_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Approved waivers/scholarships',
  `net_fee`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sponsor`         VARCHAR(120) NULL COMMENT 'Donor / project paying on behalf of the trainee',
  `status`          ENUM('admitted','running','completed','dropped_out','transferred','cancelled') NOT NULL DEFAULT 'admitted',
  `status_reason`   VARCHAR(255) NULL,
  `completion_date` DATE NULL,
  `attendance_percent` DECIMAL(5,2) NULL COMMENT 'Cached; recomputed when attendance changes',
  `created_by`      INT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollment` (`student_id`, `batch_id`),
  UNIQUE KEY `uq_enrollment_roll` (`batch_id`, `roll_no`),
  KEY `idx_enrollments_center` (`center_id`, `status`),
  CONSTRAINT `fk_enr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_enr_batch`   FOREIGN KEY (`batch_id`)   REFERENCES `batches` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_enr_center`  FOREIGN KEY (`center_id`)  REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_enr_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_enr_fees`   CHECK (`gross_fee` >= 0 AND `waiver_amount` >= 0 AND `waiver_amount` <= `gross_fee`
                                    AND `net_fee` = `gross_fee` - `waiver_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Replaces enrollment + student_course (fees, discount, status were duplicated text)';

DROP TABLE IF EXISTS `enrollment_status_logs`;
CREATE TABLE `enrollment_status_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `old_status`    VARCHAR(20) NULL,
  `new_status`    VARCHAR(20) NOT NULL,
  `reason`        VARCHAR(255) NULL,
  `changed_by`    INT UNSIGNED NULL,
  `changed_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_esl_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_esl_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Filled by trigger';

DROP TABLE IF EXISTS `student_attendance`;
CREATE TABLE `student_attendance` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_session_id` BIGINT UNSIGNED NOT NULL,
  `enrollment_id`    INT UNSIGNED NOT NULL,
  `status`           ENUM('present','absent','late','excused') NOT NULL,
  `remarks`          VARCHAR(150) NULL,
  `recorded_by`      INT UNSIGNED NULL,
  `recorded_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_att` (`class_session_id`, `enrollment_id`),
  KEY `idx_sa_enrollment` (`enrollment_id`, `status`),
  CONSTRAINT `fk_sa_session`    FOREIGN KEY (`class_session_id`) REFERENCES `class_sessions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sa_enrollment` FOREIGN KEY (`enrollment_id`)    REFERENCES `enrollments` (`id`)    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sa_recorder`   FOREIGN KEY (`recorded_by`)      REFERENCES `users` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NEW: trainee attendance per class (missing in old system)';

-- =====================================================================
-- 7. ASSESSMENT, RESULTS, CERTIFICATES, ASSIGNMENTS
-- =====================================================================
DROP TABLE IF EXISTS `grade_scales`;
CREATE TABLE `grade_scales` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scheme`      ENUM('gpa','cbt') NOT NULL COMMENT 'gpa = letter grades, cbt = Competent / Not Yet Competent',
  `grade`       VARCHAR(10) NOT NULL,
  `label_en`    VARCHAR(40) NOT NULL,
  `label_bn`    VARCHAR(40) NOT NULL,
  `min_percent` DECIMAL(5,2) NOT NULL,
  `max_percent` DECIMAL(5,2) NOT NULL,
  `grade_point` DECIMAL(3,2) NULL,
  `is_pass`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grade` (`scheme`, `grade`),
  CONSTRAINT `chk_grade_range` CHECK (`min_percent` <= `max_percent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assessments`;
CREATE TABLE `assessments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`      INT UNSIGNED NOT NULL,
  `module_id`     INT UNSIGNED NULL,
  `title`         VARCHAR(150) NOT NULL,
  `assessment_type` ENUM('class_test','mid_term','practical','viva','project','final_theory','final_practical') NOT NULL,
  `assessment_date` DATE NOT NULL,
  `total_marks`   DECIMAL(6,2) NOT NULL,
  `pass_marks`    DECIMAL(6,2) NOT NULL,
  `weight_percent` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'Contribution to final result; all weights of a batch should sum to 100',
  `status`        ENUM('scheduled','marks_entry','submitted','approved','published') NOT NULL DEFAULT 'scheduled',
  `examiner_id`   INT UNSIGNED NULL,
  `approved_by`   INT UNSIGNED NULL,
  `approved_at`   DATETIME NULL,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assess_batch` (`batch_id`, `assessment_date`),
  CONSTRAINT `fk_as_batch`    FOREIGN KEY (`batch_id`)    REFERENCES `batches` (`id`)        ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_as_module`   FOREIGN KEY (`module_id`)   REFERENCES `course_modules` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_as_examiner` FOREIGN KEY (`examiner_id`) REFERENCES `staff` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_as_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_as_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_as_marks`   CHECK (`total_marks` > 0 AND `pass_marks` BETWEEN 0 AND `total_marks` AND `weight_percent` BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NEW: exams / practical tests per batch';

DROP TABLE IF EXISTS `assessment_marks`;
CREATE TABLE `assessment_marks` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` INT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `marks`         DECIMAL(6,2) NULL,
  `is_absent`     TINYINT(1) NOT NULL DEFAULT 0,
  `remarks`       VARCHAR(150) NULL,
  `entered_by`    INT UNSIGNED NULL,
  `entered_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marks` (`assessment_id`, `enrollment_id`),
  KEY `idx_marks_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_am_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_am_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_am_user`       FOREIGN KEY (`entered_by`)    REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_am_marks`     CHECK (`marks` IS NULL OR `marks` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `results`;
CREATE TABLE `results` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id`   INT UNSIGNED NOT NULL,
  `total_percent`   DECIMAL(5,2) NOT NULL,
  `grade`           VARCHAR(10)  NOT NULL,
  `grade_point`     DECIMAL(3,2) NULL,
  `is_passed`       TINYINT(1)   NOT NULL,
  `attendance_percent` DECIMAL(5,2) NOT NULL,
  `status`          ENUM('draft','approved','published','withheld') NOT NULL DEFAULT 'draft',
  `approved_by`     INT UNSIGNED NULL,
  `approved_at`     DATETIME NULL,
  `published_at`    DATETIME NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_results_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_res_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_res_approver`   FOREIGN KEY (`approved_by`)   REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NEW: final course result per enrolment';

DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificate_no`    VARCHAR(30) NOT NULL COMMENT 'RTTI-CENTER-YYYY-NNNNN',
  `verification_code` CHAR(12)    NOT NULL COMMENT 'Random code printed as QR for public verification',
  `enrollment_id`     INT UNSIGNED NOT NULL,
  `result_id`         INT UNSIGNED NOT NULL,
  `issue_date`        DATE NOT NULL,
  `grade`             VARCHAR(10) NOT NULL,
  `status`            ENUM('issued','reissued','revoked') NOT NULL DEFAULT 'issued',
  `revoked_reason`    VARCHAR(255) NULL,
  `print_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `issued_by`         INT UNSIGNED NOT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cert_no` (`certificate_no`),
  UNIQUE KEY `uq_cert_verify` (`verification_code`),
  UNIQUE KEY `uq_cert_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_cert_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_cert_result`     FOREIGN KEY (`result_id`)     REFERENCES `results` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_cert_issuer`     FOREIGN KEY (`issued_by`)     REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Replaces certificate (now only for passed results, unique & verifiable)';

DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`    INT UNSIGNED NOT NULL,
  `module_id`   INT UNSIGNED NULL,
  `title`       VARCHAR(200) NOT NULL,
  `instructions` TEXT NULL,
  `file_path`   VARCHAR(255) NULL,
  `due_date`    DATE NULL,
  `total_marks` DECIMAL(6,2) NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assign_batch` (`batch_id`),
  CONSTRAINT `fk_asg_batch`   FOREIGN KEY (`batch_id`)   REFERENCES `batches` (`id`)        ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_asg_module`  FOREIGN KEY (`module_id`)  REFERENCES `course_modules` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_asg_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces assignment';

DROP TABLE IF EXISTS `assignment_submissions`;
CREATE TABLE `assignment_submissions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `file_path`     VARCHAR(255) NULL,
  `submitted_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `marks`         DECIMAL(6,2) NULL,
  `feedback`      VARCHAR(255) NULL,
  `graded_by`     INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_submission` (`assignment_id`, `enrollment_id`),
  CONSTRAINT `fk_sub_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sub_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_sub_grader`     FOREIGN KEY (`graded_by`)     REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. FINANCE — FEES, RECEIPTS, WAIVERS, EXPENSES, CASH LEDGER
-- =====================================================================
DROP TABLE IF EXISTS `finance_accounts`;
CREATE TABLE `finance_accounts` (
  `id`             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `center_id`      SMALLINT UNSIGNED NOT NULL,
  `account_type`   ENUM('cash','bank','mobile_wallet') NOT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `bank_name`      VARCHAR(120) NULL,
  `account_number` VARCHAR(40)  NULL,
  `opening_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fa_name` (`center_id`, `name`),
  CONSTRAINT `fk_fa_center` FOREIGN KEY (`center_id`) REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Each center''s cash box, bank and bKash/Nagad accounts';

DROP TABLE IF EXISTS `fee_invoices`;
CREATE TABLE `fee_invoices` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no`    VARCHAR(30) NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `center_id`     SMALLINT UNSIGNED NOT NULL,
  `invoice_date`  DATE NOT NULL,
  `due_date`      DATE NOT NULL,
  `installment_no` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `amount`        DECIMAL(12,2) NOT NULL,
  `paid_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Maintained by trigger from fee_payments',
  `status`        ENUM('unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  `created_by`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fee_invoice_no` (`invoice_no`),
  KEY `idx_fi_enrollment` (`enrollment_id`),
  KEY `idx_fi_center_due` (`center_id`, `status`, `due_date`),
  CONSTRAINT `fk_fi_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fi_center`     FOREIGN KEY (`center_id`)     REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fi_creator`    FOREIGN KEY (`created_by`)    REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_fi_amounts`   CHECK (`amount` > 0 AND `paid_amount` >= 0 AND `paid_amount` <= `amount` AND `due_date` >= `invoice_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NEW: fee demand / installment schedule (enables due tracking)';

DROP TABLE IF EXISTS `fee_invoice_items`;
CREATE TABLE `fee_invoice_items` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`  INT UNSIGNED NOT NULL,
  `fee_head_id` SMALLINT UNSIGNED NOT NULL,
  `amount`      DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fii_head` (`invoice_id`, `fee_head_id`),
  CONSTRAINT `fk_fii_invoice` FOREIGN KEY (`invoice_id`)  REFERENCES `fee_invoices` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_fii_head`    FOREIGN KEY (`fee_head_id`) REFERENCES `fee_heads` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_fii_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fee_payments`;
CREATE TABLE `fee_payments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_no`      VARCHAR(30) NOT NULL COMMENT 'Money receipt number MR-CENTER-YYYYMM-NNNNN',
  `invoice_id`      INT UNSIGNED NOT NULL,
  `center_id`       SMALLINT UNSIGNED NOT NULL,
  `account_id`      SMALLINT UNSIGNED NOT NULL COMMENT 'Cash box / bank / wallet that received the money',
  `payment_date`    DATE NOT NULL,
  `amount`          DECIMAL(12,2) NOT NULL,
  `method`          ENUM('cash','bkash','nagad','rocket','bank','sponsor') NOT NULL DEFAULT 'cash',
  `transaction_ref` VARCHAR(60) NULL,
  `remarks`         VARCHAR(255) NULL,
  `status`          ENUM('valid','cancelled') NOT NULL DEFAULT 'valid',
  `cancel_reason`   VARCHAR(255) NULL,
  `cancelled_by`    INT UNSIGNED NULL,
  `cancelled_at`    DATETIME NULL,
  `received_by`     INT UNSIGNED NOT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fp_receipt` (`receipt_no`),
  KEY `idx_fp_invoice` (`invoice_id`),
  KEY `idx_fp_center_date` (`center_id`, `payment_date`),
  CONSTRAINT `fk_fp_invoice`   FOREIGN KEY (`invoice_id`)   REFERENCES `fee_invoices` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fp_center`    FOREIGN KEY (`center_id`)    REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fp_account`   FOREIGN KEY (`account_id`)   REFERENCES `finance_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fp_receiver`  FOREIGN KEY (`received_by`)  REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fp_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_fp_amount`   CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Replaces fee (receipts are immutable; corrections = cancel with reason)';

DROP TABLE IF EXISTS `fee_waivers`;
CREATE TABLE `fee_waivers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `waiver_type`   ENUM('discount','scholarship','sponsor','poor_fund','staff_quota','other') NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `reason`        VARCHAR(255) NOT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by`  INT UNSIGNED NOT NULL,
  `approved_by`   INT UNSIGNED NULL,
  `approved_at`   DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fw_enrollment` (`enrollment_id`, `status`),
  CONSTRAINT `fk_fw_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_fw_requester`  FOREIGN KEY (`requested_by`)  REFERENCES `users` (`id`)       ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_fw_approver`   FOREIGN KEY (`approved_by`)   REFERENCES `users` (`id`)       ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `chk_fw_amount`    CHECK (`amount` > 0),
  CONSTRAINT `chk_fw_maker_checker` CHECK (`approved_by` IS NULL OR `approved_by` <> `requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Replaces free-text discount / less_amount (now needs approval by someone else)';

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `refund_no`     VARCHAR(30) NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `center_id`     SMALLINT UNSIGNED NOT NULL,
  `account_id`    SMALLINT UNSIGNED NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `reason`        VARCHAR(255) NOT NULL,
  `status`        ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  `requested_by`  INT UNSIGNED NOT NULL,
  `approved_by`   INT UNSIGNED NULL,
  `paid_at`       DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refund_no` (`refund_no`),
  CONSTRAINT `fk_rf_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`)      ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rf_center`     FOREIGN KEY (`center_id`)     REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rf_account`    FOREIGN KEY (`account_id`)    REFERENCES `finance_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rf_requester`  FOREIGN KEY (`requested_by`)  REFERENCES `users` (`id`)            ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_rf_approver`   FOREIGN KEY (`approved_by`)   REFERENCES `users` (`id`)            ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `chk_rf_amount`    CHECK (`amount` > 0),
  CONSTRAINT `chk_rf_maker_checker` CHECK (`approved_by` IS NULL OR `approved_by` <> `requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id`        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`      VARCHAR(20) NOT NULL,
  `name_en`   VARCHAR(80) NOT NULL,
  `name_bn`   VARCHAR(80) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ec_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_no`    VARCHAR(30) NOT NULL,
  `center_id`     SMALLINT UNSIGNED NOT NULL,
  `category_id`   SMALLINT UNSIGNED NOT NULL,
  `account_id`    SMALLINT UNSIGNED NOT NULL COMMENT 'Paid from',
  `expense_date`  DATE NOT NULL,
  `amount`        DECIMAL(12,2) NOT NULL,
  `payee`         VARCHAR(120) NULL,
  `narration`     VARCHAR(255) NOT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `status`        ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `requested_by`  INT UNSIGNED NOT NULL,
  `approved_by`   INT UNSIGNED NULL,
  `approved_at`   DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_voucher` (`voucher_no`),
  KEY `idx_exp_center_date` (`center_id`, `expense_date`),
  CONSTRAINT `fk_exp_center`    FOREIGN KEY (`center_id`)    REFERENCES `training_centers` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_exp_category`  FOREIGN KEY (`category_id`)  REFERENCES `expense_categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_exp_account`   FOREIGN KEY (`account_id`)   REFERENCES `finance_accounts` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_exp_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)              ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_exp_approver`  FOREIGN KEY (`approved_by`)  REFERENCES `users` (`id`)              ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `chk_exp_amount`   CHECK (`amount` > 0),
  CONSTRAINT `chk_exp_maker_checker` CHECK (`approved_by` IS NULL OR `approved_by` <> `requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces expense (adds category, approval, paying account)';

DROP TABLE IF EXISTS `fund_transfers`;
CREATE TABLE `fund_transfers` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_no`     VARCHAR(30) NOT NULL,
  `from_account_id` SMALLINT UNSIGNED NOT NULL,
  `to_account_id`   SMALLINT UNSIGNED NOT NULL,
  `transfer_date`   DATE NOT NULL,
  `amount`          DECIMAL(14,2) NOT NULL,
  `reference`       VARCHAR(80) NULL,
  `narration`       VARCHAR(255) NULL,
  `status`          ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by`      INT UNSIGNED NOT NULL,
  `confirmed_by`    INT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transfer_no` (`transfer_no`),
  CONSTRAINT `fk_ft_from`      FOREIGN KEY (`from_account_id`) REFERENCES `finance_accounts` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_ft_to`        FOREIGN KEY (`to_account_id`)   REFERENCES `finance_accounts` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_ft_creator`   FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ft_confirmer` FOREIGN KEY (`confirmed_by`)    REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_ft_values`   CHECK (`amount` > 0 AND `from_account_id` <> `to_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Center cash → bank deposit, center → Head Office remittance';

DROP TABLE IF EXISTS `account_transactions`;
CREATE TABLE `account_transactions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id`  SMALLINT UNSIGNED NOT NULL,
  `center_id`   SMALLINT UNSIGNED NOT NULL,
  `txn_date`    DATE NOT NULL,
  `direction`   ENUM('in','out') NOT NULL,
  `amount`      DECIMAL(14,2) NOT NULL,
  `source_type` ENUM('fee_payment','fee_payment_cancel','expense','refund','transfer','opening','adjustment') NOT NULL,
  `source_id`   INT UNSIGNED NULL,
  `narration`   VARCHAR(255) NOT NULL,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_txn_source` (`source_type`, `source_id`, `account_id`, `direction`),
  KEY `idx_txn_account_date` (`account_id`, `txn_date`),
  KEY `idx_txn_center_date` (`center_id`, `txn_date`),
  CONSTRAINT `fk_txn_account` FOREIGN KEY (`account_id`) REFERENCES `finance_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_txn_center`  FOREIGN KEY (`center_id`)  REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_txn_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_txn_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cash book / bank book: every money movement, append-only (written by triggers)';

-- =====================================================================
-- 9. ASSETS, PLACEMENT, COMMUNICATION, SYSTEM
-- =====================================================================
DROP TABLE IF EXISTS `asset_categories`;
CREATE TABLE `asset_categories` (
  `id`      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_en` VARCHAR(80) NOT NULL,
  `name_bn` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_cat` (`name_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_tag`      VARCHAR(30)  NOT NULL,
  `center_id`      SMALLINT UNSIGNED NOT NULL,
  `category_id`    SMALLINT UNSIGNED NOT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `serial_no`      VARCHAR(80)  NULL,
  `room_id`        INT UNSIGNED NULL,
  `purchase_date`  DATE NULL,
  `purchase_cost`  DECIMAL(12,2) NULL,
  `supplier`       VARCHAR(120) NULL,
  `warranty_until` DATE NULL,
  `funded_by`      VARCHAR(120) NULL,
  `condition_status` ENUM('good','needs_repair','damaged','disposed','lost') NOT NULL DEFAULT 'good',
  `assigned_to_staff_id` INT UNSIGNED NULL,
  `remarks`        VARCHAR(255) NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asset_tag` (`asset_tag`),
  KEY `idx_assets_center` (`center_id`, `condition_status`),
  CONSTRAINT `fk_asset_center`   FOREIGN KEY (`center_id`)   REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_asset_category` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_asset_room`     FOREIGN KEY (`room_id`)     REFERENCES `rooms` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_asset_staff`    FOREIGN KEY (`assigned_to_staff_id`) REFERENCES `staff` (`id`)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces asset_regist';

DROP TABLE IF EXISTS `asset_transfers`;
CREATE TABLE `asset_transfers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_id`       INT UNSIGNED NOT NULL,
  `from_center_id` SMALLINT UNSIGNED NOT NULL,
  `to_center_id`   SMALLINT UNSIGNED NOT NULL,
  `transfer_date`  DATE NOT NULL,
  `reason`         VARCHAR(255) NULL,
  `approved_by`    INT UNSIGNED NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_at_asset`    FOREIGN KEY (`asset_id`)       REFERENCES `assets` (`id`)           ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_at_from`     FOREIGN KEY (`from_center_id`) REFERENCES `training_centers` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_at_to`       FOREIGN KEY (`to_center_id`)   REFERENCES `training_centers` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_at_approver` FOREIGN KEY (`approved_by`)    REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_at_centers` CHECK (`from_center_id` <> `to_center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `employers`;
CREATE TABLE `employers` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(150) NOT NULL,
  `industry`     VARCHAR(80)  NULL,
  `contact_person` VARCHAR(120) NULL,
  `phone`        VARCHAR(20)  NULL,
  `email`        VARCHAR(150) NULL,
  `district_id`  SMALLINT UNSIGNED NULL,
  `address`      VARCHAR(255) NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employer_name` (`name`),
  CONSTRAINT `fk_employer_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `placements`;
CREATE TABLE `placements` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enrollment_id`  INT UNSIGNED NOT NULL,
  `placement_type` ENUM('wage_employment','self_employment','overseas','further_study') NOT NULL,
  `employer_id`    INT UNSIGNED NULL,
  `job_title`      VARCHAR(120) NULL,
  `monthly_income` DECIMAL(12,2) NULL,
  `start_date`     DATE NOT NULL,
  `district_id`    SMALLINT UNSIGNED NULL,
  `verified`       TINYINT(1) NOT NULL DEFAULT 0,
  `verified_by`    INT UNSIGNED NULL,
  `verified_at`    DATETIME NULL,
  `follow_up_note` VARCHAR(255) NULL,
  `created_by`     INT UNSIGNED NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_placement_enrollment` (`enrollment_id`),
  CONSTRAINT `fk_pl_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pl_employer`   FOREIGN KEY (`employer_id`)   REFERENCES `employers` (`id`)   ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pl_district`   FOREIGN KEY (`district_id`)   REFERENCES `districts` (`id`)   ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pl_verifier`   FOREIGN KEY (`verified_by`)   REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pl_creator`    FOREIGN KEY (`created_by`)    REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NEW: job placement tracking (graduate outcome reporting)';

DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `center_id`   SMALLINT UNSIGNED NULL COMMENT 'NULL = all centers',
  `batch_id`    INT UNSIGNED NULL,
  `audience`    ENUM('all','staff','students') NOT NULL DEFAULT 'all',
  `title`       VARCHAR(200) NOT NULL,
  `body`        TEXT NOT NULL,
  `publish_from` DATE NOT NULL,
  `publish_to`   DATE NULL,
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notices_center` (`center_id`, `publish_from`),
  CONSTRAINT `fk_notice_center`  FOREIGN KEY (`center_id`)  REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notice_batch`   FOREIGN KEY (`batch_id`)   REFERENCES `batches` (`id`)          ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notice_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `center_id`   SMALLINT UNSIGNED NULL,
  `title`       VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `assigned_to` INT UNSIGNED NULL,
  `due_date`    DATE NULL,
  `priority`    ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  `status`      ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_assignee` (`assigned_to`, `status`),
  CONSTRAINT `fk_task_center`   FOREIGN KEY (`center_id`)   REFERENCES `training_centers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_task_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_task_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Replaces todo';

DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mobile`     VARCHAR(20)  NOT NULL,
  `message`    VARCHAR(640) NOT NULL,
  `purpose`    ENUM('admission','fee_due','fee_receipt','absence','result','notice','password','other') NOT NULL,
  `student_id` INT UNSIGNED NULL,
  `status`     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `error`      VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sms_status` (`status`, `created_at`),
  CONSTRAINT `fk_sms_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `number_sequences`;
CREATE TABLE `number_sequences` (
  `seq_key`    VARCHAR(40) NOT NULL,
  `seq_period` VARCHAR(10) NOT NULL,
  `last_value` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`seq_key`, `seq_period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Race-safe registration / receipt / voucher numbering';

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(40) NOT NULL DEFAULT 'general',
  `description`   VARCHAR(255) NULL,
  `updated_by`    INT UNSIGNED NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `center_id`   SMALLINT UNSIGNED NULL,
  `action`      VARCHAR(40) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id`   VARCHAR(40) NULL,
  `old_value`   JSON NULL,
  `new_value`   JSON NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  VARCHAR(255) NULL,
  `source`      ENUM('app','trigger') NOT NULL DEFAULT 'app',
  `created_at`  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_center` (`center_id`, `created_at`),
  KEY `idx_audit_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only (UPDATE/DELETE blocked by triggers)';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- 10. TRIGGERS
-- =====================================================================
DELIMITER $$

-- ---- 10.1 Audit log is append-only --------------------------------------
DROP TRIGGER IF EXISTS `trg_audit_logs_bu`$$
CREATE TRIGGER `trg_audit_logs_bu` BEFORE UPDATE ON `audit_logs` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AUDIT: audit log entries are immutable';
END$$
DROP TRIGGER IF EXISTS `trg_audit_logs_bd`$$
CREATE TRIGGER `trg_audit_logs_bd` BEFORE DELETE ON `audit_logs` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AUDIT: audit log entries cannot be deleted';
END$$

-- ---- 10.2 Cash book is append-only ----------------------------------------
DROP TRIGGER IF EXISTS `trg_account_txn_bu`$$
CREATE TRIGGER `trg_account_txn_bu` BEFORE UPDATE ON `account_transactions` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCE: cash book entries are immutable';
END$$
DROP TRIGGER IF EXISTS `trg_account_txn_bd`$$
CREATE TRIGGER `trg_account_txn_bd` BEFORE DELETE ON `account_transactions` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCE: cash book entries cannot be deleted';
END$$

DROP TRIGGER IF EXISTS `trg_finance_accounts_ai`$$
CREATE TRIGGER `trg_finance_accounts_ai` AFTER INSERT ON `finance_accounts` FOR EACH ROW
BEGIN
  IF NEW.opening_balance > 0 THEN
    INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
    VALUES (NEW.id, NEW.center_id, CURRENT_DATE, 'in', NEW.opening_balance, 'opening', NEW.id, 'Opening balance', @app_user_id);
  END IF;
END$$

-- ---- 10.3 Fee receipts ------------------------------------------------------
-- Payment must belong to the same center as its invoice and its receiving
-- account, may not exceed the invoice balance, and the invoice must be open.
DROP TRIGGER IF EXISTS `trg_fee_payments_bi`$$
CREATE TRIGGER `trg_fee_payments_bi` BEFORE INSERT ON `fee_payments` FOR EACH ROW
BEGIN
  DECLARE v_center SMALLINT UNSIGNED;
  DECLARE v_amount DECIMAL(12,2);
  DECLARE v_paid   DECIMAL(12,2);
  DECLARE v_status VARCHAR(10);
  DECLARE v_acc_center SMALLINT UNSIGNED;

  SELECT `center_id`, `amount`, `paid_amount`, `status` INTO v_center, v_amount, v_paid, v_status
    FROM `fee_invoices` WHERE `id` = NEW.invoice_id FOR UPDATE;
  SELECT `center_id` INTO v_acc_center FROM `finance_accounts` WHERE `id` = NEW.account_id;

  IF v_status IN ('paid', 'cancelled') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: invoice is already paid or cancelled';
  END IF;
  IF NEW.center_id <> v_center OR v_acc_center <> v_center THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: invoice, receiving account and payment must belong to the same center';
  END IF;
  IF NEW.amount > v_amount - v_paid THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: payment exceeds the invoice due amount';
  END IF;
  SET NEW.status = 'valid';
END$$

DROP TRIGGER IF EXISTS `trg_fee_payments_ai`$$
CREATE TRIGGER `trg_fee_payments_ai` AFTER INSERT ON `fee_payments` FOR EACH ROW
BEGIN
  UPDATE `fee_invoices`
     SET `status` = IF(`paid_amount` + NEW.amount >= `amount`, 'paid', 'partial'),  -- status first: MySQL applies SET left to right
         `paid_amount` = `paid_amount` + NEW.amount
   WHERE `id` = NEW.invoice_id;
  INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
  VALUES (NEW.account_id, NEW.center_id, NEW.payment_date, 'in', NEW.amount, 'fee_payment', NEW.id,
          CONCAT('Fee receipt ', NEW.receipt_no), NEW.received_by);
  INSERT INTO `audit_logs` (`user_id`, `center_id`, `action`, `entity_type`, `entity_id`, `new_value`, `ip_address`, `source`)
  VALUES (COALESCE(@app_user_id, NEW.received_by), NEW.center_id, 'create', 'fee_payment', NEW.id,
          JSON_OBJECT('receipt_no', NEW.receipt_no, 'invoice_id', NEW.invoice_id, 'amount', NEW.amount, 'method', NEW.method),
          @app_ip, 'trigger');
END$$

-- Receipts are immutable; the only allowed change is valid -> cancelled with a reason.
DROP TRIGGER IF EXISTS `trg_fee_payments_bu`$$
CREATE TRIGGER `trg_fee_payments_bu` BEFORE UPDATE ON `fee_payments` FOR EACH ROW
BEGIN
  IF NEW.amount <> OLD.amount OR NEW.invoice_id <> OLD.invoice_id OR NEW.account_id <> OLD.account_id
     OR NEW.payment_date <> OLD.payment_date OR NEW.receipt_no <> OLD.receipt_no OR NEW.center_id <> OLD.center_id
     OR NEW.received_by <> OLD.received_by THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: money receipts cannot be edited - cancel and re-issue instead';
  END IF;
  IF OLD.status = 'cancelled' AND NEW.status <> 'cancelled' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: a cancelled receipt cannot be restored';
  END IF;
  IF NEW.status = 'cancelled' AND OLD.status = 'valid' THEN
    IF NEW.cancel_reason IS NULL OR CHAR_LENGTH(TRIM(NEW.cancel_reason)) < 5 OR NEW.cancelled_by IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: cancelling a receipt needs a reason and the cancelling user';
    END IF;
    SET NEW.cancelled_at = NOW();
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_fee_payments_au`$$
CREATE TRIGGER `trg_fee_payments_au` AFTER UPDATE ON `fee_payments` FOR EACH ROW
BEGIN
  IF NEW.status = 'cancelled' AND OLD.status = 'valid' THEN
    UPDATE `fee_invoices`
       SET `status` = IF(`paid_amount` - OLD.amount <= 0, 'unpaid', 'partial'),  -- status first (SET is left to right)
           `paid_amount` = `paid_amount` - OLD.amount
     WHERE `id` = OLD.invoice_id;
    INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
    VALUES (OLD.account_id, OLD.center_id, CURRENT_DATE, 'out', OLD.amount, 'fee_payment_cancel', OLD.id,
            CONCAT('Cancelled receipt ', OLD.receipt_no, ': ', NEW.cancel_reason), NEW.cancelled_by);
    INSERT INTO `audit_logs` (`user_id`, `center_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (COALESCE(@app_user_id, NEW.cancelled_by), NEW.center_id, 'cancel', 'fee_payment', NEW.id,
            JSON_OBJECT('status', OLD.status, 'amount', OLD.amount),
            JSON_OBJECT('status', NEW.status, 'reason', NEW.cancel_reason), @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_fee_payments_bd`$$
CREATE TRIGGER `trg_fee_payments_bd` BEFORE DELETE ON `fee_payments` FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: money receipts can never be deleted';
END$$

-- ---- 10.4 Waivers: approved waiver reduces the enrolment's net fee ----------
DROP TRIGGER IF EXISTS `trg_fee_waivers_au`$$
CREATE TRIGGER `trg_fee_waivers_au` AFTER UPDATE ON `fee_waivers` FOR EACH ROW
BEGIN
  IF NEW.status = 'approved' AND OLD.status <> 'approved' THEN
    UPDATE `enrollments`
       SET `waiver_amount` = `waiver_amount` + NEW.amount,
           `net_fee` = `gross_fee` - `waiver_amount`  -- waiver_amount already holds the new value here
     WHERE `id` = NEW.enrollment_id;
  ELSEIF OLD.status = 'approved' AND NEW.status <> 'approved' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FEE: an approved waiver cannot be changed';
  END IF;
  IF NEW.status <> OLD.status THEN
    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (COALESCE(@app_user_id, NEW.approved_by), NEW.status, 'fee_waiver', NEW.id,
            JSON_OBJECT('status', OLD.status), JSON_OBJECT('status', NEW.status, 'amount', NEW.amount), @app_ip, 'trigger');
  END IF;
END$$

-- ---- 10.5 Expenses, refunds, transfers post to the cash book on approval ---
DROP TRIGGER IF EXISTS `trg_expenses_bu`$$
CREATE TRIGGER `trg_expenses_bu` BEFORE UPDATE ON `expenses` FOR EACH ROW
BEGIN
  IF OLD.status = 'approved' AND (NEW.status <> 'approved' OR NEW.amount <> OLD.amount
     OR NEW.account_id <> OLD.account_id OR NEW.expense_date <> OLD.expense_date) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCE: an approved expense is locked';
  END IF;
  IF NEW.status = 'approved' AND OLD.status <> 'approved' THEN
    IF (SELECT `center_id` FROM `finance_accounts` WHERE `id` = NEW.account_id) <> NEW.center_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCE: expense must be paid from an account of the same center';
    END IF;
    SET NEW.approved_at = NOW();
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_expenses_au`$$
CREATE TRIGGER `trg_expenses_au` AFTER UPDATE ON `expenses` FOR EACH ROW
BEGIN
  IF NEW.status = 'approved' AND OLD.status <> 'approved' THEN
    INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
    VALUES (NEW.account_id, NEW.center_id, NEW.expense_date, 'out', NEW.amount, 'expense', NEW.id,
            CONCAT('Expense ', NEW.voucher_no, ': ', NEW.narration), NEW.approved_by);
  END IF;
  IF NEW.status <> OLD.status THEN
    INSERT INTO `audit_logs` (`user_id`, `center_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (COALESCE(@app_user_id, NEW.approved_by), NEW.center_id, NEW.status, 'expense', NEW.id,
            JSON_OBJECT('status', OLD.status), JSON_OBJECT('status', NEW.status, 'amount', NEW.amount), @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_refunds_au`$$
CREATE TRIGGER `trg_refunds_au` AFTER UPDATE ON `refunds` FOR EACH ROW
BEGIN
  IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
    IF OLD.status <> 'approved' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCE: a refund must be approved before it is paid';
    END IF;
    INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
    VALUES (NEW.account_id, NEW.center_id, CURRENT_DATE, 'out', NEW.amount, 'refund', NEW.id,
            CONCAT('Refund ', NEW.refund_no), @app_user_id);
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_fund_transfers_au`$$
CREATE TRIGGER `trg_fund_transfers_au` AFTER UPDATE ON `fund_transfers` FOR EACH ROW
BEGIN
  IF NEW.status = 'completed' AND OLD.status = 'pending' THEN
    INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
    SELECT NEW.from_account_id, a.center_id, NEW.transfer_date, 'out', NEW.amount, 'transfer', NEW.id,
           CONCAT('Transfer ', NEW.transfer_no, ' out'), NEW.confirmed_by
      FROM `finance_accounts` a WHERE a.id = NEW.from_account_id;
    INSERT INTO `account_transactions` (`account_id`, `center_id`, `txn_date`, `direction`, `amount`, `source_type`, `source_id`, `narration`, `created_by`)
    SELECT NEW.to_account_id, a.center_id, NEW.transfer_date, 'in', NEW.amount, 'transfer', NEW.id,
           CONCAT('Transfer ', NEW.transfer_no, ' in'), NEW.confirmed_by
      FROM `finance_accounts` a WHERE a.id = NEW.to_account_id;
  ELSEIF OLD.status = 'completed' AND NEW.status <> 'completed' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FINANCE: a completed transfer cannot be changed';
  END IF;
END$$

-- ---- 10.6 Academic integrity ------------------------------------------------
DROP TRIGGER IF EXISTS `trg_enrollments_bi`$$
CREATE TRIGGER `trg_enrollments_bi` BEFORE INSERT ON `enrollments` FOR EACH ROW
BEGIN
  DECLARE v_center SMALLINT UNSIGNED;
  DECLARE v_capacity SMALLINT UNSIGNED;
  DECLARE v_status VARCHAR(20);
  DECLARE v_count INT;
  SELECT `center_id`, `capacity`, `status` INTO v_center, v_capacity, v_status FROM `batches` WHERE `id` = NEW.batch_id;
  IF v_status NOT IN ('planned', 'admission_open', 'running') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ENROLMENT: batch is not open for admission';
  END IF;
  SELECT COUNT(*) INTO v_count FROM `enrollments`
   WHERE `batch_id` = NEW.batch_id AND `status` IN ('admitted', 'running', 'completed');
  IF v_count >= v_capacity THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ENROLMENT: batch capacity is full';
  END IF;
  SET NEW.center_id = v_center;
  SET NEW.net_fee = NEW.gross_fee - NEW.waiver_amount;
END$$

DROP TRIGGER IF EXISTS `trg_enrollments_au`$$
CREATE TRIGGER `trg_enrollments_au` AFTER UPDATE ON `enrollments` FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO `enrollment_status_logs` (`enrollment_id`, `old_status`, `new_status`, `reason`, `changed_by`)
    VALUES (NEW.id, OLD.status, NEW.status, NEW.status_reason, @app_user_id);
    INSERT INTO `audit_logs` (`user_id`, `center_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, NEW.center_id, 'status_change', 'enrollment', NEW.id,
            JSON_OBJECT('status', OLD.status), JSON_OBJECT('status', NEW.status, 'reason', NEW.status_reason), @app_ip, 'trigger');
  END IF;
END$$

-- Attendance cannot be changed once the class session is locked.
DROP TRIGGER IF EXISTS `trg_student_att_bi`$$
CREATE TRIGGER `trg_student_att_bi` BEFORE INSERT ON `student_attendance` FOR EACH ROW
BEGIN
  IF (SELECT `is_locked` FROM `class_sessions` WHERE `id` = NEW.class_session_id) = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ATTENDANCE: this class session is locked';
  END IF;
  IF (SELECT `batch_id` FROM `class_sessions` WHERE `id` = NEW.class_session_id)
     <> (SELECT `batch_id` FROM `enrollments` WHERE `id` = NEW.enrollment_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ATTENDANCE: trainee is not enrolled in this batch';
  END IF;
END$$
DROP TRIGGER IF EXISTS `trg_student_att_bu`$$
CREATE TRIGGER `trg_student_att_bu` BEFORE UPDATE ON `student_attendance` FOR EACH ROW
BEGIN
  IF (SELECT `is_locked` FROM `class_sessions` WHERE `id` = OLD.class_session_id) = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ATTENDANCE: this class session is locked';
  END IF;
END$$

-- Marks are frozen once the assessment is approved/published; never above total.
DROP TRIGGER IF EXISTS `trg_marks_bi`$$
CREATE TRIGGER `trg_marks_bi` BEFORE INSERT ON `assessment_marks` FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_total DECIMAL(6,2);
  SELECT `status`, `total_marks` INTO v_status, v_total FROM `assessments` WHERE `id` = NEW.assessment_id;
  IF v_status IN ('approved', 'published') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EXAM: marks are locked after approval';
  END IF;
  IF NEW.marks > v_total THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EXAM: marks exceed total marks';
  END IF;
END$$
DROP TRIGGER IF EXISTS `trg_marks_bu`$$
CREATE TRIGGER `trg_marks_bu` BEFORE UPDATE ON `assessment_marks` FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_total DECIMAL(6,2);
  SELECT `status`, `total_marks` INTO v_status, v_total FROM `assessments` WHERE `id` = NEW.assessment_id;
  IF v_status IN ('approved', 'published') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EXAM: marks are locked after approval';
  END IF;
  IF NEW.marks > v_total THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'EXAM: marks exceed total marks';
  END IF;
  IF (NEW.marks <=> OLD.marks) = 0 THEN
    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, 'update', 'assessment_mark', OLD.id, JSON_OBJECT('marks', OLD.marks), JSON_OBJECT('marks', NEW.marks), @app_ip, 'trigger');
  END IF;
END$$

-- Certificates only for approved/published passing results.
DROP TRIGGER IF EXISTS `trg_certificates_bi`$$
CREATE TRIGGER `trg_certificates_bi` BEFORE INSERT ON `certificates` FOR EACH ROW
BEGIN
  DECLARE v_passed TINYINT;
  DECLARE v_status VARCHAR(20);
  DECLARE v_enrollment INT UNSIGNED;
  SELECT `is_passed`, `status`, `enrollment_id` INTO v_passed, v_status, v_enrollment FROM `results` WHERE `id` = NEW.result_id;
  IF v_enrollment <> NEW.enrollment_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CERTIFICATE: result does not belong to this enrolment';
  END IF;
  IF v_passed <> 1 OR v_status NOT IN ('approved', 'published') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CERTIFICATE: only approved passing results can be certified';
  END IF;
END$$
DROP TRIGGER IF EXISTS `trg_certificates_bu`$$
CREATE TRIGGER `trg_certificates_bu` BEFORE UPDATE ON `certificates` FOR EACH ROW
BEGIN
  IF NEW.certificate_no <> OLD.certificate_no OR NEW.verification_code <> OLD.verification_code
     OR NEW.enrollment_id <> OLD.enrollment_id OR NEW.grade <> OLD.grade THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CERTIFICATE: issued certificate data cannot be changed';
  END IF;
  IF NEW.status = 'revoked' AND OLD.status <> 'revoked' AND (NEW.revoked_reason IS NULL OR NEW.revoked_reason = '') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'CERTIFICATE: revoking needs a reason';
  END IF;
END$$
DROP TRIGGER IF EXISTS `trg_certificates_au`$$
CREATE TRIGGER `trg_certificates_au` AFTER UPDATE ON `certificates` FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, NEW.status, 'certificate', NEW.id, JSON_OBJECT('status', OLD.status),
            JSON_OBJECT('status', NEW.status, 'reason', NEW.revoked_reason), @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_results_au`$$
CREATE TRIGGER `trg_results_au` AFTER UPDATE ON `results` FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status OR NEW.total_percent <> OLD.total_percent THEN
    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, 'update', 'result', NEW.id,
            JSON_OBJECT('status', OLD.status, 'total_percent', OLD.total_percent, 'grade', OLD.grade),
            JSON_OBJECT('status', NEW.status, 'total_percent', NEW.total_percent, 'grade', NEW.grade), @app_ip, 'trigger');
  END IF;
END$$

-- ---- 10.7 Users & permissions audit -----------------------------------------
DROP TRIGGER IF EXISTS `trg_users_au`$$
CREATE TRIGGER `trg_users_au` AFTER UPDATE ON `users` FOR EACH ROW
BEGIN
  IF NEW.role_id <> OLD.role_id OR (NEW.center_id <=> OLD.center_id) = 0 OR (NEW.district_id <=> OLD.district_id) = 0
     OR NEW.status <> OLD.status OR NEW.username <> OLD.username OR (NEW.deleted_at <=> OLD.deleted_at) = 0 THEN
    INSERT INTO `audit_logs` (`user_id`, `center_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_center_id, 'update', 'user', NEW.id,
            JSON_OBJECT('role_id', OLD.role_id, 'center_id', OLD.center_id, 'district_id', OLD.district_id,
                        'status', OLD.status, 'username', OLD.username, 'deleted_at', OLD.deleted_at),
            JSON_OBJECT('role_id', NEW.role_id, 'center_id', NEW.center_id, 'district_id', NEW.district_id,
                        'status', NEW.status, 'username', NEW.username, 'deleted_at', NEW.deleted_at),
            @app_ip, 'trigger');
  END IF;
  IF NEW.password_hash <> OLD.password_hash THEN
    INSERT INTO `audit_logs` (`user_id`, `center_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_center_id, 'password_change', 'user', NEW.id, @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_role_permissions_ai`$$
CREATE TRIGGER `trg_role_permissions_ai` AFTER INSERT ON `role_permissions` FOR EACH ROW
BEGIN
  IF @app_user_id IS NOT NULL THEN
    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, 'grant', 'role_permission', NEW.role_id, JSON_OBJECT('permission_id', NEW.permission_id), @app_ip, 'trigger');
  END IF;
END$$
DROP TRIGGER IF EXISTS `trg_role_permissions_ad`$$
CREATE TRIGGER `trg_role_permissions_ad` AFTER DELETE ON `role_permissions` FOR EACH ROW
BEGIN
  IF @app_user_id IS NOT NULL THEN
    INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `old_value`, `ip_address`, `source`)
    VALUES (@app_user_id, 'revoke', 'role_permission', OLD.role_id, JSON_OBJECT('permission_id', OLD.permission_id), @app_ip, 'trigger');
  END IF;
END$$

DELIMITER ;

-- =====================================================================
-- 11. REPORTING VIEWS
-- =====================================================================
CREATE OR REPLACE VIEW `v_enrollment_dues` AS
SELECT e.id AS enrollment_id, e.center_id, e.batch_id, e.student_id, s.registration_no, s.name_en, s.mobile,
       e.net_fee,
       COALESCE(SUM(CASE WHEN p.status = 'valid' THEN p.amount END), 0) AS paid,
       e.net_fee - COALESCE(SUM(CASE WHEN p.status = 'valid' THEN p.amount END), 0) AS due
  FROM enrollments e
  JOIN students s ON s.id = e.student_id
  LEFT JOIN fee_invoices i ON i.enrollment_id = e.id AND i.status <> 'cancelled'
  LEFT JOIN fee_payments p ON p.invoice_id = i.id
 WHERE e.status NOT IN ('cancelled')
 GROUP BY e.id, e.center_id, e.batch_id, e.student_id, s.registration_no, s.name_en, s.mobile, e.net_fee;

CREATE OR REPLACE VIEW `v_account_balances` AS
SELECT a.id AS account_id, a.center_id, a.name, a.account_type,
       COALESCE(SUM(CASE WHEN t.direction = 'in'  THEN t.amount END), 0) AS total_in,
       COALESCE(SUM(CASE WHEN t.direction = 'out' THEN t.amount END), 0) AS total_out,
       COALESCE(SUM(CASE WHEN t.direction = 'in' THEN t.amount ELSE -t.amount END), 0) AS balance
  FROM finance_accounts a
  LEFT JOIN account_transactions t ON t.account_id = a.id
 GROUP BY a.id, a.center_id, a.name, a.account_type;

CREATE OR REPLACE VIEW `v_enrollment_attendance` AS
SELECT e.id AS enrollment_id, e.batch_id, e.center_id,
       COUNT(sa.id) AS sessions_marked,
       SUM(sa.status IN ('present', 'late')) AS sessions_attended,
       ROUND(100 * SUM(sa.status IN ('present', 'late')) / NULLIF(COUNT(sa.id), 0), 2) AS attendance_percent
  FROM enrollments e
  LEFT JOIN student_attendance sa ON sa.enrollment_id = e.id
 GROUP BY e.id, e.batch_id, e.center_id;

CREATE OR REPLACE VIEW `v_batch_summary` AS
SELECT b.id AS batch_id, b.batch_code, b.center_id, b.course_id, b.status, b.capacity, b.start_date, b.end_date,
       SUM(e.status IN ('admitted', 'running', 'completed')) AS enrolled,
       SUM(e.status = 'completed')   AS completed,
       SUM(e.status = 'dropped_out') AS dropped_out,
       SUM(st.gender = 'female')     AS female,
       SUM(st.disability <> 'none')  AS with_disability
  FROM batches b
  LEFT JOIN enrollments e ON e.batch_id = b.id
  LEFT JOIN students st   ON st.id = e.student_id
 GROUP BY b.id, b.batch_code, b.center_id, b.course_id, b.status, b.capacity, b.start_date, b.end_date;

-- =====================================================================
-- 12. SEED DATA
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `divisions` (`id`, `name_en`, `name_bn`) VALUES
 (1,'Dhaka','ঢাকা'),(2,'Chattogram','চট্টগ্রাম'),(3,'Rajshahi','রাজশাহী'),(4,'Khulna','খুলনা'),
 (5,'Barishal','বরিশাল'),(6,'Sylhet','সিলেট'),(7,'Rangpur','রংপুর'),(8,'Mymensingh','ময়মনসিংহ');

INSERT INTO `districts` (`id`, `division_id`, `name_en`, `name_bn`) VALUES
 ( 1,1,'Dhaka','ঢাকা'),( 2,1,'Gazipur','গাজীপুর'),( 3,1,'Narayanganj','নারায়ণগঞ্জ'),( 4,1,'Narsingdi','নরসিংদী'),
 ( 5,1,'Manikganj','মানিকগঞ্জ'),( 6,1,'Munshiganj','মুন্সীগঞ্জ'),( 7,1,'Tangail','টাঙ্গাইল'),( 8,1,'Kishoreganj','কিশোরগঞ্জ'),
 ( 9,1,'Faridpur','ফরিদপুর'),(10,1,'Gopalganj','গোপালগঞ্জ'),(11,1,'Madaripur','মাদারীপুর'),(12,1,'Rajbari','রাজবাড়ী'),
 (13,1,'Shariatpur','শরীয়তপুর'),
 (14,2,'Chattogram','চট্টগ্রাম'),(15,2,'Cox\'s Bazar','কক্সবাজার'),(16,2,'Cumilla','কুমিল্লা'),(17,2,'Brahmanbaria','ব্রাহ্মণবাড়িয়া'),
 (18,2,'Chandpur','চাঁদপুর'),(19,2,'Feni','ফেনী'),(20,2,'Lakshmipur','লক্ষ্মীপুর'),(21,2,'Noakhali','নোয়াখালী'),
 (22,2,'Khagrachhari','খাগড়াছড়ি'),(23,2,'Rangamati','রাঙ্গামাটি'),(24,2,'Bandarban','বান্দরবান'),
 (25,3,'Rajshahi','রাজশাহী'),(26,3,'Bogura','বগুড়া'),(27,3,'Joypurhat','জয়পুরহাট'),(28,3,'Naogaon','নওগাঁ'),
 (29,3,'Natore','নাটোর'),(30,3,'Chapainawabganj','চাঁপাইনবাবগঞ্জ'),(31,3,'Pabna','পাবনা'),(32,3,'Sirajganj','সিরাজগঞ্জ'),
 (33,4,'Khulna','খুলনা'),(34,4,'Bagerhat','বাগেরহাট'),(35,4,'Satkhira','সাতক্ষীরা'),(36,4,'Jashore','যশোর'),
 (37,4,'Jhenaidah','ঝিনাইদহ'),(38,4,'Magura','মাগুরা'),(39,4,'Narail','নড়াইল'),(40,4,'Kushtia','কুষ্টিয়া'),
 (41,4,'Chuadanga','চুয়াডাঙ্গা'),(42,4,'Meherpur','মেহেরপুর'),
 (43,5,'Barishal','বরিশাল'),(44,5,'Bhola','ভোলা'),(45,5,'Patuakhali','পটুয়াখালী'),(46,5,'Pirojpur','পিরোজপুর'),
 (47,5,'Barguna','বরগুনা'),(48,5,'Jhalokathi','ঝালকাঠি'),
 (49,6,'Sylhet','সিলেট'),(50,6,'Moulvibazar','মৌলভীবাজার'),(51,6,'Habiganj','হবিগঞ্জ'),(52,6,'Sunamganj','সুনামগঞ্জ'),
 (53,7,'Rangpur','রংপুর'),(54,7,'Dinajpur','দিনাজপুর'),(55,7,'Gaibandha','গাইবান্ধা'),(56,7,'Kurigram','কুড়িগ্রাম'),
 (57,7,'Lalmonirhat','লালমনিরহাট'),(58,7,'Nilphamari','নীলফামারী'),(59,7,'Panchagarh','পঞ্চগড়'),(60,7,'Thakurgaon','ঠাকুরগাঁও'),
 (61,8,'Mymensingh','ময়মনসিংহ'),(62,8,'Jamalpur','জামালপুর'),(63,8,'Netrokona','নেত্রকোণা'),(64,8,'Sherpur','শেরপুর');

-- One Sadar upazila per district; add the rest from Settings → Geography.
INSERT INTO `upazilas` (`district_id`, `name_en`, `name_bn`)
SELECT d.id, CONCAT(d.name_en, ' Sadar'), CONCAT(d.name_bn, ' সদর') FROM districts d ORDER BY d.id;

-- ---- Roles --------------------------------------------------------------
INSERT INTO `roles` (`id`, `slug`, `name_en`, `name_bn`, `description`, `scope`, `level`, `is_system`) VALUES
 ( 1, 'super_admin',          'Super Admin',          'সুপার অ্যাডমিন',            'Full control of every center, user, role and setting',                     'global',   1, 1),
 ( 2, 'ho_admin',             'Head Office Admin',    'প্রধান কার্যালয় অ্যাডমিন',   'Runs all centers from Head Office; cannot change roles or system settings', 'global',  10, 1),
 ( 3, 'exam_controller',      'Exam Controller',      'পরীক্ষা নিয়ন্ত্রক',           'Courses, assessments, result publishing and certificates for all centers', 'global',  15, 1),
 ( 4, 'auditor',              'Auditor / Monitor',    'অডিটর / পরিদর্শক',          'Read-only access to every center, finance and the audit trail',            'global',  15, 1),
 ( 5, 'district_coordinator', 'District Coordinator', 'জেলা সমন্বয়কারী',            'Supervises and approves for all centers of assigned district(s)',          'district', 20, 1),
 ( 6, 'center_manager',       'Center Manager',       'কেন্দ্র ব্যবস্থাপক',          'Runs one training center: staff, batches, admissions, fees, expenses',     'center',   30, 1),
 ( 7, 'accountant',           'Accountant',           'হিসাবরক্ষক',                 'Fee collection, money receipts, expenses and cash book of the center',     'center',   40, 1),
 ( 8, 'admission_officer',    'Admission Officer',    'ভর্তি কর্মকর্তা',             'Trainee registration, documents, enrolment and fee collection',            'center',   40, 1),
 ( 9, 'trainer',              'Trainer / Instructor', 'প্রশিক্ষক',                   'Own batches only: attendance, marks entry, assignments',                   'self',     50, 1),
 (10, 'student',              'Trainee (Student)',    'প্রশিক্ষণার্থী',               'Own profile, attendance, fees, results and certificate',                   'self',     90, 1);

-- ---- Permissions ----------------------------------------------------------
INSERT INTO `permissions` (`slug`, `module`, `name_en`, `name_bn`) VALUES
 ('dashboard.view',            'dashboard',  'View dashboard',                          'ড্যাশবোর্ড দেখা'),
 ('dashboard.all_centers',     'dashboard',  'View all-center consolidated dashboard',  'সকল কেন্দ্রের সমন্বিত ড্যাশবোর্ড'),
 ('center.view',               'center',     'View training centers',                   'প্রশিক্ষণ কেন্দ্র দেখা'),
 ('center.manage',             'center',     'Create/edit training centers & rooms',    'প্রশিক্ষণ কেন্দ্র ও কক্ষ ব্যবস্থাপনা'),
 ('geo.manage',                'center',     'Manage districts & upazilas',             'জেলা ও উপজেলা ব্যবস্থাপনা'),
 ('user.view',                 'user',       'View users',                              'ব্যবহারকারী দেখা'),
 ('user.create',               'user',       'Create users',                            'ব্যবহারকারী তৈরি'),
 ('user.update',               'user',       'Edit users',                              'ব্যবহারকারী সম্পাদনা'),
 ('user.deactivate',           'user',       'Activate/deactivate users',               'ব্যবহারকারী সক্রিয়/নিষ্ক্রিয়'),
 ('user.reset_password',       'user',       'Reset user passwords',                    'পাসওয়ার্ড রিসেট'),
 ('role.view',                 'user',       'View roles & permissions',                'রোল ও পারমিশন দেখা'),
 ('role.manage',               'user',       'Change role permissions',                 'রোলের পারমিশন পরিবর্তন'),
 ('staff.view',                'hr',         'View staff',                              'কর্মী দেখা'),
 ('staff.manage',              'hr',         'Create/edit staff',                       'কর্মী ব্যবস্থাপনা'),
 ('staff_attendance.view',     'hr',         'View staff attendance',                   'কর্মী হাজিরা দেখা'),
 ('staff_attendance.manage',   'hr',         'Record staff attendance',                 'কর্মী হাজিরা নেওয়া'),
 ('leave.request',             'hr',         'Apply for leave',                         'ছুটির আবেদন'),
 ('leave.approve',             'hr',         'Approve leave',                           'ছুটি অনুমোদন'),
 ('course.view',               'academic',   'View courses',                            'কোর্স দেখা'),
 ('course.manage',             'academic',   'Create/edit courses & curriculum',        'কোর্স ও কারিকুলাম ব্যবস্থাপনা'),
 ('fee_structure.manage',      'academic',   'Manage course fee structure',             'কোর্স ফি কাঠামো ব্যবস্থাপনা'),
 ('batch.view',                'academic',   'View batches',                            'ব্যাচ দেখা'),
 ('batch.manage',              'academic',   'Create/edit batches',                     'ব্যাচ ব্যবস্থাপনা'),
 ('routine.manage',            'academic',   'Manage class routine',                    'ক্লাস রুটিন ব্যবস্থাপনা'),
 ('student.view',              'student',    'View trainees',                           'প্রশিক্ষণার্থী দেখা'),
 ('student.create',            'student',    'Register trainees',                       'প্রশিক্ষণার্থী নিবন্ধন'),
 ('student.update',            'student',    'Edit trainees',                           'প্রশিক্ষণার্থী সম্পাদনা'),
 ('student.delete',            'student',    'Delete trainees',                         'প্রশিক্ষণার্থী মুছে ফেলা'),
 ('student.documents',         'student',    'Upload/view trainee documents',           'প্রশিক্ষণার্থীর কাগজপত্র'),
 ('enrollment.view',           'student',    'View enrolments',                         'ভর্তি দেখা'),
 ('enrollment.create',         'student',    'Admit trainees to batches',               'ব্যাচে ভর্তি'),
 ('enrollment.update_status',  'student',    'Change enrolment status (drop-out, complete)', 'ভর্তির অবস্থা পরিবর্তন'),
 ('enrollment.transfer',       'student',    'Transfer trainee between batches',        'ব্যাচ পরিবর্তন'),
 ('attendance.view',           'attendance', 'View trainee attendance',                 'প্রশিক্ষণার্থী হাজিরা দেখা'),
 ('attendance.take',           'attendance', 'Take trainee attendance',                 'প্রশিক্ষণার্থী হাজিরা নেওয়া'),
 ('attendance.lock',           'attendance', 'Lock attendance',                         'হাজিরা লক করা'),
 ('assessment.view',           'exam',       'View assessments',                        'মূল্যায়ন দেখা'),
 ('assessment.manage',         'exam',       'Create/schedule assessments',             'মূল্যায়ন তৈরি'),
 ('marks.entry',               'exam',       'Enter marks',                             'নম্বর এন্ট্রি'),
 ('marks.approve',             'exam',       'Approve marks',                           'নম্বর অনুমোদন'),
 ('result.process',            'exam',       'Process results',                         'ফলাফল প্রস্তুত'),
 ('result.publish',            'exam',       'Publish results',                         'ফলাফল প্রকাশ'),
 ('certificate.view',          'exam',       'View certificates',                       'সনদ দেখা'),
 ('certificate.issue',         'exam',       'Issue/print certificates',                'সনদ প্রদান/প্রিন্ট'),
 ('certificate.revoke',        'exam',       'Revoke certificates',                     'সনদ বাতিল'),
 ('assignment.view',           'academic',   'View assignments',                        'অ্যাসাইনমেন্ট দেখা'),
 ('assignment.manage',         'academic',   'Create/grade assignments',                'অ্যাসাইনমেন্ট তৈরি ও মূল্যায়ন'),
 ('fee.view',                  'finance',    'View fees & dues',                        'ফি ও বকেয়া দেখা'),
 ('fee.collect',               'finance',    'Collect fees / issue money receipts',     'ফি আদায় / মানি রসিদ'),
 ('fee.cancel_receipt',        'finance',    'Cancel money receipts',                   'মানি রসিদ বাতিল'),
 ('waiver.request',            'finance',    'Request fee waiver',                      'ফি মওকুফের আবেদন'),
 ('waiver.approve',            'finance',    'Approve fee waiver',                      'ফি মওকুফ অনুমোদন'),
 ('refund.request',            'finance',    'Request refund',                          'ফেরতের আবেদন'),
 ('refund.approve',            'finance',    'Approve refund',                          'ফেরত অনুমোদন'),
 ('expense.view',              'finance',    'View expenses',                           'খরচ দেখা'),
 ('expense.create',            'finance',    'Enter expenses',                          'খরচ এন্ট্রি'),
 ('expense.approve',           'finance',    'Approve expenses',                        'খরচ অনুমোদন'),
 ('finance.accounts_manage',   'finance',    'Manage cash/bank accounts',               'নগদ/ব্যাংক হিসাব ব্যবস্থাপনা'),
 ('finance.transfer',          'finance',    'Fund transfer / bank deposit',            'ফান্ড ট্রান্সফার / ব্যাংক জমা'),
 ('finance.cashbook',          'finance',    'View cash book & bank book',              'ক্যাশ বুক ও ব্যাংক বুক দেখা'),
 ('asset.view',                'asset',      'View assets',                             'সম্পদ দেখা'),
 ('asset.manage',              'asset',      'Register/edit assets',                    'সম্পদ নিবন্ধন'),
 ('asset.transfer',            'asset',      'Transfer assets between centers',         'সম্পদ স্থানান্তর'),
 ('placement.view',            'placement',  'View job placements',                     'চাকরি/কর্মসংস্থান দেখা'),
 ('placement.manage',          'placement',  'Record job placements',                   'কর্মসংস্থান তথ্য এন্ট্রি'),
 ('placement.verify',          'placement',  'Verify job placements',                   'কর্মসংস্থান যাচাই'),
 ('notice.view',               'communication', 'View notices',                         'নোটিশ দেখা'),
 ('notice.manage',             'communication', 'Publish notices / send SMS',           'নোটিশ প্রকাশ / এসএমএস'),
 ('task.manage',               'communication', 'Manage tasks',                         'কাজের তালিকা ব্যবস্থাপনা'),
 ('report.academic',           'report',     'Academic reports',                        'একাডেমিক রিপোর্ট'),
 ('report.finance',            'report',     'Finance reports',                         'আর্থিক রিপোর্ট'),
 ('report.consolidated',       'report',     'Consolidated (all-center) reports',       'সমন্বিত রিপোর্ট'),
 ('report.export',             'report',     'Export reports (Excel/PDF)',              'রিপোর্ট এক্সপোর্ট'),
 ('audit.view',                'system',     'View audit trail',                        'অডিট ট্রেইল দেখা'),
 ('settings.manage',           'system',     'Manage system settings',                  'সিস্টেম সেটিংস'),
 ('portal.student',            'portal',     'Trainee self-service portal',             'প্রশিক্ষণার্থী পোর্টাল');

-- ---- Role → permission matrix ------------------------------------------------
INSERT INTO `role_permissions` SELECT 1, id FROM permissions WHERE slug <> 'portal.student';

INSERT INTO `role_permissions` SELECT 2, id FROM permissions
 WHERE slug NOT IN ('role.manage', 'settings.manage', 'portal.student');

INSERT INTO `role_permissions` SELECT 3, id FROM permissions WHERE slug IN (
 'dashboard.view','dashboard.all_centers','center.view','course.view','course.manage','batch.view','student.view',
 'enrollment.view','attendance.view','assessment.view','assessment.manage','marks.approve','result.process',
 'result.publish','certificate.view','certificate.issue','certificate.revoke','report.academic',
 'report.consolidated','report.export','notice.view','leave.request');

INSERT INTO `role_permissions` SELECT 4, id FROM permissions
 WHERE slug LIKE '%.view' OR slug IN ('dashboard.all_centers','finance.cashbook','report.academic','report.finance',
                                      'report.consolidated','report.export','audit.view');

INSERT INTO `role_permissions` SELECT 5, id FROM permissions WHERE slug IN (
 'dashboard.view','center.view','user.view','role.view','staff.view','staff_attendance.view','leave.approve',
 'course.view','batch.view','student.view','enrollment.view','enrollment.update_status','attendance.view',
 'assessment.view','marks.approve','certificate.view','fee.view','waiver.approve','refund.approve',
 'expense.view','expense.approve','finance.cashbook','asset.view','asset.transfer','placement.view',
 'placement.verify','notice.view','notice.manage','task.manage','report.academic','report.finance',
 'report.export','audit.view','leave.request');

INSERT INTO `role_permissions` SELECT 6, id FROM permissions WHERE slug IN (
 'dashboard.view','center.view','user.view','user.create','user.update','user.deactivate','user.reset_password',
 'role.view','staff.view','staff.manage','staff_attendance.view','staff_attendance.manage','leave.request',
 'leave.approve','course.view','batch.view','batch.manage','routine.manage','student.view','student.create',
 'student.update','student.documents','enrollment.view','enrollment.create','enrollment.update_status',
 'enrollment.transfer','attendance.view','attendance.take','attendance.lock','assessment.view',
 'assessment.manage','marks.approve','result.process','certificate.view','assignment.view','fee.view',
 'fee.collect','fee.cancel_receipt','waiver.request','waiver.approve','refund.request','refund.approve',
 'expense.view','expense.create','expense.approve','finance.accounts_manage','finance.transfer',
 'finance.cashbook','asset.view','asset.manage','asset.transfer','placement.view','placement.manage',
 'notice.view','notice.manage','task.manage','report.academic','report.finance','report.export');

INSERT INTO `role_permissions` SELECT 7, id FROM permissions WHERE slug IN (
 'dashboard.view','student.view','enrollment.view','fee.view','fee.collect','waiver.request','refund.request',
 'expense.view','expense.create','finance.transfer','finance.cashbook','report.finance','report.export',
 'notice.view','leave.request');

INSERT INTO `role_permissions` SELECT 8, id FROM permissions WHERE slug IN (
 'dashboard.view','course.view','batch.view','student.view','student.create','student.update',
 'student.documents','enrollment.view','enrollment.create','fee.view','fee.collect','waiver.request',
 'placement.view','placement.manage','notice.view','report.academic','leave.request');

INSERT INTO `role_permissions` SELECT 9, id FROM permissions WHERE slug IN (
 'dashboard.view','course.view','batch.view','student.view','enrollment.view','attendance.view',
 'attendance.take','assessment.view','marks.entry','assignment.view','assignment.manage','notice.view',
 'leave.request');

INSERT INTO `role_permissions` SELECT 10, id FROM permissions WHERE slug IN ('portal.student', 'notice.view');

-- ---- Reference data -------------------------------------------------------
INSERT INTO `designations` (`name_en`, `name_bn`, `is_trainer`) VALUES
 ('Center Manager','কেন্দ্র ব্যবস্থাপক',0), ('Senior Trainer','সিনিয়র প্রশিক্ষক',1), ('Trainer','প্রশিক্ষক',1),
 ('Assistant Trainer','সহকারী প্রশিক্ষক',1), ('Accountant','হিসাবরক্ষক',0), ('Admission Officer','ভর্তি কর্মকর্তা',0),
 ('Office Assistant','অফিস সহকারী',0), ('Lab Assistant','ল্যাব সহকারী',0), ('Hostel Supervisor','হোস্টেল তত্ত্বাবধায়ক',0),
 ('Security Guard','নিরাপত্তা প্রহরী',0), ('Cleaner','পরিচ্ছন্নতা কর্মী',0);

INSERT INTO `trades` (`code`, `name_en`, `name_bn`) VALUES
 ('ICT',  'Computer & ICT',                     'কম্পিউটার ও আইসিটি'),
 ('GD',   'Graphic Design & Multimedia',        'গ্রাফিক ডিজাইন ও মাল্টিমিডিয়া'),
 ('ELEC', 'Electrical Installation & Maintenance', 'ইলেকট্রিক্যাল ইনস্টলেশন ও মেইনটেন্যান্স'),
 ('ELTR', 'Electronics & Mobile Servicing',     'ইলেকট্রনিক্স ও মোবাইল সার্ভিসিং'),
 ('RAC',  'Refrigeration & Air-conditioning',   'রেফ্রিজারেশন ও এয়ার-কন্ডিশনিং'),
 ('AUTO', 'Driving & Automotive',               'ড্রাইভিং ও অটোমোটিভ'),
 ('WELD', 'Welding & Fabrication',              'ওয়েল্ডিং ও ফেব্রিকেশন'),
 ('PLMB', 'Plumbing & Pipe Fitting',            'প্লাম্বিং ও পাইপ ফিটিং'),
 ('GARM', 'Tailoring & Garments',               'দর্জি ও গার্মেন্টস'),
 ('BEAU', 'Beauty Care',                        'বিউটি কেয়ার'),
 ('AGRI', 'Agriculture & Livestock',            'কৃষি ও প্রাণিসম্পদ'),
 ('HOSP', 'Hospitality & Food Processing',      'হসপিটালিটি ও খাদ্য প্রক্রিয়াজাতকরণ');

INSERT INTO `fee_heads` (`code`, `name_en`, `name_bn`, `is_refundable`, `sort_order`) VALUES
 ('ADMISSION',   'Admission Fee',   'ভর্তি ফি',        0, 1),
 ('COURSE',      'Course Fee',      'কোর্স ফি',         0, 2),
 ('RESIDENTIAL', 'Residential Fee', 'আবাসিক ফি',      0, 3),
 ('MATERIAL',    'Material Fee',    'উপকরণ ফি',        0, 4),
 ('EXAM',        'Examination Fee', 'পরীক্ষা ফি',       0, 5),
 ('CERTIFICATE', 'Certificate Fee', 'সনদ ফি',          0, 6),
 ('DEPOSIT',     'Security Deposit','জামানত',          1, 7);

INSERT INTO `grade_scales` (`scheme`, `grade`, `label_en`, `label_bn`, `min_percent`, `max_percent`, `grade_point`, `is_pass`) VALUES
 ('gpa','A+','Outstanding','অসাধারণ',80,100,5.00,1), ('gpa','A','Excellent','চমৎকার',70,79.99,4.00,1),
 ('gpa','A-','Very Good','খুব ভালো',60,69.99,3.50,1), ('gpa','B','Good','ভালো',50,59.99,3.00,1),
 ('gpa','C','Satisfactory','সন্তোষজনক',40,49.99,2.00,1), ('gpa','F','Fail','অকৃতকার্য',0,39.99,0.00,0),
 ('cbt','C','Competent','দক্ষ',50,100,NULL,1), ('cbt','NYC','Not Yet Competent','এখনও দক্ষ নয়',0,49.99,NULL,0);

INSERT INTO `expense_categories` (`code`, `name_en`, `name_bn`) VALUES
 ('RENT','House/Office Rent','ভাড়া'), ('UTIL','Electricity, Water & Gas','বিদ্যুৎ, পানি ও গ্যাস'),
 ('SAL','Salaries & Honorarium','বেতন ও সম্মানী'), ('MAT','Training Materials','প্রশিক্ষণ উপকরণ'),
 ('STAT','Printing & Stationery','মুদ্রণ ও স্টেশনারি'), ('MAINT','Repairs & Maintenance','মেরামত ও রক্ষণাবেক্ষণ'),
 ('NET','Internet & Mobile','ইন্টারনেট ও মোবাইল'), ('TRVL','Travel & Conveyance','যাতায়াত'),
 ('FOOD','Hostel Food','হোস্টেল খাবার'), ('EXAM','Examination & Certification','পরীক্ষা ও সনদ'),
 ('ENT','Entertainment & Events','আপ্যায়ন ও অনুষ্ঠান'), ('MISC','Miscellaneous','বিবিধ');

INSERT INTO `asset_categories` (`name_en`, `name_bn`) VALUES
 ('Computer & Laptop','কম্পিউটার ও ল্যাপটপ'), ('Printer & Scanner','প্রিন্টার ও স্ক্যানার'),
 ('Projector & AV','প্রজেক্টর ও অডিও-ভিডিও'), ('Training Equipment & Tools','প্রশিক্ষণ যন্ত্রপাতি'),
 ('Sewing Machine','সেলাই মেশিন'), ('Vehicle','যানবাহন'), ('Furniture','আসবাবপত্র'),
 ('Electrical Appliance','বৈদ্যুতিক সরঞ্জাম'), ('Attendance Device','হাজিরা ডিভাইস');

INSERT INTO `fiscal_years` (`name`, `start_date`, `end_date`) VALUES ('FY 2026-27', '2026-07-01', '2027-06-30');

-- Head Office record — edit the address after installation; add training centers from the app.
INSERT INTO `training_centers` (`id`, `code`, `name_en`, `name_bn`, `center_type`, `district_id`, `address`, `is_active`) VALUES
 (1, 'HO', 'Head Office', 'প্রধান কার্যালয়', 'head_office', (SELECT id FROM districts WHERE name_en = 'Rangpur'), 'Update address in Settings → Centers', 1);

INSERT INTO `finance_accounts` (`center_id`, `account_type`, `name`) VALUES (1, 'cash', 'Head Office Cash');

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
 ('institute_name_en',         'RDRS Technical Training Institute', 'general',  'Printed on receipts and certificates'),
 ('institute_name_bn',         'আরডিআরএস কারিগরি প্রশিক্ষণ ইনস্টিটিউট', 'general', NULL),
 ('institute_short_name',      'RTTI',                               'general',  'Prefix for certificate numbers'),
 ('default_locale',            'bn',                                 'general',  'bn or en'),
 ('currency_symbol',           '৳',                                  'general',  NULL),
 ('min_attendance_percent',    '80',                                 'academic', 'Default eligibility for final assessment'),
 ('grading_scheme',            'gpa',                                'academic', 'gpa or cbt'),
 ('fee_due_reminder_days',     '3',                                  'finance',  'SMS reminder before due date'),
 ('max_waiver_percent_center', '25',                                 'finance',  'Above this a district coordinator / HO must approve'),
 ('sms_enabled',               '0',                                  'sms',      NULL),
 ('absence_sms_after_days',    '2',                                  'sms',      'Notify guardian after consecutive absences'),
 ('login_max_attempts',        '5',                                  'security', NULL),
 ('login_lockout_minutes',     '15',                                 'security', NULL),
 ('password_min_length',       '8',                                  'security', NULL);

-- Super Admin — username: superadmin  password: Admin@12345 (must be changed at first login)
INSERT INTO `users` (`id`, `role_id`, `center_id`, `name`, `name_bn`, `username`, `email`, `mobile`, `password_hash`, `locale`, `status`, `must_change_password`, `password_changed_at`) VALUES
 (1, 1, 1, 'System Administrator', 'সিস্টেম অ্যাডমিনিস্ট্রেটর', 'superadmin', 'admin@rtti.local', '01700000000',
  '$2y$12$bRqWOBiWF2udP8gJOR/ds.O5TpKQL.A67KTz6vZoSpp6S7zALqXBu', 'bn', 'active', 1, NOW());

UPDATE `training_centers` SET `manager_user_id` = 1 WHERE `id` = 1;

SET FOREIGN_KEY_CHECKS = 1;
-- =====================================================================
--  END OF SCHEMA
-- =====================================================================
