-- =====================================================================
--  Courier Management সার্ভিস
--  Complete MySQL 8.0+ schema  (InnoDB · utf8mb4 · ACID · FK · Triggers)
--
--  Install:
--    mysql -u root -p < database/schema.sql
--  (phpMyAdmin "Import" also works: it understands DELIMITER.)
--
--  Sections
--    01  Database & session settings
--    02  Geography: divisions, districts, zones, thanas
--    03  Organisation: branches, coverage, hub routing, vehicles
--    04  Identity & RBAC: roles, permissions, users, sessions, login throttling
--    05  Merchants (B2B) & customers (B2C)
--    06  Pricing / tariff engine
--    07  Parcels, tracking timeline, scans, bulk uploads, pickups
--    08  Line-haul: manifests, bags, manifest items
--    09  Last-mile delivery: riders, partners, DRS, POD, OTP, RTO, cash handover
--    10  Double-entry accounting: fiscal years, COA, journals, vouchers,
--        invoices, receipts, merchant COD settlement
--    11  System: settings, audit log, notifications, SMS log
--    12  Triggers (ledger integrity + audit trail)
--    13  Reporting views
--    14  Seed data
--
--  Audit context: the application runs, right after connecting,
--    SET @app_user_id = ?, @app_branch_id = ?, @app_ip = ?;
--  so database triggers can attribute every change to a user.
-- =====================================================================

-- Note: columns used in CHECK constraints are referenced by FKs with
-- RESTRICT actions only (MySQL error 3823 otherwise).
-- ---------------------------------------------------------------------
-- 01  DATABASE
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `courier_management`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `courier_management`;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+06:00';
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------
-- 02  GEOGRAPHY
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `divisions`;
CREATE TABLE `divisions` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(10)  NOT NULL,
  `name_en`     VARCHAR(100) NOT NULL,
  `name_bn`     VARCHAR(100) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_divisions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Administrative divisions (বিভাগ)';

DROP TABLE IF EXISTS `districts`;
CREATE TABLE `districts` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` SMALLINT UNSIGNED NOT NULL,
  `name_en`     VARCHAR(100) NOT NULL,
  `name_bn`     VARCHAR(100) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_districts_name` (`division_id`, `name_en`),
  CONSTRAINT `fk_districts_division` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Districts (জেলা)';

DROP TABLE IF EXISTS `zones`;
CREATE TABLE `zones` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(10)  NOT NULL,
  `name_en`     VARCHAR(100) NOT NULL,
  `name_bn`     VARCHAR(100) NOT NULL,
  `zone_type`   ENUM('metro','suburb','district','remote') NOT NULL DEFAULT 'district',
  `description` VARCHAR(255) NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zones_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pricing zones used by the tariff engine';

DROP TABLE IF EXISTS `thanas`;
CREATE TABLE `thanas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `district_id` SMALLINT UNSIGNED NOT NULL,
  `zone_id`     SMALLINT UNSIGNED NOT NULL,
  `name_en`     VARCHAR(100) NOT NULL,
  `name_bn`     VARCHAR(100) NOT NULL,
  `postcode`    VARCHAR(10)  NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_thanas_name` (`district_id`, `name_en`),
  KEY `idx_thanas_zone` (`zone_id`),
  CONSTRAINT `fk_thanas_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_thanas_zone`     FOREIGN KEY (`zone_id`)     REFERENCES `zones` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Thanas / Upazilas / delivery areas (থানা)';

-- ---------------------------------------------------------------------
-- 03  ORGANISATION
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id`             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(12)  NOT NULL,
  `name_en`        VARCHAR(150) NOT NULL,
  `name_bn`        VARCHAR(150) NOT NULL,
  `branch_type`    ENUM('head_office','hub','branch') NOT NULL DEFAULT 'branch',
  `parent_hub_id`  SMALLINT UNSIGNED NULL COMMENT 'Hub this branch feeds into (hub-and-spoke)',
  `thana_id`       INT UNSIGNED NOT NULL,
  `address`        VARCHAR(255) NOT NULL,
  `phone`          VARCHAR(20)  NULL,
  `email`          VARCHAR(150) NULL,
  `manager_user_id` INT UNSIGNED NULL,
  `latitude`       DECIMAL(10,7) NULL,
  `longitude`      DECIMAL(10,7) NULL,
  `opening_time`   TIME NULL,
  `closing_time`   TIME NULL,
  `cash_limit`     DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Max cash the branch may hold before a bank deposit is due',
  `can_book`       TINYINT(1) NOT NULL DEFAULT 1,
  `can_deliver`    TINYINT(1) NOT NULL DEFAULT 1,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP  NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     TIMESTAMP  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_code` (`code`),
  KEY `idx_branches_type` (`branch_type`, `is_active`),
  KEY `idx_branches_parent` (`parent_hub_id`),
  KEY `idx_branches_thana` (`thana_id`),
  CONSTRAINT `fk_branches_parent_hub` FOREIGN KEY (`parent_hub_id`)  REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_branches_thana`      FOREIGN KEY (`thana_id`)       REFERENCES `thanas` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_branches_manager`    FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`)   ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Head office, regional hubs and local branches';

DROP TABLE IF EXISTS `branch_coverage`;
CREATE TABLE `branch_coverage` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thana_id`      INT UNSIGNED NOT NULL,
  `branch_id`     SMALLINT UNSIGNED NOT NULL COMMENT 'Destination branch responsible for last-mile delivery',
  `delivery_mode` ENUM('own_fleet','partner') NOT NULL DEFAULT 'own_fleet',
  `partner_id`    SMALLINT UNSIGNED NULL COMMENT 'Default 3PL partner when delivery_mode = partner',
  `sla_hours`     SMALLINT UNSIGNED NULL,
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP  NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branch_coverage_thana` (`thana_id`),
  KEY `idx_branch_coverage_branch` (`branch_id`),
  CONSTRAINT `fk_coverage_thana`   FOREIGN KEY (`thana_id`)   REFERENCES `thanas` (`id`)           ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_coverage_branch`  FOREIGN KEY (`branch_id`)  REFERENCES `branches` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_coverage_partner` FOREIGN KEY (`partner_id`) REFERENCES `courier_partners` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Which branch delivers to which thana (auto destination-branch resolution)';

DROP TABLE IF EXISTS `routing_rules`;
CREATE TABLE `routing_rules` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `origin_hub_id`      SMALLINT UNSIGNED NOT NULL,
  `destination_hub_id` SMALLINT UNSIGNED NOT NULL,
  `via_hub_id`         SMALLINT UNSIGNED NULL COMMENT 'Optional intermediate hub',
  `transit_hours`      SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  `line_haul_mode`     ENUM('road','rail','air','river') NOT NULL DEFAULT 'road',
  `departure_time`     TIME NULL COMMENT 'Scheduled line-haul cut-off',
  `priority`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`         TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP  NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_routing_pair` (`origin_hub_id`, `destination_hub_id`, `priority`),
  KEY `idx_routing_dest` (`destination_hub_id`),
  CONSTRAINT `fk_routing_origin` FOREIGN KEY (`origin_hub_id`)      REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_routing_dest`   FOREIGN KEY (`destination_hub_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_routing_via`    FOREIGN KEY (`via_hub_id`)         REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inter-hub line-haul routing rules';

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id`       SMALLINT UNSIGNED NOT NULL COMMENT 'Home branch/hub',
  `registration_no` VARCHAR(30) NOT NULL,
  `vehicle_type`    ENUM('motorbike','bicycle','van','pickup','covered_van','truck') NOT NULL,
  `ownership`       ENUM('own','rented','partner') NOT NULL DEFAULT 'own',
  `capacity_kg`     DECIMAL(10,2) NULL,
  `driver_name`     VARCHAR(100) NULL,
  `driver_phone`    VARCHAR(20)  NULL,
  `fitness_expiry`  DATE NULL,
  `insurance_expiry` DATE NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP  NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_reg` (`registration_no`),
  KEY `idx_vehicles_branch` (`branch_id`),
  CONSTRAINT `fk_vehicles_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fleet / line-haul vehicles';

-- ---------------------------------------------------------------------
-- 04  IDENTITY & RBAC
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(40)  NOT NULL,
  `name_en`     VARCHAR(80)  NOT NULL,
  `name_bn`     VARCHAR(80)  NOT NULL,
  `description` VARCHAR(255) NULL,
  `scope`       ENUM('global','branch','self') NOT NULL DEFAULT 'branch' COMMENT 'global = all branches, branch = own branch(es), self = own records (merchant/rider)',
  `is_system`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System roles cannot be deleted',
  `created_at`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP  NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(80)  NOT NULL COMMENT 'e.g. parcel.create — referenced by permission:<slug> middleware',
  `module`      VARCHAR(40)  NOT NULL,
  `name_en`     VARCHAR(120) NOT NULL,
  `name_bn`     VARCHAR(120) NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id`       TINYINT UNSIGNED  NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_role_permissions_perm` (`permission_id`),
  CONSTRAINT `fk_rp_role`       FOREIGN KEY (`role_id`)       REFERENCES `roles` (`id`)       ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id`             TINYINT UNSIGNED  NOT NULL,
  `branch_id`           SMALLINT UNSIGNED NULL COMMENT 'Home branch (NULL for merchants)',
  `merchant_id`         INT UNSIGNED NULL COMMENT 'Set for Corporate Merchant portal users',
  `employee_code`       VARCHAR(30)  NULL,
  `name`                VARCHAR(120) NOT NULL,
  `name_bn`             VARCHAR(120) NULL,
  `email`               VARCHAR(150) NOT NULL,
  `phone`               VARCHAR(20)  NOT NULL,
  `password_hash`       VARCHAR(255) NOT NULL,
  `avatar_path`         VARCHAR(255) NULL,
  `locale`              ENUM('bn','en') NOT NULL DEFAULT 'bn',
  `status`              ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_secret`   VARCHAR(255) NULL COMMENT 'Encrypted (Crypto::encrypt)',
  `failed_login_count`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`        DATETIME NULL,
  `last_login_at`       DATETIME NULL,
  `last_login_ip`       VARCHAR(45) NULL,
  `password_changed_at` DATETIME NULL,
  `remember_token_hash` CHAR(64) NULL,
  `created_by`          INT UNSIGNED NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`),
  UNIQUE KEY `uq_users_employee_code` (`employee_code`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_branch` (`branch_id`, `status`),
  KEY `idx_users_merchant` (`merchant_id`),
  CONSTRAINT `fk_users_role`     FOREIGN KEY (`role_id`)     REFERENCES `roles` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_users_branch`   FOREIGN KEY (`branch_id`)   REFERENCES `branches` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_users_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_users_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `user_id`       INT UNSIGNED      NOT NULL,
  `permission_id` SMALLINT UNSIGNED NOT NULL,
  `is_granted`    TINYINT(1)        NOT NULL DEFAULT 1 COMMENT '1 = extra grant, 0 = explicit revoke (overrides role)',
  `granted_by`    INT UNSIGNED      NULL,
  `created_at`    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `permission_id`),
  KEY `idx_user_permissions_perm` (`permission_id`),
  CONSTRAINT `fk_up_user`       FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_up_granted_by` FOREIGN KEY (`granted_by`)    REFERENCES `users` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-user permission overrides on top of the role';

DROP TABLE IF EXISTS `user_branch_access`;
CREATE TABLE `user_branch_access` (
  `user_id`    INT UNSIGNED      NOT NULL,
  `branch_id`  SMALLINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `branch_id`),
  KEY `idx_uba_branch` (`branch_id`),
  CONSTRAINT `fk_uba_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_uba_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Extra branches a user may operate on (e.g. regional accountant)';

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`   VARCHAR(150) NOT NULL COMMENT 'Email/phone as typed (lower-cased)',
  `ip_address`   VARCHAR(45)  NOT NULL,
  `user_agent`   VARCHAR(255) NULL,
  `successful`   TINYINT(1)   NOT NULL DEFAULT 0,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_identifier` (`identifier`, `attempted_at`),
  KEY `idx_login_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Brute-force throttling by identifier and IP';

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token_hash`  CHAR(64)     NOT NULL COMMENT 'SHA-256 of the emailed token',
  `ip_address`  VARCHAR(45)  NULL,
  `expires_at`  DATETIME     NOT NULL,
  `used_at`     DATETIME     NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_resets_token` (`token_hash`),
  KEY `idx_password_resets_user` (`user_id`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id`            VARCHAR(128) NOT NULL COMMENT 'PHP session id',
  `user_id`       INT UNSIGNED NULL,
  `ip_address`    VARCHAR(45)  NULL,
  `user_agent`    VARCHAR(255) NULL,
  `payload`       MEDIUMBLOB   NOT NULL,
  `last_activity` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user` (`user_id`),
  KEY `idx_user_sessions_activity` (`last_activity`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Optional DB session store (multi-server deployments, force-logout)';

-- ---------------------------------------------------------------------
-- 05  MERCHANTS (B2B) & CUSTOMERS (B2C)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `merchants`;
CREATE TABLE `merchants` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`               VARCHAR(20)  NOT NULL COMMENT 'e.g. M-000123',
  `business_name`      VARCHAR(150) NOT NULL,
  `business_name_bn`   VARCHAR(150) NULL,
  `owner_name`         VARCHAR(120) NOT NULL,
  `phone`              VARCHAR(20)  NOT NULL,
  `email`              VARCHAR(150) NULL,
  `address`            VARCHAR(255) NOT NULL,
  `thana_id`           INT UNSIGNED NOT NULL,
  `pickup_branch_id`   SMALLINT UNSIGNED NOT NULL COMMENT 'Branch that picks up / books this merchant',
  `merchant_type`      ENUM('corporate','sme','individual') NOT NULL DEFAULT 'sme',
  `business_category`  VARCHAR(80) NULL COMMENT 'Fashion, Electronics, Pharmacy …',
  `website`            VARCHAR(200) NULL,
  `trade_license_no`   VARCHAR(60) NULL,
  `tin_no`             VARCHAR(30) NULL,
  `bin_no`             VARCHAR(30) NULL COMMENT 'VAT Business Identification Number',
  `nid_no`             VARCHAR(30) NULL,
  `billing_type`       ENUM('cod_deduction','credit_invoice','prepaid') NOT NULL DEFAULT 'cod_deduction'
                       COMMENT 'cod_deduction = charges deducted from COD payout; credit_invoice = periodic invoice',
  `settlement_cycle`   ENUM('daily','weekly','biweekly','monthly','on_request') NOT NULL DEFAULT 'weekly',
  `credit_limit`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_terms_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `security_deposit`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`             ENUM('pending','active','suspended','closed') NOT NULL DEFAULT 'pending',
  `approved_by`        INT UNSIGNED NULL,
  `approved_at`        DATETIME NULL,
  `notes`              TEXT NULL,
  `created_by`         INT UNSIGNED NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_merchants_code` (`code`),
  UNIQUE KEY `uq_merchants_phone` (`phone`),
  KEY `idx_merchants_branch` (`pickup_branch_id`, `status`),
  KEY `idx_merchants_name` (`business_name`),
  CONSTRAINT `fk_merchants_thana`    FOREIGN KEY (`thana_id`)         REFERENCES `thanas` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_merchants_branch`   FOREIGN KEY (`pickup_branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_merchants_approver` FOREIGN KEY (`approved_by`)      REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_merchants_creator`  FOREIGN KEY (`created_by`)       REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2B corporate / SME merchants';

DROP TABLE IF EXISTS `merchant_payout_accounts`;
CREATE TABLE `merchant_payout_accounts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id`    INT UNSIGNED NOT NULL,
  `method`         ENUM('bank','bkash','nagad','rocket','cash') NOT NULL,
  `account_name`   VARCHAR(120) NOT NULL,
  `account_number` VARCHAR(40)  NOT NULL,
  `bank_name`      VARCHAR(120) NULL,
  `bank_branch`    VARCHAR(120) NULL,
  `routing_number` VARCHAR(20)  NULL,
  `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
  `is_verified`    TINYINT(1)   NOT NULL DEFAULT 0,
  `verified_by`    INT UNSIGNED NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpa_merchant` (`merchant_id`, `is_default`),
  CONSTRAINT `fk_mpa_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpa_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Where merchant COD payouts are sent';

DROP TABLE IF EXISTS `merchant_pickup_addresses`;
CREATE TABLE `merchant_pickup_addresses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id`   INT UNSIGNED NOT NULL,
  `label`         VARCHAR(60)  NOT NULL,
  `contact_name`  VARCHAR(120) NOT NULL,
  `contact_phone` VARCHAR(20)  NOT NULL,
  `address`       VARCHAR(255) NOT NULL,
  `thana_id`      INT UNSIGNED NOT NULL,
  `is_default`    TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpka_merchant` (`merchant_id`),
  CONSTRAINT `fk_mpka_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpka_thana`    FOREIGN KEY (`thana_id`)    REFERENCES `thanas` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `merchant_api_keys`;
CREATE TABLE `merchant_api_keys` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id`  INT UNSIGNED NOT NULL,
  `name`         VARCHAR(80)  NOT NULL,
  `key_prefix`   CHAR(8)      NOT NULL COMMENT 'First characters shown in the UI for identification',
  `key_hash`     CHAR(64)     NOT NULL COMMENT 'HMAC-SHA256 of the full key; the key itself is shown once',
  `scopes`       JSON         NULL,
  `last_used_at` DATETIME     NULL,
  `last_used_ip` VARCHAR(45)  NULL,
  `expires_at`   DATETIME     NULL,
  `revoked_at`   DATETIME     NULL,
  `created_by`   INT UNSIGNED NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mak_hash` (`key_hash`),
  KEY `idx_mak_merchant` (`merchant_id`),
  CONSTRAINT `fk_mak_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mak_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant REST API credentials';

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `phone`       VARCHAR(20)  NOT NULL,
  `alt_phone`   VARCHAR(20)  NULL,
  `email`       VARCHAR(150) NULL,
  `address`     VARCHAR(255) NULL,
  `thana_id`    INT UNSIGNED NULL,
  `nid_no`      VARCHAR(30)  NULL,
  `total_bookings` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_phone` (`phone`),
  KEY `idx_customers_name` (`name`),
  CONSTRAINT `fk_customers_thana`   FOREIGN KEY (`thana_id`)   REFERENCES `thanas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_customers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2C walk-in senders (auto-filled by phone)';

-- ---------------------------------------------------------------------
-- 06  PRICING / TARIFF ENGINE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `service_types`;
CREATE TABLE `service_types` (
  `id`              TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(10)  NOT NULL,
  `name_en`         VARCHAR(80)  NOT NULL,
  `name_bn`         VARCHAR(80)  NOT NULL,
  `sla_hours`       SMALLINT UNSIGNED NOT NULL DEFAULT 72,
  `is_fragile`      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Forces fragile handling label',
  `same_zone_only`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Only offered when origin zone = destination zone',
  `booking_cutoff`  TIME NULL COMMENT 'Bookings after this time roll to next day (e.g. same-day)',
  `sort_order`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `packaging_types`;
CREATE TABLE `packaging_types` (
  `id`         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(20)  NOT NULL,
  `name_en`    VARCHAR(80)  NOT NULL,
  `name_bn`    VARCHAR(80)  NOT NULL,
  `charge`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_packaging_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rate_cards`;
CREATE TABLE `rate_cards` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(30)  NOT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `card_type`      ENUM('standard','b2c','b2b','merchant_custom') NOT NULL DEFAULT 'standard',
  `merchant_id`    INT UNSIGNED NULL COMMENT 'Only for card_type = merchant_custom',
  `is_default`     TINYINT(1) NOT NULL DEFAULT 0,
  `volumetric_divisor` SMALLINT UNSIGNED NOT NULL DEFAULT 5000 COMMENT 'L×W×H (cm) / divisor = volumetric kg',
  `effective_from` DATE NOT NULL,
  `effective_to`   DATE NULL,
  `status`         ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  `created_by`     INT UNSIGNED NULL,
  `approved_by`    INT UNSIGNED NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_cards_code` (`code`),
  KEY `idx_rate_cards_lookup` (`card_type`, `status`, `effective_from`),
  KEY `idx_rate_cards_merchant` (`merchant_id`),
  CONSTRAINT `fk_rate_cards_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_rate_cards_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rate_cards_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_rate_cards_dates`   CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tariff headers (standard, B2C, B2B, merchant-specific)';

DROP TABLE IF EXISTS `rate_card_items`;
CREATE TABLE `rate_card_items` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rate_card_id`          INT UNSIGNED NOT NULL,
  `origin_zone_id`        SMALLINT UNSIGNED NOT NULL,
  `destination_zone_id`   SMALLINT UNSIGNED NOT NULL,
  `service_type_id`       TINYINT UNSIGNED NOT NULL,
  `base_weight_kg`        DECIMAL(8,3)  NOT NULL DEFAULT 1.000,
  `base_charge`           DECIMAL(10,2) NOT NULL,
  `additional_weight_unit_kg` DECIMAL(8,3) NOT NULL DEFAULT 1.000 COMMENT 'Each started unit above base is charged',
  `additional_charge`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Charge per additional unit',
  `max_weight_kg`         DECIMAL(8,3)  NULL,
  `cod_percent`           DECIMAL(5,2)  NOT NULL DEFAULT 1.00,
  `cod_min_charge`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `return_charge_percent` DECIMAL(5,2)  NOT NULL DEFAULT 50.00 COMMENT '% of delivery charge billed on RTO',
  `fragile_surcharge`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sla_hours`             SMALLINT UNSIGNED NULL COMMENT 'Overrides service_types.sla_hours',
  `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_item` (`rate_card_id`, `origin_zone_id`, `destination_zone_id`, `service_type_id`),
  KEY `idx_rate_item_zones` (`origin_zone_id`, `destination_zone_id`),
  KEY `idx_rate_item_service` (`service_type_id`),
  CONSTRAINT `fk_rci_card`    FOREIGN KEY (`rate_card_id`)        REFERENCES `rate_cards` (`id`)    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_rci_origin`  FOREIGN KEY (`origin_zone_id`)      REFERENCES `zones` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rci_dest`    FOREIGN KEY (`destination_zone_id`) REFERENCES `zones` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rci_service` FOREIGN KEY (`service_type_id`)     REFERENCES `service_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_rci_amounts` CHECK (`base_charge` >= 0 AND `additional_charge` >= 0 AND `cod_percent` BETWEEN 0 AND 100
                                     AND `return_charge_percent` BETWEEN 0 AND 100 AND `base_weight_kg` > 0
                                     AND `additional_weight_unit_kg` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zone × Zone × Service tariff with weight slabs';

DROP TABLE IF EXISTS `merchant_rate_agreements`;
CREATE TABLE `merchant_rate_agreements` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id`            INT UNSIGNED NOT NULL,
  `rate_card_id`           INT UNSIGNED NOT NULL,
  `discount_percent`       DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Applied on delivery charge',
  `cod_percent_override`   DECIMAL(5,2) NULL,
  `return_percent_override` DECIMAL(5,2) NULL,
  `effective_from`         DATE NOT NULL,
  `effective_to`           DATE NULL,
  `agreement_ref`          VARCHAR(60) NULL,
  `status`                 ENUM('draft','active','expired','terminated') NOT NULL DEFAULT 'draft',
  `approved_by`            INT UNSIGNED NULL,
  `approved_at`            DATETIME NULL,
  `created_by`             INT UNSIGNED NULL,
  `created_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mra_lookup` (`merchant_id`, `status`, `effective_from`),
  KEY `idx_mra_card` (`rate_card_id`),
  CONSTRAINT `fk_mra_merchant` FOREIGN KEY (`merchant_id`)  REFERENCES `merchants` (`id`)  ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mra_card`     FOREIGN KEY (`rate_card_id`) REFERENCES `rate_cards` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_mra_approver` FOREIGN KEY (`approved_by`)  REFERENCES `users` (`id`)      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_mra_creator`  FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`)      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_mra_values`  CHECK (`discount_percent` BETWEEN 0 AND 100
                                     AND (`cod_percent_override` IS NULL OR `cod_percent_override` BETWEEN 0 AND 100)
                                     AND (`return_percent_override` IS NULL OR `return_percent_override` BETWEEN 0 AND 100)
                                     AND (`effective_to` IS NULL OR `effective_to` >= `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2B contracted pricing per merchant';

-- ---------------------------------------------------------------------
-- 07  PARCELS, TRACKING, SCANS, BULK UPLOADS, PICKUPS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `number_sequences`;
CREATE TABLE `number_sequences` (
  `seq_key`    VARCHAR(40) NOT NULL COMMENT 'tracking, manifest, bag, drs, journal, voucher, invoice, settlement, receipt, rto, handover, merchant',
  `seq_period` VARCHAR(10) NOT NULL COMMENT 'YYYYMMDD, YYYYMM or "ALL"',
  `last_value` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`seq_key`, `seq_period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Gap-free, race-safe document numbering (SELECT … FOR UPDATE / LAST_INSERT_ID pattern)';

DROP TABLE IF EXISTS `parcel_statuses`;
CREATE TABLE `parcel_statuses` (
  `code`         VARCHAR(30) NOT NULL,
  `name_en`      VARCHAR(80) NOT NULL,
  `name_bn`      VARCHAR(80) NOT NULL,
  `stage`        ENUM('booking','pickup','origin','transit','destination','delivery','return','closed') NOT NULL,
  `badge_class`  VARCHAR(40) NOT NULL DEFAULT 'secondary' COMMENT 'Bootstrap contextual class',
  `is_terminal`  TINYINT(1)  NOT NULL DEFAULT 0,
  `is_public`    TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'Shown on public tracking page',
  `sort_order`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bilingual parcel status master';

DROP TABLE IF EXISTS `bulk_uploads`;
CREATE TABLE `bulk_uploads` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id`    INT UNSIGNED NOT NULL,
  `uploaded_by`    INT UNSIGNED NOT NULL,
  `original_name`  VARCHAR(255) NOT NULL,
  `stored_path`    VARCHAR(255) NOT NULL,
  `file_hash`      CHAR(64)     NOT NULL COMMENT 'SHA-256 to block duplicate uploads',
  `total_rows`     INT UNSIGNED NOT NULL DEFAULT 0,
  `valid_rows`     INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `status`         ENUM('uploaded','validating','validated','importing','completed','failed','cancelled') NOT NULL DEFAULT 'uploaded',
  `error_report`   JSON NULL COMMENT '[{"row":5,"errors":{"receiver_phone":["…"]}}]',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bulk_uploads_hash` (`merchant_id`, `file_hash`),
  KEY `idx_bulk_uploads_status` (`status`),
  CONSTRAINT `fk_bulk_uploads_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_bulk_uploads_user`     FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant CSV/Excel bulk booking batches';

DROP TABLE IF EXISTS `pickup_requests`;
CREATE TABLE `pickup_requests` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pickup_no`         VARCHAR(25)  NOT NULL,
  `merchant_id`       INT UNSIGNED NOT NULL,
  `pickup_address_id` INT UNSIGNED NULL,
  `branch_id`         SMALLINT UNSIGNED NOT NULL,
  `requested_date`    DATE NOT NULL,
  `time_slot`         VARCHAR(30) NULL,
  `estimated_parcels` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `picked_parcels`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `assigned_rider_id` INT UNSIGNED NULL,
  `status`            ENUM('requested','assigned','picked','partially_picked','cancelled','failed') NOT NULL DEFAULT 'requested',
  `picked_at`         DATETIME NULL,
  `notes`             VARCHAR(255) NULL,
  `created_by`        INT UNSIGNED NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pickup_no` (`pickup_no`),
  KEY `idx_pickup_branch_date` (`branch_id`, `requested_date`, `status`),
  KEY `idx_pickup_merchant` (`merchant_id`),
  KEY `idx_pickup_rider` (`assigned_rider_id`),
  CONSTRAINT `fk_pickup_merchant` FOREIGN KEY (`merchant_id`)       REFERENCES `merchants` (`id`)                 ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pickup_address`  FOREIGN KEY (`pickup_address_id`) REFERENCES `merchant_pickup_addresses` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pickup_branch`   FOREIGN KEY (`branch_id`)         REFERENCES `branches` (`id`)                  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pickup_rider`    FOREIGN KEY (`assigned_rider_id`) REFERENCES `riders` (`id`)                    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pickup_creator`  FOREIGN KEY (`created_by`)        REFERENCES `users` (`id`)                     ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant pickup scheduling';

DROP TABLE IF EXISTS `parcels`;
CREATE TABLE `parcels` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tracking_no`           VARCHAR(25)  NOT NULL COMMENT 'AWB: CMS-YYYYMMDD-XXXXXX',
  `booking_type`          ENUM('b2b','b2c') NOT NULL,
  `booking_channel`       ENUM('counter','merchant_portal','bulk_upload','api','pickup') NOT NULL DEFAULT 'counter',
  `merchant_id`           INT UNSIGNED NULL,
  `merchant_order_ref`    VARCHAR(60)  NULL COMMENT 'Merchant invoice/order id',
  `customer_id`           INT UNSIGNED NULL COMMENT 'B2C sender',
  `bulk_upload_id`        INT UNSIGNED NULL,
  `pickup_request_id`     INT UNSIGNED NULL,
  `parent_parcel_id`      BIGINT UNSIGNED NULL COMMENT 'Original parcel for exchange / partial-return child shipments',

  -- Sender
  `sender_name`           VARCHAR(120) NOT NULL,
  `sender_phone`          VARCHAR(20)  NOT NULL,
  `sender_address`        VARCHAR(255) NOT NULL,
  `sender_thana_id`       INT UNSIGNED NULL,

  -- Receiver
  `receiver_name`         VARCHAR(120) NOT NULL,
  `receiver_phone`        VARCHAR(20)  NOT NULL,
  `receiver_alt_phone`    VARCHAR(20)  NULL,
  `receiver_address`      VARCHAR(255) NOT NULL,
  `receiver_thana_id`     INT UNSIGNED NOT NULL,
  `receiver_landmark`     VARCHAR(150) NULL,

  -- Routing
  `origin_branch_id`      SMALLINT UNSIGNED NOT NULL,
  `destination_branch_id` SMALLINT UNSIGNED NOT NULL,
  `current_branch_id`     SMALLINT UNSIGNED NULL COMMENT 'NULL while in line-haul transit',
  `origin_zone_id`        SMALLINT UNSIGNED NOT NULL,
  `destination_zone_id`   SMALLINT UNSIGNED NOT NULL,
  `delivery_mode`         ENUM('own_fleet','partner') NOT NULL DEFAULT 'own_fleet',
  `partner_id`            SMALLINT UNSIGNED NULL,
  `partner_tracking_no`   VARCHAR(60)  NULL,

  -- Shipment
  `service_type_id`       TINYINT UNSIGNED NOT NULL,
  `rate_card_id`          INT UNSIGNED NULL,
  `rate_card_item_id`     INT UNSIGNED NULL,
  `product_description`   VARCHAR(255) NULL,
  `item_count`            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `declared_value`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `actual_weight_kg`      DECIMAL(8,3)  NOT NULL DEFAULT 0.500,
  `length_cm`             DECIMAL(7,2)  NULL,
  `width_cm`              DECIMAL(7,2)  NULL,
  `height_cm`             DECIMAL(7,2)  NULL,
  `volumetric_weight_kg`  DECIMAL(8,3)  NOT NULL DEFAULT 0.000,
  `chargeable_weight_kg`  DECIMAL(8,3)  NOT NULL DEFAULT 0.500,
  `is_fragile`            TINYINT(1)    NOT NULL DEFAULT 0,
  `packaging_type_id`     TINYINT UNSIGNED NULL,

  -- Money
  `payment_type`          ENUM('cod','prepaid','credit') NOT NULL
                          COMMENT 'cod = receiver pays product price; prepaid = sender paid charges at counter; credit = B2B invoiced',
  `cod_amount`            DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount to collect from receiver',
  `collected_amount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Actually collected (partial delivery aware)',
  `delivery_charge`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `weight_charge`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Additional-weight part (included in delivery_charge)',
  `cod_charge`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `fragile_charge`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `packaging_charge`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `return_charge`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `vat_amount`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_charge`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'delivery+cod+fragile+packaging+return-discount+vat',
  `charge_payer`          ENUM('sender','receiver','merchant') NOT NULL DEFAULT 'merchant',
  `charge_payment_status` ENUM('unpaid','paid','invoiced','deducted') NOT NULL DEFAULT 'unpaid',

  -- Lifecycle
  `status`                VARCHAR(30) NOT NULL DEFAULT 'booked',
  `delivery_attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts`          TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `expected_delivery_date` DATE NULL,
  `hold_until`            DATE NULL,
  `picked_up_at`          DATETIME NULL,
  `delivered_at`          DATETIME NULL,
  `returned_at`           DATETIME NULL,
  `cancelled_at`          DATETIME NULL,
  `cancel_reason`         VARCHAR(255) NULL,
  `is_rto`                TINYINT(1) NOT NULL DEFAULT 0,
  `special_instructions`  VARCHAR(255) NULL,
  `label_printed_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  -- Finance links
  `settlement_status`     ENUM('not_applicable','unsettled','in_settlement','settled') NOT NULL DEFAULT 'unsettled',
  `settlement_id`         INT UNSIGNED NULL,
  `invoice_id`            INT UNSIGNED NULL,
  `booking_journal_id`    BIGINT UNSIGNED NULL,
  `delivery_journal_id`   BIGINT UNSIGNED NULL,

  `created_by`            INT UNSIGNED NULL,
  `updated_by`            INT UNSIGNED NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`            TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parcels_tracking` (`tracking_no`),
  UNIQUE KEY `uq_parcels_merchant_ref` (`merchant_id`, `merchant_order_ref`),
  KEY `idx_parcels_status` (`status`),
  KEY `idx_parcels_origin` (`origin_branch_id`, `status`, `created_at`),
  KEY `idx_parcels_destination` (`destination_branch_id`, `status`),
  KEY `idx_parcels_current` (`current_branch_id`, `status`),
  KEY `idx_parcels_merchant` (`merchant_id`, `created_at`),
  KEY `idx_parcels_settlement` (`merchant_id`, `settlement_status`, `status`),
  KEY `idx_parcels_receiver_phone` (`receiver_phone`),
  KEY `idx_parcels_sender_phone` (`sender_phone`),
  KEY `idx_parcels_created` (`created_at`),
  KEY `idx_parcels_partner` (`partner_id`, `partner_tracking_no`),
  KEY `idx_parcels_parent` (`parent_parcel_id`),
  CONSTRAINT `fk_parcels_merchant`     FOREIGN KEY (`merchant_id`)           REFERENCES `merchants` (`id`)        ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_customer`     FOREIGN KEY (`customer_id`)           REFERENCES `customers` (`id`)        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_bulk`         FOREIGN KEY (`bulk_upload_id`)        REFERENCES `bulk_uploads` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_pickup`       FOREIGN KEY (`pickup_request_id`)     REFERENCES `pickup_requests` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_parent`       FOREIGN KEY (`parent_parcel_id`)      REFERENCES `parcels` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_sender_thana` FOREIGN KEY (`sender_thana_id`)       REFERENCES `thanas` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_recv_thana`   FOREIGN KEY (`receiver_thana_id`)     REFERENCES `thanas` (`id`)           ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_origin`       FOREIGN KEY (`origin_branch_id`)      REFERENCES `branches` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_destination`  FOREIGN KEY (`destination_branch_id`) REFERENCES `branches` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_current`      FOREIGN KEY (`current_branch_id`)     REFERENCES `branches` (`id`)         ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_origin_zone`  FOREIGN KEY (`origin_zone_id`)        REFERENCES `zones` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_dest_zone`    FOREIGN KEY (`destination_zone_id`)   REFERENCES `zones` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_partner`      FOREIGN KEY (`partner_id`)            REFERENCES `courier_partners` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_service`      FOREIGN KEY (`service_type_id`)       REFERENCES `service_types` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_rate_card`    FOREIGN KEY (`rate_card_id`)          REFERENCES `rate_cards` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_rate_item`    FOREIGN KEY (`rate_card_item_id`)     REFERENCES `rate_card_items` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_packaging`    FOREIGN KEY (`packaging_type_id`)     REFERENCES `packaging_types` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_status`       FOREIGN KEY (`status`)                REFERENCES `parcel_statuses` (`code`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_parcels_settlement`   FOREIGN KEY (`settlement_id`)         REFERENCES `merchant_settlements` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_invoice`      FOREIGN KEY (`invoice_id`)            REFERENCES `invoices` (`id`)         ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_booking_je`   FOREIGN KEY (`booking_journal_id`)    REFERENCES `journal_entries` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_delivery_je`  FOREIGN KEY (`delivery_journal_id`)   REFERENCES `journal_entries` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_creator`      FOREIGN KEY (`created_by`)            REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_parcels_updater`      FOREIGN KEY (`updated_by`)            REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_parcels_party`       CHECK ((`booking_type` = 'b2b' AND `merchant_id` IS NOT NULL) OR (`booking_type` = 'b2c')),
  CONSTRAINT `chk_parcels_money`       CHECK (`cod_amount` >= 0 AND `collected_amount` >= 0 AND `delivery_charge` >= 0
                                             AND `cod_charge` >= 0 AND `total_charge` >= 0 AND `declared_value` >= 0),
  CONSTRAINT `chk_parcels_cod`         CHECK (`payment_type` = 'cod' OR `cod_amount` = 0),
  CONSTRAINT `chk_parcels_weight`      CHECK (`actual_weight_kg` > 0 AND `chargeable_weight_kg` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shipments (AWB) — the core operational record';

DROP TABLE IF EXISTS `parcel_items`;
CREATE TABLE `parcel_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcel_id`       BIGINT UNSIGNED NOT NULL,
  `sku`             VARCHAR(60)  NULL,
  `description`     VARCHAR(200) NOT NULL,
  `quantity`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `delivered_qty`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'For partial delivery',
  `returned_qty`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_parcel_items_parcel` (`parcel_id`),
  CONSTRAINT `fk_parcel_items_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `chk_parcel_items_qty`   CHECK (`delivered_qty` + `returned_qty` <= `quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Optional line items (enables partial delivery by item)';

DROP TABLE IF EXISTS `parcel_tracking_events`;
CREATE TABLE `parcel_tracking_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcel_id`     BIGINT UNSIGNED NOT NULL,
  `status`        VARCHAR(30) NOT NULL,
  `branch_id`     SMALLINT UNSIGNED NULL,
  `manifest_id`   INT UNSIGNED NULL,
  `drs_id`        INT UNSIGNED NULL,
  `remarks_en`    VARCHAR(255) NULL,
  `remarks_bn`    VARCHAR(255) NULL,
  `is_public`     TINYINT(1) NOT NULL DEFAULT 1,
  `latitude`      DECIMAL(10,7) NULL,
  `longitude`     DECIMAL(10,7) NULL,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pte_parcel` (`parcel_id`, `created_at`),
  KEY `idx_pte_branch` (`branch_id`, `created_at`),
  CONSTRAINT `fk_pte_parcel`   FOREIGN KEY (`parcel_id`)   REFERENCES `parcels` (`id`)              ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pte_status`   FOREIGN KEY (`status`)      REFERENCES `parcel_statuses` (`code`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pte_branch`   FOREIGN KEY (`branch_id`)   REFERENCES `branches` (`id`)             ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pte_manifest` FOREIGN KEY (`manifest_id`) REFERENCES `manifests` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pte_drs`      FOREIGN KEY (`drs_id`)      REFERENCES `delivery_run_sheets` (`id`)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pte_user`     FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)                ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Real-time tracking timeline';

DROP TABLE IF EXISTS `scan_logs`;
CREATE TABLE `scan_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode`     VARCHAR(40) NOT NULL COMMENT 'Raw scanned value (tracking no or bag no)',
  `parcel_id`   BIGINT UNSIGNED NULL,
  `bag_id`      INT UNSIGNED NULL,
  `manifest_id` INT UNSIGNED NULL,
  `drs_id`      INT UNSIGNED NULL,
  `scan_type`   ENUM('booking','pickup','bag_in','bag_out','manifest_out','manifest_in','drs_out','drs_return','rto_in','audit') NOT NULL,
  `result`      ENUM('ok','not_found','wrong_branch','duplicate','not_in_manifest','status_blocked','excess') NOT NULL DEFAULT 'ok',
  `branch_id`   SMALLINT UNSIGNED NOT NULL,
  `device_info` VARCHAR(120) NULL,
  `scanned_by`  INT UNSIGNED NOT NULL,
  `scanned_at`  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_scan_logs_parcel` (`parcel_id`, `scanned_at`),
  KEY `idx_scan_logs_branch` (`branch_id`, `scanned_at`),
  KEY `idx_scan_logs_manifest` (`manifest_id`),
  KEY `idx_scan_logs_barcode` (`barcode`),
  CONSTRAINT `fk_scan_parcel`   FOREIGN KEY (`parcel_id`)   REFERENCES `parcels` (`id`)             ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_scan_bag`      FOREIGN KEY (`bag_id`)      REFERENCES `bags` (`id`)                ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_scan_manifest` FOREIGN KEY (`manifest_id`) REFERENCES `manifests` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_scan_drs`      FOREIGN KEY (`drs_id`)      REFERENCES `delivery_run_sheets` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_scan_branch`   FOREIGN KEY (`branch_id`)   REFERENCES `branches` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_scan_user`     FOREIGN KEY (`scanned_by`)  REFERENCES `users` (`id`)               ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Every barcode scan at every transit point (incl. failed scans)';

-- ---------------------------------------------------------------------
-- 08  LINE-HAUL: MANIFESTS & BAGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `manifests`;
CREATE TABLE `manifests` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `manifest_no`           VARCHAR(25) NOT NULL COMMENT 'MF-YYYYMMDD-XXXX',
  `manifest_type`         ENUM('branch_to_hub','hub_to_hub','hub_to_branch','branch_to_branch','return') NOT NULL,
  `origin_branch_id`      SMALLINT UNSIGNED NOT NULL,
  `destination_branch_id` SMALLINT UNSIGNED NOT NULL,
  `vehicle_id`            INT UNSIGNED NULL,
  `vehicle_no`            VARCHAR(30)  NULL COMMENT 'Free text for hired vehicles',
  `driver_name`           VARCHAR(100) NULL,
  `driver_phone`          VARCHAR(20)  NULL,
  `seal_no`               VARCHAR(40)  NULL,
  `total_bags`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total_parcels`         INT UNSIGNED NOT NULL DEFAULT 0,
  `total_weight_kg`       DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `total_cod_amount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `received_parcels`      INT UNSIGNED NOT NULL DEFAULT 0,
  `missing_parcels`       INT UNSIGNED NOT NULL DEFAULT 0,
  `line_haul_cost`        DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Posted to Fuel/Line-haul expense',
  `status`                ENUM('draft','sealed','dispatched','in_transit','received','partially_received','cancelled') NOT NULL DEFAULT 'draft',
  `expected_arrival_at`   DATETIME NULL,
  `dispatched_by`         INT UNSIGNED NULL,
  `dispatched_at`         DATETIME NULL,
  `received_by`           INT UNSIGNED NULL,
  `received_at`           DATETIME NULL,
  `remarks`               VARCHAR(255) NULL,
  `created_by`            INT UNSIGNED NOT NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_manifests_no` (`manifest_no`),
  KEY `idx_manifests_origin` (`origin_branch_id`, `status`, `created_at`),
  KEY `idx_manifests_destination` (`destination_branch_id`, `status`),
  CONSTRAINT `fk_manifests_origin`     FOREIGN KEY (`origin_branch_id`)      REFERENCES `branches` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_manifests_dest`       FOREIGN KEY (`destination_branch_id`) REFERENCES `branches` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_manifests_vehicle`    FOREIGN KEY (`vehicle_id`)            REFERENCES `vehicles` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_manifests_dispatcher` FOREIGN KEY (`dispatched_by`)         REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_manifests_receiver`   FOREIGN KEY (`received_by`)           REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_manifests_creator`    FOREIGN KEY (`created_by`)            REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_manifests_route`     CHECK (`origin_branch_id` <> `destination_branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line-haul dispatch manifests';

DROP TABLE IF EXISTS `bags`;
CREATE TABLE `bags` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bag_no`                VARCHAR(25) NOT NULL COMMENT 'BG-YYYYMMDD-XXXX (barcode on bag tag)',
  `manifest_id`           INT UNSIGNED NULL,
  `origin_branch_id`      SMALLINT UNSIGNED NOT NULL,
  `destination_branch_id` SMALLINT UNSIGNED NOT NULL,
  `seal_no`               VARCHAR(40) NULL,
  `parcel_count`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `weight_kg`             DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `status`                ENUM('open','sealed','dispatched','received','opened','cancelled') NOT NULL DEFAULT 'open',
  `sealed_by`             INT UNSIGNED NULL,
  `sealed_at`             DATETIME NULL,
  `opened_by`             INT UNSIGNED NULL,
  `opened_at`             DATETIME NULL,
  `created_by`            INT UNSIGNED NOT NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bags_no` (`bag_no`),
  KEY `idx_bags_manifest` (`manifest_id`),
  KEY `idx_bags_origin` (`origin_branch_id`, `status`),
  CONSTRAINT `fk_bags_manifest` FOREIGN KEY (`manifest_id`)           REFERENCES `manifests` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_bags_origin`   FOREIGN KEY (`origin_branch_id`)      REFERENCES `branches` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bags_dest`     FOREIGN KEY (`destination_branch_id`) REFERENCES `branches` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bags_sealer`   FOREIGN KEY (`sealed_by`)             REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_bags_opener`   FOREIGN KEY (`opened_by`)             REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_bags_creator`  FOREIGN KEY (`created_by`)            REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sealed sacks grouping parcels inside a manifest';

DROP TABLE IF EXISTS `manifest_items`;
CREATE TABLE `manifest_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `manifest_id`     INT UNSIGNED NOT NULL,
  `parcel_id`       BIGINT UNSIGNED NOT NULL,
  `bag_id`          INT UNSIGNED NULL,
  `scan_out_by`     INT UNSIGNED NULL,
  `scan_out_at`     DATETIME NULL,
  `scan_in_by`      INT UNSIGNED NULL,
  `scan_in_at`      DATETIME NULL,
  `receive_status`  ENUM('pending','received','missing','damaged','short_landed','excess') NOT NULL DEFAULT 'pending',
  `remarks`         VARCHAR(255) NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_manifest_parcel` (`manifest_id`, `parcel_id`),
  KEY `idx_manifest_items_parcel` (`parcel_id`),
  KEY `idx_manifest_items_bag` (`bag_id`),
  CONSTRAINT `fk_mi_manifest` FOREIGN KEY (`manifest_id`) REFERENCES `manifests` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mi_parcel`   FOREIGN KEY (`parcel_id`)   REFERENCES `parcels` (`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_mi_bag`      FOREIGN KEY (`bag_id`)      REFERENCES `bags` (`id`)      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_mi_out_by`   FOREIGN KEY (`scan_out_by`) REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_mi_in_by`    FOREIGN KEY (`scan_in_by`)  REFERENCES `users` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parcels loaded on a manifest with scan-out / scan-in verification';

-- ---------------------------------------------------------------------
-- 09  LAST-MILE DELIVERY
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `riders`;
CREATE TABLE `riders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `branch_id`        SMALLINT UNSIGNED NOT NULL,
  `rider_code`       VARCHAR(20)  NOT NULL,
  `vehicle_type`     ENUM('walking','bicycle','motorbike','van') NOT NULL DEFAULT 'motorbike',
  `vehicle_no`       VARCHAR(30)  NULL,
  `driving_license_no` VARCHAR(40) NULL,
  `nid_no`           VARCHAR(30)  NULL,
  `emergency_contact` VARCHAR(20) NULL,
  `employment_type`  ENUM('permanent','contractual','freelance') NOT NULL DEFAULT 'contractual',
  `commission_type`  ENUM('per_delivery','percent_of_charge','fixed_salary','salary_plus_per_delivery') NOT NULL DEFAULT 'per_delivery',
  `commission_rate`  DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taka per delivery or % depending on commission_type',
  `cash_limit`       DECIMAL(14,2) NOT NULL DEFAULT 20000.00 COMMENT 'Max COD a rider may carry before mandatory handover',
  `max_daily_parcels` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `rating`           DECIMAL(3,2) NULL,
  `status`           ENUM('active','on_leave','suspended','resigned') NOT NULL DEFAULT 'active',
  `joined_at`        DATE NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_riders_user` (`user_id`),
  UNIQUE KEY `uq_riders_code` (`rider_code`),
  KEY `idx_riders_branch` (`branch_id`, `status`),
  CONSTRAINT `fk_riders_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_riders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Internal delivery fleet (rider profile for users with role rider)';

DROP TABLE IF EXISTS `courier_partners`;
CREATE TABLE `courier_partners` (
  `id`                    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                  VARCHAR(20)  NOT NULL,
  `name`                  VARCHAR(120) NOT NULL,
  `contact_person`        VARCHAR(120) NULL,
  `phone`                 VARCHAR(20)  NULL,
  `email`                 VARCHAR(150) NULL,
  `address`               VARCHAR(255) NULL,
  `api_base_url`          VARCHAR(255) NULL,
  `api_key_encrypted`     TEXT NULL COMMENT 'Crypto::encrypt() — never stored in plain text',
  `api_secret_encrypted`  TEXT NULL,
  `tracking_url_template` VARCHAR(255) NULL COMMENT 'e.g. https://partner.example/track/{tracking_no}',
  `charge_type`           ENUM('flat','percent','rate_card') NOT NULL DEFAULT 'flat',
  `charge_value`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cod_remit_days`        TINYINT UNSIGNED NOT NULL DEFAULT 7,
  `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courier_partners_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Third-party logistics (3PL) partners';

DROP TABLE IF EXISTS `delivery_failure_reasons`;
CREATE TABLE `delivery_failure_reasons` (
  `id`           TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(30) NOT NULL,
  `name_en`      VARCHAR(120) NOT NULL,
  `name_bn`      VARCHAR(120) NOT NULL,
  `outcome`      ENUM('reschedule','hold','return') NOT NULL DEFAULT 'reschedule',
  `counts_attempt` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dfr_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_run_sheets`;
CREATE TABLE `delivery_run_sheets` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `drs_no`              VARCHAR(25) NOT NULL COMMENT 'DRS-YYYYMMDD-XXXX',
  `branch_id`           SMALLINT UNSIGNED NOT NULL,
  `assignment_type`     ENUM('rider','partner') NOT NULL DEFAULT 'rider',
  `rider_id`            INT UNSIGNED NULL,
  `partner_id`          SMALLINT UNSIGNED NULL,
  `run_date`            DATE NOT NULL,
  `route_area`          VARCHAR(150) NULL,
  `total_parcels`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `delivered_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `partial_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total_cod_expected`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_cod_collected` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `cash_handed_over`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status`              ENUM('draft','assigned','out_for_delivery','returned_to_branch','closed','cancelled') NOT NULL DEFAULT 'draft',
  `dispatched_at`       DATETIME NULL,
  `closed_by`           INT UNSIGNED NULL,
  `closed_at`           DATETIME NULL,
  `remarks`             VARCHAR(255) NULL,
  `created_by`          INT UNSIGNED NOT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drs_no` (`drs_no`),
  KEY `idx_drs_branch_date` (`branch_id`, `run_date`, `status`),
  KEY `idx_drs_rider` (`rider_id`, `run_date`),
  KEY `idx_drs_partner` (`partner_id`),
  CONSTRAINT `fk_drs_branch`  FOREIGN KEY (`branch_id`)  REFERENCES `branches` (`id`)         ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_drs_rider`   FOREIGN KEY (`rider_id`)   REFERENCES `riders` (`id`)           ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_drs_partner` FOREIGN KEY (`partner_id`) REFERENCES `courier_partners` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_drs_closer`  FOREIGN KEY (`closed_by`)  REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_drs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_drs_assignee` CHECK ((`assignment_type` = 'rider' AND `rider_id` IS NOT NULL AND `partner_id` IS NULL)
                                    OR (`assignment_type` = 'partner' AND `partner_id` IS NOT NULL AND `rider_id` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delivery Run-Sheet (rider or 3PL hand-over)';

DROP TABLE IF EXISTS `drs_items`;
CREATE TABLE `drs_items` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `drs_id`              INT UNSIGNED NOT NULL,
  `parcel_id`           BIGINT UNSIGNED NOT NULL,
  `sequence_no`         SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Route order',
  `attempt_no`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `partner_tracking_no` VARCHAR(60) NULL,
  `cod_expected`        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `cod_collected`       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `payment_method`      ENUM('cash','bkash','nagad','rocket','card','none') NOT NULL DEFAULT 'cash',
  `payment_reference`   VARCHAR(60) NULL,
  `status`              ENUM('assigned','out_for_delivery','delivered','partially_delivered','rescheduled','on_hold','returned','cancelled') NOT NULL DEFAULT 'assigned',
  `failure_reason_id`   TINYINT UNSIGNED NULL,
  `failure_note`        VARCHAR(255) NULL,
  `rescheduled_to`      DATE NULL,
  `journal_entry_id`    BIGINT UNSIGNED NULL,
  `completed_at`        DATETIME NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drs_parcel_attempt` (`drs_id`, `parcel_id`),
  KEY `idx_drs_items_parcel` (`parcel_id`),
  KEY `idx_drs_items_status` (`drs_id`, `status`),
  CONSTRAINT `fk_drsi_drs`     FOREIGN KEY (`drs_id`)            REFERENCES `delivery_run_sheets` (`id`)      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_drsi_parcel`  FOREIGN KEY (`parcel_id`)         REFERENCES `parcels` (`id`)                  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_drsi_reason`  FOREIGN KEY (`failure_reason_id`) REFERENCES `delivery_failure_reasons` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_drsi_journal` FOREIGN KEY (`journal_entry_id`)  REFERENCES `journal_entries` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_drsi_cod`    CHECK (`cod_collected` >= 0 AND `cod_expected` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parcels on a run-sheet with per-attempt outcome';

DROP TABLE IF EXISTS `delivery_otps`;
CREATE TABLE `delivery_otps` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcel_id`    BIGINT UNSIGNED NOT NULL,
  `otp_hash`     VARCHAR(255) NOT NULL COMMENT 'password_hash() of the 6-digit OTP',
  `sent_to`      VARCHAR(20)  NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `expires_at`   DATETIME NOT NULL,
  `verified_at`  DATETIME NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_delivery_otps_parcel` (`parcel_id`, `created_at`),
  CONSTRAINT `fk_delivery_otps_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Receiver OTP for proof of delivery';

DROP TABLE IF EXISTS `proof_of_delivery`;
CREATE TABLE `proof_of_delivery` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parcel_id`         BIGINT UNSIGNED NOT NULL,
  `drs_item_id`       BIGINT UNSIGNED NULL,
  `rider_id`          INT UNSIGNED NULL,
  `receiver_name`     VARCHAR(120) NOT NULL,
  `receiver_relation` ENUM('self','family','colleague','security_guard','reception','other') NOT NULL DEFAULT 'self',
  `verification_method` ENUM('otp','signature','photo','otp_signature','partner_confirmed','manual') NOT NULL,
  `otp_id`            BIGINT UNSIGNED NULL,
  `signature_path`    VARCHAR(255) NULL COMMENT 'storage/uploads/signatures/…',
  `photo_path`        VARCHAR(255) NULL COMMENT 'storage/uploads/pod/…',
  `latitude`          DECIMAL(10,7) NULL,
  `longitude`         DECIMAL(10,7) NULL,
  `gps_accuracy_m`    DECIMAL(8,2) NULL,
  `device_info`       VARCHAR(255) NULL,
  `ip_address`        VARCHAR(45)  NULL,
  `delivered_at`      DATETIME NOT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pod_parcel` (`parcel_id`),
  KEY `idx_pod_rider` (`rider_id`, `delivered_at`),
  CONSTRAINT `fk_pod_parcel`   FOREIGN KEY (`parcel_id`)   REFERENCES `parcels` (`id`)       ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pod_drs_item` FOREIGN KEY (`drs_item_id`) REFERENCES `drs_items` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pod_rider`    FOREIGN KEY (`rider_id`)    REFERENCES `riders` (`id`)        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_pod_otp`      FOREIGN KEY (`otp_id`)      REFERENCES `delivery_otps` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proof of Delivery: OTP / signature / photo / GPS';

DROP TABLE IF EXISTS `rto_requests`;
CREATE TABLE `rto_requests` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rto_no`              VARCHAR(25) NOT NULL COMMENT 'RTO-YYYYMMDD-XXXX',
  `parcel_id`           BIGINT UNSIGNED NOT NULL,
  `return_parcel_id`    BIGINT UNSIGNED NULL COMMENT 'Child parcel created for partial-delivery returns',
  `reason_id`           TINYINT UNSIGNED NULL,
  `reason_note`         VARCHAR(255) NULL,
  `initiated_from`      ENUM('auto_max_attempts','rider','branch','merchant','customer_service') NOT NULL,
  `from_branch_id`      SMALLINT UNSIGNED NOT NULL COMMENT 'Where the parcel is when RTO starts',
  `return_branch_id`    SMALLINT UNSIGNED NOT NULL COMMENT 'Origin branch receiving the return',
  `return_charge`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`              ENUM('initiated','approved','in_transit','received_at_origin','returned_to_merchant','closed','cancelled') NOT NULL DEFAULT 'initiated',
  `approved_by`         INT UNSIGNED NULL,
  `approved_at`         DATETIME NULL,
  `received_at_origin_at` DATETIME NULL,
  `handed_over_to`      VARCHAR(120) NULL,
  `handed_over_at`      DATETIME NULL,
  `handover_signature_path` VARCHAR(255) NULL,
  `journal_entry_id`    BIGINT UNSIGNED NULL,
  `initiated_by`        INT UNSIGNED NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rto_no` (`rto_no`),
  KEY `idx_rto_parcel` (`parcel_id`),
  KEY `idx_rto_return_branch` (`return_branch_id`, `status`),
  CONSTRAINT `fk_rto_parcel`        FOREIGN KEY (`parcel_id`)        REFERENCES `parcels` (`id`)                  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rto_return_parcel` FOREIGN KEY (`return_parcel_id`) REFERENCES `parcels` (`id`)                  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rto_reason`        FOREIGN KEY (`reason_id`)        REFERENCES `delivery_failure_reasons` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rto_from_branch`   FOREIGN KEY (`from_branch_id`)   REFERENCES `branches` (`id`)                 ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rto_return_branch` FOREIGN KEY (`return_branch_id`) REFERENCES `branches` (`id`)                 ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rto_approver`      FOREIGN KEY (`approved_by`)      REFERENCES `users` (`id`)                    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rto_initiator`     FOREIGN KEY (`initiated_by`)     REFERENCES `users` (`id`)                    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rto_journal`       FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Return-to-Origin reverse logistics workflow';

DROP TABLE IF EXISTS `rider_cash_handovers`;
CREATE TABLE `rider_cash_handovers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `handover_no`      VARCHAR(25) NOT NULL COMMENT 'CH-YYYYMMDD-XXXX',
  `branch_id`        SMALLINT UNSIGNED NOT NULL,
  `rider_id`         INT UNSIGNED NOT NULL,
  `drs_id`           INT UNSIGNED NULL,
  `expected_amount`  DECIMAL(14,2) NOT NULL,
  `cash_amount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `digital_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'bKash/Nagad collected directly to company wallet',
  `short_amount`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `excess_amount`    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `denominations`    JSON NULL COMMENT '{"1000":5,"500":3,…}',
  `status`           ENUM('pending','verified','disputed','reversed') NOT NULL DEFAULT 'pending',
  `received_by`      INT UNSIGNED NULL COMMENT 'Branch cashier',
  `received_at`      DATETIME NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `remarks`          VARCHAR(255) NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rch_no` (`handover_no`),
  KEY `idx_rch_branch` (`branch_id`, `created_at`),
  KEY `idx_rch_rider` (`rider_id`, `status`),
  CONSTRAINT `fk_rch_branch`   FOREIGN KEY (`branch_id`)        REFERENCES `branches` (`id`)            ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rch_rider`    FOREIGN KEY (`rider_id`)         REFERENCES `riders` (`id`)              ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_rch_drs`      FOREIGN KEY (`drs_id`)           REFERENCES `delivery_run_sheets` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rch_receiver` FOREIGN KEY (`received_by`)      REFERENCES `users` (`id`)               ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_rch_journal`  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)     ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_rch_amounts` CHECK (`expected_amount` >= 0 AND `cash_amount` >= 0 AND `digital_amount` >= 0
                                     AND `short_amount` >= 0 AND `excess_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily rider → branch cashier COD handover';

-- ---------------------------------------------------------------------
-- 10  DOUBLE-ENTRY ACCOUNTING
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `fiscal_years`;
CREATE TABLE `fiscal_years` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(20) NOT NULL COMMENT 'e.g. FY 2026-27',
  `start_date`  DATE NOT NULL,
  `end_date`    DATE NOT NULL,
  `lock_date`   DATE NULL COMMENT 'No postings on or before this date (period close)',
  `is_closed`   TINYINT(1) NOT NULL DEFAULT 0,
  `closed_by`   INT UNSIGNED NULL,
  `closed_at`   DATETIME NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiscal_years_name` (`name`),
  UNIQUE KEY `uq_fiscal_years_start` (`start_date`),
  CONSTRAINT `fk_fy_closer` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_fy_dates` CHECK (`end_date` > `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bangladesh fiscal year July–June by default';

DROP TABLE IF EXISTS `chart_of_accounts`;
CREATE TABLE `chart_of_accounts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(20)  NOT NULL,
  `name_en`         VARCHAR(150) NOT NULL,
  `name_bn`         VARCHAR(150) NOT NULL,
  `parent_id`       INT UNSIGNED NULL,
  `account_class`   ENUM('asset','liability','equity','income','expense') NOT NULL,
  `normal_balance`  ENUM('debit','credit') NOT NULL,
  `report_group`    ENUM('current_asset','fixed_asset','current_liability','long_term_liability','equity',
                         'operating_income','other_income','direct_expense','admin_expense','financial_expense') NOT NULL,
  `system_key`      VARCHAR(40)  NULL COMMENT 'Stable key used by the posting engine, e.g. MERCHANT_COD_PAYABLE',
  `is_group`        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Group/header accounts cannot receive postings',
  `is_system`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Cannot be deleted / re-classed',
  `is_cash`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Appears in Cash Book',
  `is_bank`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Appears in Bank Book',
  `requires_party`  ENUM('none','merchant','rider','partner','customer','employee') NOT NULL DEFAULT 'none'
                    COMMENT 'Sub-ledger enforced on journal lines',
  `description`     VARCHAR(255) NULL,
  `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`      INT UNSIGNED NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coa_code` (`code`),
  UNIQUE KEY `uq_coa_system_key` (`system_key`),
  KEY `idx_coa_parent` (`parent_id`),
  KEY `idx_coa_class` (`account_class`, `is_active`),
  CONSTRAINT `fk_coa_parent`  FOREIGN KEY (`parent_id`)  REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_coa_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)             ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chart of Accounts (hierarchical)';

DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id`              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id`      INT UNSIGNED NOT NULL COMMENT 'Linked COA ledger (is_bank = 1)',
  `branch_id`       SMALLINT UNSIGNED NULL COMMENT 'Owning branch; NULL = Head Office',
  `bank_name`       VARCHAR(120) NOT NULL,
  `bank_branch`     VARCHAR(120) NULL,
  `account_title`   VARCHAR(150) NOT NULL,
  `account_number`  VARCHAR(40)  NOT NULL,
  `routing_number`  VARCHAR(20)  NULL,
  `account_type`    ENUM('current','savings','snd','mfs_wallet') NOT NULL DEFAULT 'current',
  `opening_balance` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_accounts_number` (`bank_name`, `account_number`),
  UNIQUE KEY `uq_bank_accounts_coa` (`account_id`),
  CONSTRAINT `fk_bank_accounts_coa`    FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bank_accounts_branch` FOREIGN KEY (`branch_id`)  REFERENCES `branches` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE `journal_entries` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entry_no`          VARCHAR(30) NOT NULL COMMENT 'JV-YYYYMM-XXXXX',
  `entry_date`        DATE NOT NULL,
  `fiscal_year_id`    SMALLINT UNSIGNED NOT NULL,
  `branch_id`         SMALLINT UNSIGNED NOT NULL COMMENT 'Originating branch of the transaction',
  `voucher_type`      ENUM('JV','PV','RV','CV','SV') NOT NULL DEFAULT 'JV'
                      COMMENT 'JV journal, PV payment, RV receipt, CV contra, SV system (auto-posted)',
  `source_type`       VARCHAR(40) NOT NULL DEFAULT 'manual'
                      COMMENT 'manual, parcel_booking, parcel_delivery, parcel_rto, rider_handover, b2b_invoice, invoice_payment, cod_settlement, voucher, partner_remittance, opening_balance, reversal',
  `source_id`         BIGINT UNSIGNED NULL,
  `idempotency_key`   VARCHAR(80) NULL COMMENT 'Prevents double posting of the same business event',
  `narration`         VARCHAR(500) NOT NULL,
  `currency`          CHAR(3) NOT NULL DEFAULT 'BDT',
  `total_debit`       DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_credit`      DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `status`            ENUM('draft','posted','reversed') NOT NULL DEFAULT 'draft',
  `reversal_of_id`    BIGINT UNSIGNED NULL COMMENT 'Set on the reversing entry',
  `reversed_by_id`    BIGINT UNSIGNED NULL COMMENT 'Set on the original entry when reversed',
  `posted_by`         INT UNSIGNED NULL,
  `posted_at`         DATETIME NULL,
  `created_by`        INT UNSIGNED NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_je_no` (`entry_no`),
  UNIQUE KEY `uq_je_idempotency` (`idempotency_key`),
  KEY `idx_je_date` (`entry_date`, `status`),
  KEY `idx_je_branch_date` (`branch_id`, `entry_date`),
  KEY `idx_je_source` (`source_type`, `source_id`),
  KEY `idx_je_fy` (`fiscal_year_id`),
  CONSTRAINT `fk_je_fy`        FOREIGN KEY (`fiscal_year_id`) REFERENCES `fiscal_years` (`id`)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_je_branch`    FOREIGN KEY (`branch_id`)      REFERENCES `branches` (`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_je_reversal`  FOREIGN KEY (`reversal_of_id`) REFERENCES `journal_entries` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_je_reversed`  FOREIGN KEY (`reversed_by_id`) REFERENCES `journal_entries` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_je_poster`    FOREIGN KEY (`posted_by`)      REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_je_creator`   FOREIGN KEY (`created_by`)     REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='General Journal headers (immutable once posted)';

DROP TABLE IF EXISTS `journal_lines`;
CREATE TABLE `journal_lines` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `line_no`          SMALLINT UNSIGNED NOT NULL,
  `account_id`       INT UNSIGNED NOT NULL,
  `branch_id`        SMALLINT UNSIGNED NOT NULL COMMENT 'Profit centre — drives branch-wise P&L',
  `debit`            DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `credit`           DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `party_type`       ENUM('none','merchant','rider','partner','customer','employee') NOT NULL DEFAULT 'none',
  `party_id`         INT UNSIGNED NULL,
  `parcel_id`        BIGINT UNSIGNED NULL,
  `description`      VARCHAR(255) NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jl_line` (`journal_entry_id`, `line_no`),
  KEY `idx_jl_account_branch` (`account_id`, `branch_id`),
  KEY `idx_jl_party` (`party_type`, `party_id`, `account_id`),
  KEY `idx_jl_parcel` (`parcel_id`),
  KEY `idx_jl_branch` (`branch_id`),
  CONSTRAINT `fk_jl_entry`   FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_jl_account` FOREIGN KEY (`account_id`)       REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_jl_branch`  FOREIGN KEY (`branch_id`)        REFERENCES `branches` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_jl_parcel`  FOREIGN KEY (`parcel_id`)        REFERENCES `parcels` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_jl_amounts` CHECK (`debit` >= 0 AND `credit` >= 0 AND ((`debit` > 0 AND `credit` = 0) OR (`credit` > 0 AND `debit` = 0))),
  CONSTRAINT `chk_jl_party`   CHECK ((`party_type` = 'none' AND `party_id` IS NULL) OR (`party_type` <> 'none' AND `party_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal lines — one side per line, sub-ledger party aware';

DROP TABLE IF EXISTS `vouchers`;
CREATE TABLE `vouchers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_no`       VARCHAR(30) NOT NULL COMMENT 'PV/RV/CV/JV-YYYYMM-XXXXX',
  `voucher_type`     ENUM('payment','receipt','contra','journal') NOT NULL,
  `voucher_date`     DATE NOT NULL,
  `branch_id`        SMALLINT UNSIGNED NOT NULL,
  `payee_type`       ENUM('none','merchant','rider','partner','customer','employee','vendor') NOT NULL DEFAULT 'none',
  `payee_id`         INT UNSIGNED NULL,
  `payee_name`       VARCHAR(150) NULL,
  `amount`           DECIMAL(16,2) NOT NULL,
  `payment_mode`     ENUM('cash','bank_transfer','cheque','mfs','adjustment') NOT NULL DEFAULT 'cash',
  `cheque_no`        VARCHAR(30) NULL,
  `cheque_date`      DATE NULL,
  `reference_no`     VARCHAR(60) NULL,
  `narration`        VARCHAR(500) NOT NULL,
  `attachment_path`  VARCHAR(255) NULL,
  `status`           ENUM('draft','pending_approval','approved','posted','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `rejection_reason` VARCHAR(255) NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `prepared_by`      INT UNSIGNED NOT NULL,
  `approved_by`      INT UNSIGNED NULL,
  `approved_at`      DATETIME NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vouchers_no` (`voucher_no`),
  KEY `idx_vouchers_branch_date` (`branch_id`, `voucher_date`),
  KEY `idx_vouchers_status` (`status`),
  CONSTRAINT `fk_vouchers_branch`   FOREIGN KEY (`branch_id`)        REFERENCES `branches` (`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_vouchers_journal`  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_vouchers_preparer` FOREIGN KEY (`prepared_by`)      REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_vouchers_approver` FOREIGN KEY (`approved_by`)      REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_vouchers_amount`  CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment / Receipt / Contra / Journal vouchers with maker-checker approval';

DROP TABLE IF EXISTS `voucher_lines`;
CREATE TABLE `voucher_lines` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_id`  INT UNSIGNED NOT NULL,
  `account_id`  INT UNSIGNED NOT NULL,
  `branch_id`   SMALLINT UNSIGNED NOT NULL,
  `debit`       DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `credit`      DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `party_type`  ENUM('none','merchant','rider','partner','customer','employee') NOT NULL DEFAULT 'none',
  `party_id`    INT UNSIGNED NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_voucher_lines_voucher` (`voucher_id`),
  KEY `idx_voucher_lines_account` (`account_id`),
  CONSTRAINT `fk_vl_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`)          ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vl_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_vl_branch`  FOREIGN KEY (`branch_id`)  REFERENCES `branches` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_vl_amounts` CHECK (`debit` >= 0 AND `credit` >= 0 AND ((`debit` > 0 AND `credit` = 0) OR (`credit` > 0 AND `debit` = 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no`       VARCHAR(30) NOT NULL COMMENT 'INV-YYYYMM-XXXXX',
  `merchant_id`      INT UNSIGNED NOT NULL,
  `branch_id`        SMALLINT UNSIGNED NOT NULL,
  `invoice_date`     DATE NOT NULL,
  `due_date`         DATE NOT NULL,
  `period_from`      DATE NOT NULL,
  `period_to`        DATE NOT NULL,
  `parcel_count`     INT UNSIGNED NOT NULL DEFAULT 0,
  `subtotal`         DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `discount`         DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `vat_amount`       DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_amount`     DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `paid_amount`      DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `status`           ENUM('draft','issued','partially_paid','paid','cancelled') NOT NULL DEFAULT 'draft',
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `notes`            VARCHAR(500) NULL,
  `created_by`       INT UNSIGNED NOT NULL,
  `issued_by`        INT UNSIGNED NULL,
  `issued_at`        DATETIME NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_no` (`invoice_no`),
  KEY `idx_invoices_merchant` (`merchant_id`, `status`, `due_date`),
  KEY `idx_invoices_branch_date` (`branch_id`, `invoice_date`),
  CONSTRAINT `fk_invoices_merchant` FOREIGN KEY (`merchant_id`)      REFERENCES `merchants` (`id`)       ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_branch`   FOREIGN KEY (`branch_id`)        REFERENCES `branches` (`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_journal`  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_creator`  FOREIGN KEY (`created_by`)       REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_issuer`   FOREIGN KEY (`issued_by`)        REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_invoices_amounts` CHECK (`paid_amount` >= 0 AND `paid_amount` <= `total_amount` AND `due_date` >= `invoice_date`
                                          AND `period_to` >= `period_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2B credit invoices (Dr AR-Merchant / Cr Revenue / Cr VAT)';

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`      INT UNSIGNED NOT NULL,
  `parcel_id`       BIGINT UNSIGNED NULL,
  `description`     VARCHAR(255) NOT NULL,
  `delivery_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cod_charge`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `return_charge`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `other_charge`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_total`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_items_parcel` (`invoice_id`, `parcel_id`),
  KEY `idx_invoice_items_parcel` (`parcel_id`),
  CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ii_parcel`  FOREIGN KEY (`parcel_id`)  REFERENCES `parcels` (`id`)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_payments`;
CREATE TABLE `invoice_payments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`       INT UNSIGNED NOT NULL,
  `payment_date`     DATE NOT NULL,
  `amount`           DECIMAL(16,2) NOT NULL,
  `payment_mode`     ENUM('cash','bank_transfer','cheque','mfs','cod_adjustment') NOT NULL,
  `deposit_account_id` INT UNSIGNED NOT NULL COMMENT 'Cash/Bank COA ledger debited',
  `reference_no`     VARCHAR(60) NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `received_by`      INT UNSIGNED NOT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_payments_invoice` (`invoice_id`),
  CONSTRAINT `fk_ip_invoice`  FOREIGN KEY (`invoice_id`)         REFERENCES `invoices` (`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ip_account`  FOREIGN KEY (`deposit_account_id`) REFERENCES `chart_of_accounts` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ip_journal`  FOREIGN KEY (`journal_entry_id`)   REFERENCES `journal_entries` (`id`)   ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ip_receiver` FOREIGN KEY (`received_by`)        REFERENCES `users` (`id`)             ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_ip_amount`  CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `money_receipts`;
CREATE TABLE `money_receipts` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_no`       VARCHAR(30) NOT NULL COMMENT 'MR-YYYYMMDD-XXXX',
  `branch_id`        SMALLINT UNSIGNED NOT NULL,
  `parcel_id`        BIGINT UNSIGNED NULL,
  `customer_id`      INT UNSIGNED NULL,
  `payer_name`       VARCHAR(120) NOT NULL,
  `payer_phone`      VARCHAR(20)  NULL,
  `amount`           DECIMAL(14,2) NOT NULL,
  `payment_mode`     ENUM('cash','mfs','card','bank_transfer') NOT NULL DEFAULT 'cash',
  `reference_no`     VARCHAR(60) NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `received_by`      INT UNSIGNED NOT NULL,
  `is_cancelled`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_money_receipts_no` (`receipt_no`),
  KEY `idx_mr_branch_date` (`branch_id`, `created_at`),
  KEY `idx_mr_parcel` (`parcel_id`),
  CONSTRAINT `fk_mr_branch`   FOREIGN KEY (`branch_id`)        REFERENCES `branches` (`id`)        ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_mr_parcel`   FOREIGN KEY (`parcel_id`)        REFERENCES `parcels` (`id`)         ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_mr_customer` FOREIGN KEY (`customer_id`)      REFERENCES `customers` (`id`)       ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_mr_journal`  FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_mr_receiver` FOREIGN KEY (`received_by`)      REFERENCES `users` (`id`)           ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_mr_amount`  CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='B2C counter money receipts';

DROP TABLE IF EXISTS `merchant_settlements`;
CREATE TABLE `merchant_settlements` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `settlement_no`        VARCHAR(30) NOT NULL COMMENT 'STL-YYYYMM-XXXXX',
  `merchant_id`          INT UNSIGNED NOT NULL,
  `branch_id`            SMALLINT UNSIGNED NOT NULL COMMENT 'Paying branch (usually Head Office)',
  `period_from`          DATE NOT NULL,
  `period_to`            DATE NOT NULL,
  `parcel_count`         INT UNSIGNED NOT NULL DEFAULT 0,
  `total_cod_collected`  DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_delivery_charge` DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_cod_charge`     DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_return_charge`  DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_other_charge`   DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `total_vat`            DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `adjustment_amount`    DECIMAL(16,2) NOT NULL DEFAULT 0.00 COMMENT '+ credit to merchant / − debit (claims, penalties)',
  `adjustment_note`      VARCHAR(255) NULL,
  `net_payable`          DECIMAL(16,2) NOT NULL DEFAULT 0.00,
  `payout_account_id`    INT UNSIGNED NULL COMMENT 'merchant_payout_accounts.id',
  `paid_from_account_id` INT UNSIGNED NULL COMMENT 'COA cash/bank ledger credited on payment',
  `transaction_ref`      VARCHAR(80) NULL,
  `status`               ENUM('draft','pending_approval','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `prepared_by`          INT UNSIGNED NOT NULL,
  `approved_by`          INT UNSIGNED NULL,
  `approved_at`          DATETIME NULL,
  `paid_by`              INT UNSIGNED NULL,
  `paid_at`              DATETIME NULL,
  `rejection_reason`     VARCHAR(255) NULL,
  `journal_entry_id`     BIGINT UNSIGNED NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlements_no` (`settlement_no`),
  KEY `idx_settlements_merchant` (`merchant_id`, `status`),
  KEY `idx_settlements_period` (`period_from`, `period_to`),
  CONSTRAINT `fk_stl_merchant` FOREIGN KEY (`merchant_id`)          REFERENCES `merchants` (`id`)                ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stl_branch`   FOREIGN KEY (`branch_id`)            REFERENCES `branches` (`id`)                 ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stl_payout`   FOREIGN KEY (`payout_account_id`)    REFERENCES `merchant_payout_accounts` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_stl_paid_from` FOREIGN KEY (`paid_from_account_id`) REFERENCES `chart_of_accounts` (`id`)       ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stl_preparer` FOREIGN KEY (`prepared_by`)          REFERENCES `users` (`id`)                    ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_stl_approver` FOREIGN KEY (`approved_by`)          REFERENCES `users` (`id`)                    ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_stl_payer`    FOREIGN KEY (`paid_by`)              REFERENCES `users` (`id`)                    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_stl_journal`  FOREIGN KEY (`journal_entry_id`)     REFERENCES `journal_entries` (`id`)          ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `chk_stl_period`  CHECK (`period_to` >= `period_from`),
  CONSTRAINT `chk_stl_maker_checker` CHECK (`approved_by` IS NULL OR `approved_by` <> `prepared_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant COD payout statements (maker-checker enforced)';

DROP TABLE IF EXISTS `merchant_settlement_items`;
CREATE TABLE `merchant_settlement_items` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `settlement_id`   INT UNSIGNED NOT NULL,
  `parcel_id`       BIGINT UNSIGNED NOT NULL,
  `parcel_status`   VARCHAR(30) NOT NULL COMMENT 'Snapshot: delivered / partially_delivered / returned_to_merchant',
  `cod_collected`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cod_charge`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `return_charge`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `other_charge`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `net_amount`      DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'cod_collected − all charges (can be negative for RTO)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msi_parcel` (`settlement_id`, `parcel_id`),
  KEY `idx_msi_parcel` (`parcel_id`),
  CONSTRAINT `fk_msi_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `merchant_settlements` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_msi_parcel`     FOREIGN KEY (`parcel_id`)     REFERENCES `parcels` (`id`)              ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11  SYSTEM
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id`            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_group` VARCHAR(40)  NOT NULL DEFAULT 'general',
  `setting_key`   VARCHAR(80)  NOT NULL,
  `setting_value` TEXT NULL,
  `value_type`    ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
  `is_public`     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Safe to expose to the browser',
  `description`   VARCHAR(255) NULL,
  `updated_by`    INT UNSIGNED NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`setting_group`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NULL,
  `branch_id`    SMALLINT UNSIGNED NULL,
  `action`       VARCHAR(40)  NOT NULL COMMENT 'create, update, delete, status_change, login, logout, login_failed, post, reverse, approve, export …',
  `entity_type`  VARCHAR(60)  NOT NULL,
  `entity_id`    VARCHAR(40)  NULL,
  `old_value`    JSON NULL,
  `new_value`    JSON NULL,
  `ip_address`   VARCHAR(45)  NULL,
  `user_agent`   VARCHAR(255) NULL,
  `source`       ENUM('app','trigger') NOT NULL DEFAULT 'app',
  `created_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user` (`user_id`, `created_at`),
  KEY `idx_audit_branch` (`branch_id`, `created_at`),
  KEY `idx_audit_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only audit trail (no FKs so history survives deletes; UPDATE/DELETE blocked by trigger)';

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        VARCHAR(40)  NOT NULL,
  `title_en`    VARCHAR(150) NOT NULL,
  `title_bn`    VARCHAR(150) NOT NULL,
  `body_en`     VARCHAR(500) NULL,
  `body_bn`     VARCHAR(500) NULL,
  `link`        VARCHAR(255) NULL,
  `read_at`     DATETIME NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`, `read_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone`             VARCHAR(20)  NOT NULL,
  `message`           VARCHAR(640) NOT NULL COMMENT 'OTP values are masked before logging',
  `purpose`           ENUM('booking','otp','out_for_delivery','delivered','rto','settlement','password_reset','other') NOT NULL,
  `parcel_id`         BIGINT UNSIGNED NULL,
  `provider`          VARCHAR(40)  NULL,
  `provider_message_id` VARCHAR(80) NULL,
  `status`            ENUM('queued','sent','failed','delivered') NOT NULL DEFAULT 'queued',
  `error_message`     VARCHAR(255) NULL,
  `cost`              DECIMAL(8,4) NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`           DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sms_status` (`status`, `created_at`),
  KEY `idx_sms_parcel` (`parcel_id`),
  CONSTRAINT `fk_sms_parcel` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbound SMS queue / log';

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 12  TRIGGERS
-- ---------------------------------------------------------------------
DELIMITER $$

-- ===== 12.1 Ledger integrity ========================================

-- Lines may only be added/changed/removed while the entry is a draft,
-- may not hit group accounts, and must carry the sub-ledger party the
-- account demands (e.g. Merchant COD Payable requires party merchant).
DROP TRIGGER IF EXISTS `trg_journal_lines_bi`$$
CREATE TRIGGER `trg_journal_lines_bi` BEFORE INSERT ON `journal_lines`
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(10);
  DECLARE v_is_group TINYINT;
  DECLARE v_is_active TINYINT;
  DECLARE v_party VARCHAR(20);

  SELECT `status` INTO v_status FROM `journal_entries` WHERE `id` = NEW.journal_entry_id;
  IF v_status IS NULL OR v_status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: lines can only be added to a draft journal entry';
  END IF;

  SELECT `is_group`, `is_active`, `requires_party` INTO v_is_group, v_is_active, v_party
    FROM `chart_of_accounts` WHERE `id` = NEW.account_id;
  IF v_is_group = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: cannot post to a group (header) account';
  END IF;
  IF v_is_active = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: account is inactive';
  END IF;
  IF v_party <> 'none' AND (NEW.party_type <> v_party OR NEW.party_id IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: this account requires a matching sub-ledger party';
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_journal_lines_bu`$$
CREATE TRIGGER `trg_journal_lines_bu` BEFORE UPDATE ON `journal_lines`
FOR EACH ROW
BEGIN
  IF (SELECT `status` FROM `journal_entries` WHERE `id` = OLD.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: lines of a posted entry are immutable - post a reversal instead';
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_journal_lines_bd`$$
CREATE TRIGGER `trg_journal_lines_bd` BEFORE DELETE ON `journal_lines`
FOR EACH ROW
BEGIN
  IF (SELECT `status` FROM `journal_entries` WHERE `id` = OLD.journal_entry_id) <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: lines of a posted entry cannot be deleted';
  END IF;
END$$

-- Posting rule: Σdebit = Σcredit > 0, at least two lines, open fiscal
-- year, date after lock date. Posted entries are frozen except for the
-- posted → reversed transition.
DROP TRIGGER IF EXISTS `trg_journal_entries_bu`$$
CREATE TRIGGER `trg_journal_entries_bu` BEFORE UPDATE ON `journal_entries`
FOR EACH ROW
BEGIN
  DECLARE v_debit  DECIMAL(16,2) DEFAULT 0;
  DECLARE v_credit DECIMAL(16,2) DEFAULT 0;
  DECLARE v_lines  INT DEFAULT 0;
  DECLARE v_fy_ok  INT DEFAULT 0;

  IF OLD.status = 'reversed' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: a reversed entry cannot be modified';
  END IF;

  IF OLD.status = 'posted' THEN
    IF NEW.status NOT IN ('posted', 'reversed')
       OR NEW.entry_date <> OLD.entry_date OR NEW.branch_id <> OLD.branch_id
       OR NEW.total_debit <> OLD.total_debit OR NEW.total_credit <> OLD.total_credit
       OR NEW.fiscal_year_id <> OLD.fiscal_year_id OR NEW.entry_no <> OLD.entry_no THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: posted entries are immutable - only reversal is allowed';
    END IF;
    IF NEW.status = 'reversed' AND NEW.reversed_by_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: reversed_by_id is required when reversing';
    END IF;
  END IF;

  IF NEW.status = 'posted' AND OLD.status = 'draft' THEN
    SELECT COALESCE(SUM(`debit`), 0), COALESCE(SUM(`credit`), 0), COUNT(*)
      INTO v_debit, v_credit, v_lines
      FROM `journal_lines` WHERE `journal_entry_id` = NEW.id;

    IF v_lines < 2 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: a journal entry needs at least two lines';
    END IF;
    IF v_debit <> v_credit OR v_debit = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: journal entry is not balanced (debit <> credit)';
    END IF;

    SELECT COUNT(*) INTO v_fy_ok FROM `fiscal_years`
     WHERE `id` = NEW.fiscal_year_id
       AND NEW.entry_date BETWEEN `start_date` AND `end_date`
       AND `is_closed` = 0
       AND (`lock_date` IS NULL OR NEW.entry_date > `lock_date`);
    IF v_fy_ok = 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: entry date is outside an open fiscal year or inside a locked period';
    END IF;

    SET NEW.total_debit  = v_debit;
    SET NEW.total_credit = v_credit;
    IF NEW.posted_at IS NULL THEN
      SET NEW.posted_at = NOW();
    END IF;
  END IF;

  IF NEW.status = 'draft' AND OLD.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: an entry cannot return to draft';
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_journal_entries_bd`$$
CREATE TRIGGER `trg_journal_entries_bd` BEFORE DELETE ON `journal_entries`
FOR EACH ROW
BEGIN
  IF OLD.status <> 'draft' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: posted/reversed entries can never be deleted';
  END IF;
END$$

-- Block deleting/re-classing accounts that already carry postings.
DROP TRIGGER IF EXISTS `trg_coa_bu`$$
CREATE TRIGGER `trg_coa_bu` BEFORE UPDATE ON `chart_of_accounts`
FOR EACH ROW
BEGIN
  IF (NEW.account_class <> OLD.account_class OR NEW.normal_balance <> OLD.normal_balance OR NEW.is_group <> OLD.is_group)
     AND EXISTS (SELECT 1 FROM `journal_lines` WHERE `account_id` = OLD.id LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: account already has postings; class/balance/group cannot change';
  END IF;
  IF OLD.is_system = 1 AND (NEW.system_key <=> OLD.system_key) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: system_key of a system account cannot change';
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_coa_bd`$$
CREATE TRIGGER `trg_coa_bd` BEFORE DELETE ON `chart_of_accounts`
FOR EACH ROW
BEGIN
  IF OLD.is_system = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEDGER: system accounts cannot be deleted';
  END IF;
END$$

-- ===== 12.2 Audit log is append-only ================================
DROP TRIGGER IF EXISTS `trg_audit_logs_bu`$$
CREATE TRIGGER `trg_audit_logs_bu` BEFORE UPDATE ON `audit_logs`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AUDIT: audit log entries are immutable';
END$$

DROP TRIGGER IF EXISTS `trg_audit_logs_bd`$$
CREATE TRIGGER `trg_audit_logs_bd` BEFORE DELETE ON `audit_logs`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AUDIT: audit log entries cannot be deleted';
END$$

-- ===== 12.3 Automatic audit trail ===================================
-- @app_user_id / @app_branch_id / @app_ip are set by the PDO layer.

DROP TRIGGER IF EXISTS `trg_parcels_ai`$$
CREATE TRIGGER `trg_parcels_ai` AFTER INSERT ON `parcels`
FOR EACH ROW
BEGIN
  INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
  VALUES (@app_user_id, COALESCE(@app_branch_id, NEW.origin_branch_id), 'create', 'parcel', NEW.id, NULL,
          JSON_OBJECT('tracking_no', NEW.tracking_no, 'booking_type', NEW.booking_type, 'merchant_id', NEW.merchant_id,
                      'payment_type', NEW.payment_type, 'cod_amount', NEW.cod_amount, 'total_charge', NEW.total_charge,
                      'status', NEW.status),
          @app_ip, 'trigger');
END$$

DROP TRIGGER IF EXISTS `trg_parcels_bu`$$
CREATE TRIGGER `trg_parcels_bu` BEFORE UPDATE ON `parcels`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    IF NEW.status IN ('delivered', 'partially_delivered') AND NEW.delivered_at IS NULL THEN
      SET NEW.delivered_at = NOW();
    END IF;
    IF NEW.status IN ('returned_to_merchant') AND NEW.returned_at IS NULL THEN
      SET NEW.returned_at = NOW();
    END IF;
    IF NEW.status = 'cancelled' AND NEW.cancelled_at IS NULL THEN
      SET NEW.cancelled_at = NOW();
    END IF;
    IF NEW.status LIKE 'return%' OR NEW.status = 'rto_initiated' THEN
      SET NEW.is_rto = 1;
    END IF;
  END IF;
  -- Financial fields are frozen once the parcel is in (or past) settlement.
  IF OLD.settlement_status IN ('in_settlement', 'settled') AND NEW.settlement_status = OLD.settlement_status
     AND (NEW.cod_amount <> OLD.cod_amount OR NEW.collected_amount <> OLD.collected_amount
          OR NEW.delivery_charge <> OLD.delivery_charge OR NEW.cod_charge <> OLD.cod_charge
          OR NEW.return_charge <> OLD.return_charge OR NEW.total_charge <> OLD.total_charge) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'PARCEL: amounts are locked after settlement';
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_parcels_au`$$
CREATE TRIGGER `trg_parcels_au` AFTER UPDATE ON `parcels`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, COALESCE(@app_branch_id, NEW.current_branch_id), 'status_change', 'parcel', NEW.id,
            JSON_OBJECT('status', OLD.status, 'current_branch_id', OLD.current_branch_id),
            JSON_OBJECT('status', NEW.status, 'current_branch_id', NEW.current_branch_id),
            @app_ip, 'trigger');
  END IF;
  IF NEW.cod_amount <> OLD.cod_amount OR NEW.collected_amount <> OLD.collected_amount
     OR NEW.delivery_charge <> OLD.delivery_charge OR NEW.cod_charge <> OLD.cod_charge
     OR NEW.discount_amount <> OLD.discount_amount OR NEW.total_charge <> OLD.total_charge
     OR NEW.receiver_phone <> OLD.receiver_phone OR NEW.receiver_address <> OLD.receiver_address THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, COALESCE(@app_branch_id, NEW.current_branch_id), 'update', 'parcel', NEW.id,
            JSON_OBJECT('cod_amount', OLD.cod_amount, 'collected_amount', OLD.collected_amount,
                        'delivery_charge', OLD.delivery_charge, 'cod_charge', OLD.cod_charge,
                        'discount_amount', OLD.discount_amount, 'total_charge', OLD.total_charge,
                        'receiver_phone', OLD.receiver_phone, 'receiver_address', OLD.receiver_address),
            JSON_OBJECT('cod_amount', NEW.cod_amount, 'collected_amount', NEW.collected_amount,
                        'delivery_charge', NEW.delivery_charge, 'cod_charge', NEW.cod_charge,
                        'discount_amount', NEW.discount_amount, 'total_charge', NEW.total_charge,
                        'receiver_phone', NEW.receiver_phone, 'receiver_address', NEW.receiver_address),
            @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_users_au`$$
CREATE TRIGGER `trg_users_au` AFTER UPDATE ON `users`
FOR EACH ROW
BEGIN
  IF NEW.role_id <> OLD.role_id OR (NEW.branch_id <=> OLD.branch_id) = 0 OR NEW.status <> OLD.status
     OR NEW.email <> OLD.email OR NEW.phone <> OLD.phone OR (NEW.deleted_at <=> OLD.deleted_at) = 0 THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_branch_id, 'update', 'user', NEW.id,
            JSON_OBJECT('role_id', OLD.role_id, 'branch_id', OLD.branch_id, 'status', OLD.status,
                        'email', OLD.email, 'phone', OLD.phone, 'deleted_at', OLD.deleted_at),
            JSON_OBJECT('role_id', NEW.role_id, 'branch_id', NEW.branch_id, 'status', NEW.status,
                        'email', NEW.email, 'phone', NEW.phone, 'deleted_at', NEW.deleted_at),
            @app_ip, 'trigger');
  END IF;
  IF NEW.password_hash <> OLD.password_hash THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_branch_id, 'password_change', 'user', NEW.id, NULL, NULL, @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_role_permissions_ai`$$
CREATE TRIGGER `trg_role_permissions_ai` AFTER INSERT ON `role_permissions`
FOR EACH ROW
BEGIN
  IF @app_user_id IS NOT NULL THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_branch_id, 'grant', 'role_permission', NEW.role_id,
            JSON_OBJECT('permission_id', NEW.permission_id), @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_role_permissions_ad`$$
CREATE TRIGGER `trg_role_permissions_ad` AFTER DELETE ON `role_permissions`
FOR EACH ROW
BEGIN
  IF @app_user_id IS NOT NULL THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_branch_id, 'revoke', 'role_permission', OLD.role_id,
            JSON_OBJECT('permission_id', OLD.permission_id), @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_journal_entries_au`$$
CREATE TRIGGER `trg_journal_entries_au` AFTER UPDATE ON `journal_entries`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, NEW.branch_id, IF(NEW.status = 'posted', 'post', 'reverse'), 'journal_entry', NEW.id,
            JSON_OBJECT('status', OLD.status),
            JSON_OBJECT('status', NEW.status, 'entry_no', NEW.entry_no, 'total', NEW.total_debit,
                        'source_type', NEW.source_type, 'source_id', NEW.source_id, 'reversed_by_id', NEW.reversed_by_id),
            @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_vouchers_au`$$
CREATE TRIGGER `trg_vouchers_au` AFTER UPDATE ON `vouchers`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status OR NEW.amount <> OLD.amount THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, NEW.branch_id, 'status_change', 'voucher', NEW.id,
            JSON_OBJECT('status', OLD.status, 'amount', OLD.amount),
            JSON_OBJECT('status', NEW.status, 'amount', NEW.amount, 'approved_by', NEW.approved_by),
            @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_settlements_au`$$
CREATE TRIGGER `trg_settlements_au` AFTER UPDATE ON `merchant_settlements`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status OR NEW.net_payable <> OLD.net_payable THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, NEW.branch_id, 'status_change', 'merchant_settlement', NEW.id,
            JSON_OBJECT('status', OLD.status, 'net_payable', OLD.net_payable),
            JSON_OBJECT('status', NEW.status, 'net_payable', NEW.net_payable,
                        'approved_by', NEW.approved_by, 'paid_by', NEW.paid_by, 'transaction_ref', NEW.transaction_ref),
            @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_rate_card_items_au`$$
CREATE TRIGGER `trg_rate_card_items_au` AFTER UPDATE ON `rate_card_items`
FOR EACH ROW
BEGIN
  IF NEW.base_charge <> OLD.base_charge OR NEW.additional_charge <> OLD.additional_charge
     OR NEW.cod_percent <> OLD.cod_percent OR NEW.return_charge_percent <> OLD.return_charge_percent
     OR NEW.base_weight_kg <> OLD.base_weight_kg OR NEW.is_active <> OLD.is_active THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, @app_branch_id, 'update', 'rate_card_item', NEW.id,
            JSON_OBJECT('base_charge', OLD.base_charge, 'additional_charge', OLD.additional_charge, 'cod_percent', OLD.cod_percent,
                        'return_charge_percent', OLD.return_charge_percent, 'base_weight_kg', OLD.base_weight_kg, 'is_active', OLD.is_active),
            JSON_OBJECT('base_charge', NEW.base_charge, 'additional_charge', NEW.additional_charge, 'cod_percent', NEW.cod_percent,
                        'return_charge_percent', NEW.return_charge_percent, 'base_weight_kg', NEW.base_weight_kg, 'is_active', NEW.is_active),
            @app_ip, 'trigger');
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_rider_handovers_au`$$
CREATE TRIGGER `trg_rider_handovers_au` AFTER UPDATE ON `rider_cash_handovers`
FOR EACH ROW
BEGIN
  IF NEW.status <> OLD.status OR NEW.cash_amount <> OLD.cash_amount THEN
    INSERT INTO `audit_logs` (`user_id`, `branch_id`, `action`, `entity_type`, `entity_id`, `old_value`, `new_value`, `ip_address`, `source`)
    VALUES (@app_user_id, NEW.branch_id, 'status_change', 'rider_cash_handover', NEW.id,
            JSON_OBJECT('status', OLD.status, 'cash_amount', OLD.cash_amount, 'short_amount', OLD.short_amount),
            JSON_OBJECT('status', NEW.status, 'cash_amount', NEW.cash_amount, 'short_amount', NEW.short_amount),
            @app_ip, 'trigger');
  END IF;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 13  REPORTING VIEWS  (posted entries only)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_ledger_lines` AS
SELECT jl.id            AS line_id,
       je.id            AS journal_entry_id,
       je.entry_no,
       je.entry_date,
       je.voucher_type,
       je.source_type,
       je.source_id,
       je.narration,
       jl.branch_id,
       jl.account_id,
       a.code           AS account_code,
       a.name_en        AS account_name_en,
       a.name_bn        AS account_name_bn,
       a.account_class,
       a.normal_balance,
       jl.debit,
       jl.credit,
       jl.party_type,
       jl.party_id,
       jl.parcel_id,
       jl.description
  FROM journal_lines jl
  JOIN journal_entries je   ON je.id = jl.journal_entry_id
  JOIN chart_of_accounts a  ON a.id  = jl.account_id
 WHERE je.status IN ('posted', 'reversed');

CREATE OR REPLACE VIEW `v_trial_balance` AS
SELECT a.id AS account_id, a.code, a.name_en, a.name_bn, a.account_class, a.normal_balance, a.report_group,
       COALESCE(SUM(l.debit), 0)  AS total_debit,
       COALESCE(SUM(l.credit), 0) AS total_credit,
       CASE WHEN a.normal_balance = 'debit'
            THEN COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0)
            ELSE COALESCE(SUM(l.credit), 0) - COALESCE(SUM(l.debit), 0) END AS balance
  FROM chart_of_accounts a
  LEFT JOIN v_ledger_lines l ON l.account_id = a.id
 WHERE a.is_group = 0
 GROUP BY a.id, a.code, a.name_en, a.name_bn, a.account_class, a.normal_balance, a.report_group;

CREATE OR REPLACE VIEW `v_branch_account_balances` AS
SELECT l.branch_id, l.account_id, l.account_code, l.account_name_en, l.account_name_bn, l.account_class,
       SUM(l.debit) AS total_debit, SUM(l.credit) AS total_credit,
       CASE WHEN l.normal_balance = 'debit' THEN SUM(l.debit) - SUM(l.credit)
            ELSE SUM(l.credit) - SUM(l.debit) END AS balance
  FROM v_ledger_lines l
 GROUP BY l.branch_id, l.account_id, l.account_code, l.account_name_en, l.account_name_bn, l.account_class, l.normal_balance;

CREATE OR REPLACE VIEW `v_merchant_balances` AS
SELECT m.id AS merchant_id, m.code, m.business_name,
       COALESCE(SUM(CASE WHEN a.system_key = 'MERCHANT_COD_PAYABLE' THEN l.credit - l.debit END), 0) AS cod_payable,
       COALESCE(SUM(CASE WHEN a.system_key = 'AR_MERCHANT'          THEN l.debit - l.credit END), 0) AS receivable
  FROM merchants m
  LEFT JOIN v_ledger_lines l    ON l.party_type = 'merchant' AND l.party_id = m.id
  LEFT JOIN chart_of_accounts a ON a.id = l.account_id
 GROUP BY m.id, m.code, m.business_name;

CREATE OR REPLACE VIEW `v_rider_cash_balances` AS
SELECT r.id AS rider_id, r.rider_code, r.branch_id, u.name AS rider_name, r.cash_limit,
       COALESCE(SUM(l.debit - l.credit), 0) AS cash_in_hand
  FROM riders r
  JOIN users u ON u.id = r.user_id
  LEFT JOIN v_ledger_lines l ON l.party_type = 'rider' AND l.party_id = r.id
       AND l.account_id = (SELECT id FROM chart_of_accounts WHERE system_key = 'RIDER_RECEIVABLE')
 GROUP BY r.id, r.rider_code, r.branch_id, u.name, r.cash_limit;

CREATE OR REPLACE VIEW `v_pending_cod_settlement` AS
SELECT p.merchant_id,
       COUNT(*)                                   AS parcel_count,
       SUM(p.collected_amount)                    AS cod_collected,
       SUM(p.delivery_charge + p.fragile_charge + p.packaging_charge - p.discount_amount) AS delivery_charges,
       SUM(p.cod_charge)                          AS cod_charges,
       SUM(p.return_charge)                       AS return_charges,
       SUM(p.vat_amount)                          AS vat,
       SUM(p.collected_amount - p.total_charge)   AS net_payable,
       MIN(COALESCE(p.delivered_at, p.returned_at)) AS oldest_event
  FROM parcels p
 WHERE p.booking_type = 'b2b'
   AND p.settlement_status = 'unsettled'
   AND p.charge_payment_status <> 'invoiced'
   AND p.status IN ('delivered', 'partially_delivered', 'returned_to_merchant')
   AND p.deleted_at IS NULL
 GROUP BY p.merchant_id;

CREATE OR REPLACE VIEW `v_parcel_status_summary` AS
SELECT p.current_branch_id AS branch_id, p.status, s.name_en, s.name_bn, s.badge_class,
       COUNT(*) AS parcel_count, SUM(p.cod_amount) AS cod_amount
  FROM parcels p
  JOIN parcel_statuses s ON s.code = p.status
 WHERE p.deleted_at IS NULL
 GROUP BY p.current_branch_id, p.status, s.name_en, s.name_bn, s.badge_class;

-- ---------------------------------------------------------------------
-- 14  SEED DATA
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

-- 14.1 Divisions ------------------------------------------------------
INSERT INTO `divisions` (`id`, `code`, `name_en`, `name_bn`) VALUES
 (1, 'DHK', 'Dhaka',      'ঢাকা'),
 (2, 'CTG', 'Chattogram', 'চট্টগ্রাম'),
 (3, 'RAJ', 'Rajshahi',   'রাজশাহী'),
 (4, 'KHL', 'Khulna',     'খুলনা'),
 (5, 'BAR', 'Barishal',   'বরিশাল'),
 (6, 'SYL', 'Sylhet',     'সিলেট'),
 (7, 'RNG', 'Rangpur',    'রংপুর'),
 (8, 'MYM', 'Mymensingh', 'ময়মনসিংহ');

-- 14.2 All 64 districts ------------------------------------------------
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

-- 14.3 Pricing zones ----------------------------------------------------
INSERT INTO `zones` (`id`, `code`, `name_en`, `name_bn`, `zone_type`, `description`) VALUES
 (1, 'ISD', 'Inside Dhaka',      'ঢাকা সিটির ভিতরে',     'metro',    'Dhaka North & South City Corporation thanas'),
 (2, 'SUB', 'Dhaka Suburbs',     'ঢাকা শহরতলী',          'suburb',   'Savar, Keraniganj, Gazipur, Narayanganj'),
 (3, 'CTG', 'Inside Chattogram', 'চট্টগ্রাম সিটির ভিতরে', 'metro',    'Chattogram City Corporation thanas'),
 (4, 'OSD', 'Outside Dhaka',     'ঢাকার বাইরে',           'district', 'All other district towns and upazilas'),
 (5, 'RMT', 'Remote Areas',      'দুর্গম এলাকা',           'remote',   'Hill tracts, islands and hard-to-reach areas');

-- 14.4 Thanas -----------------------------------------------------------
-- Dhaka metro (zone ISD)
INSERT INTO `thanas` (`id`, `district_id`, `zone_id`, `name_en`, `name_bn`, `postcode`) VALUES
 ( 1,1,1,'Dhanmondi','ধানমন্ডি','1209'),        ( 2,1,1,'Gulshan','গুলশান','1212'),
 ( 3,1,1,'Banani','বনানী','1213'),             ( 4,1,1,'Mohammadpur','মোহাম্মদপুর','1207'),
 ( 5,1,1,'Mirpur','মিরপুর','1216'),            ( 6,1,1,'Pallabi','পল্লবী','1216'),
 ( 7,1,1,'Kafrul','কাফরুল','1206'),            ( 8,1,1,'Uttara','উত্তরা','1230'),
 ( 9,1,1,'Tejgaon','তেজগাঁও','1208'),          (10,1,1,'Motijheel','মতিঝিল','1000'),
 (11,1,1,'Ramna','রমনা','1217'),               (12,1,1,'Paltan','পল্টন','1000'),
 (13,1,1,'Khilgaon','খিলগাঁও','1219'),         (14,1,1,'Badda','বাড্ডা','1212'),
 (15,1,1,'Rampura','রামপুরা','1219'),          (16,1,1,'Lalbagh','লালবাগ','1211'),
 (17,1,1,'Kotwali (Dhaka)','কোতোয়ালী (ঢাকা)','1100'), (18,1,1,'Sutrapur','সূত্রাপুর','1100'),
 (19,1,1,'Jatrabari','যাত্রাবাড়ী','1204'),     (20,1,1,'Demra','ডেমরা','1360'),
 (21,1,1,'Khilkhet','খিলক্ষেত','1229'),        (22,1,1,'Cantonment','ক্যান্টনমেন্ট','1206'),
 (23,1,1,'Shahbagh','শাহবাগ','1000'),          (24,1,1,'Kalabagan','কলাবাগান','1205'),
 (25,1,1,'Hazaribagh','হাজারীবাগ','1209'),      (26,1,1,'Shyampur','শ্যামপুর','1204'),
 (27,1,1,'Adabor','আদাবর','1207'),             (28,1,1,'Turag','তুরাগ','1230'),
 (29,1,1,'Dakshinkhan','দক্ষিণখান','1230'),    (30,1,1,'Uttarkhan','উত্তরখান','1230');

-- Dhaka suburbs (zone SUB)
INSERT INTO `thanas` (`id`, `district_id`, `zone_id`, `name_en`, `name_bn`, `postcode`) VALUES
 (31,1,2,'Savar','সাভার','1340'),               (32,1,2,'Keraniganj','কেরানীগঞ্জ','1310'),
 (33,1,2,'Dhamrai','ধামরাই','1350'),            (34,2,2,'Gazipur Sadar','গাজীপুর সদর','1700'),
 (35,2,2,'Tongi','টঙ্গী','1710'),               (36,3,2,'Narayanganj Sadar','নারায়ণগঞ্জ সদর','1400'),
 (37,3,2,'Fatullah','ফতুল্লা','1421'),           (38,3,2,'Siddhirganj','সিদ্ধিরগঞ্জ','1430');

-- Chattogram metro (zone CTG)
INSERT INTO `thanas` (`id`, `district_id`, `zone_id`, `name_en`, `name_bn`, `postcode`) VALUES
 (39,14,3,'Kotwali (Chattogram)','কোতোয়ালী (চট্টগ্রাম)','4000'),
 (40,14,3,'Double Mooring','ডবলমুরিং','4100'),   (41,14,3,'Panchlaish','পাঁচলাইশ','4203'),
 (42,14,3,'Chandgaon','চান্দগাঁও','4212'),        (43,14,3,'Bakalia','বাকলিয়া','4203'),
 (44,14,3,'Halishahar','হালিশহর','4216'),         (45,14,3,'Pahartali','পাহাড়তলী','4202'),
 (46,14,3,'Khulshi','খুলশী','4225'),              (47,14,3,'Bayazid Bostami','বায়েজিদ বোস্তামী','4210'),
 (48,14,3,'Patenga','পতেঙ্গা','4204');

-- One "Sadar" thana for every other district (zone OSD); remote zone for hill tracts.
INSERT INTO `thanas` (`district_id`, `zone_id`, `name_en`, `name_bn`)
SELECT d.id,
       CASE WHEN d.id IN (22, 23, 24) THEN 5 ELSE 4 END,
       CONCAT(d.name_en, ' Sadar'),
       CONCAT(d.name_bn, ' সদর')
  FROM districts d
 WHERE d.id NOT IN (1, 2, 3, 14)
 ORDER BY d.id;

-- 14.5 Courier partner (3PL) — referenced by coverage below -----------
INSERT INTO `courier_partners` (`id`, `code`, `name`, `contact_person`, `phone`, `charge_type`, `charge_value`, `cod_remit_days`, `is_active`) VALUES
 (1, 'P-NATIONWIDE', 'Nationwide Partner Courier (sample)', 'Partner Desk', '01700000099', 'flat', 70.00, 7, 1);

-- 14.6 Branch network (hub-and-spoke) ---------------------------------
INSERT INTO `branches` (`id`, `code`, `name_en`, `name_bn`, `branch_type`, `parent_hub_id`, `thana_id`, `address`, `phone`, `opening_time`, `closing_time`, `cash_limit`, `can_book`, `can_deliver`) VALUES
 ( 1, 'HO',      'Head Office',               'প্রধান কার্যালয়',            'head_office', NULL,  9, 'Tejgaon Industrial Area, Dhaka 1208', '01700000001', '09:00', '18:00', 500000.00, 0, 0),
 ( 2, 'DHK-HUB', 'Dhaka Central Hub',         'ঢাকা কেন্দ্রীয় হাব',          'hub',         NULL,  9, 'Tejgaon Link Road, Dhaka 1208',       '01700000002', '00:00', '23:59', 300000.00, 0, 1),
 ( 3, 'CTG-HUB', 'Chattogram Regional Hub',   'চট্টগ্রাম আঞ্চলিক হাব',       'hub',         NULL, 40, 'Agrabad C/A, Chattogram 4100',        '01700000003', '00:00', '23:59', 200000.00, 0, 0),
 ( 4, 'DHN',     'Dhanmondi Branch',          'ধানমন্ডি শাখা',               'branch',      2,     1, 'Road 27, Dhanmondi, Dhaka 1209',      '01700000004', '09:00', '21:00', 100000.00, 1, 1),
 ( 5, 'MRP',     'Mirpur Branch',             'মিরপুর শাখা',                 'branch',      2,     5, 'Mirpur 10, Dhaka 1216',               '01700000005', '09:00', '21:00', 100000.00, 1, 1),
 ( 6, 'UTR',     'Uttara Branch',             'উত্তরা শাখা',                 'branch',      2,     8, 'Sector 7, Uttara, Dhaka 1230',        '01700000006', '09:00', '21:00', 100000.00, 1, 1),
 ( 7, 'MTJ',     'Motijheel Branch',          'মতিঝিল শাখা',                 'branch',      2,    10, 'Motijheel C/A, Dhaka 1000',           '01700000007', '09:00', '21:00', 100000.00, 1, 1),
 ( 8, 'SVR',     'Savar Branch',              'সাভার শাখা',                  'branch',      2,    31, 'Savar Bazar Road, Savar 1340',        '01700000008', '09:00', '20:00',  75000.00, 1, 1),
 ( 9, 'AGR',     'Agrabad Branch',            'আগ্রাবাদ শাখা',               'branch',      3,    40, 'Agrabad Access Road, Chattogram 4100', '01700000009', '09:00', '21:00', 100000.00, 1, 1),
 (10, 'SYL',     'Sylhet Branch',             'সিলেট শাখা',                  'branch',      2,    (SELECT t.id FROM thanas t WHERE t.name_en = 'Sylhet Sadar'), 'Zindabazar, Sylhet 3100',           '01700000010', '09:00', '20:00',  75000.00, 1, 1),
 (11, 'RAJ',     'Rajshahi Branch',           'রাজশাহী শাখা',                'branch',      2,    (SELECT t.id FROM thanas t WHERE t.name_en = 'Rajshahi Sadar'), 'Shaheb Bazar, Rajshahi 6100',       '01700000011', '09:00', '20:00',  75000.00, 1, 1),
 (12, 'KHL',     'Khulna Branch',             'খুলনা শাখা',                  'branch',      2,    (SELECT t.id FROM thanas t WHERE t.name_en = 'Khulna Sadar'), 'KDA Avenue, Khulna 9100',           '01700000012', '09:00', '20:00',  75000.00, 1, 1);

-- 14.7 Delivery coverage --------------------------------------------------
-- Own-fleet coverage inside the metro areas
INSERT INTO `branch_coverage` (`thana_id`, `branch_id`, `delivery_mode`, `sla_hours`)
SELECT t.id,
       CASE
         WHEN t.id IN (1, 4, 11, 16, 23, 24, 25, 27)            THEN 4   -- Dhanmondi
         WHEN t.id IN (5, 6, 7, 9, 22)                          THEN 5   -- Mirpur
         WHEN t.id IN (2, 3, 8, 14, 21, 28, 29, 30)             THEN 6   -- Uttara
         WHEN t.id IN (10, 12, 13, 15, 17, 18, 19, 20, 26)      THEN 7   -- Motijheel
         WHEN t.id BETWEEN 31 AND 38                            THEN 8   -- Savar (suburbs)
         WHEN t.id BETWEEN 39 AND 48                            THEN 9   -- Agrabad (Chattogram metro)
       END,
       'own_fleet',
       CASE WHEN t.zone_id = 2 THEN 48 ELSE 24 END
  FROM thanas t
 WHERE t.id BETWEEN 1 AND 48;

-- District towns: own fleet at the three district branches, 3PL partner elsewhere
INSERT INTO `branch_coverage` (`thana_id`, `branch_id`, `delivery_mode`, `partner_id`, `sla_hours`)
SELECT t.id,
       CASE
         WHEN d.division_id = 2                                 THEN 9   -- Chattogram division via Agrabad
         WHEN d.division_id = 6                                 THEN 10  -- Sylhet division
         WHEN d.division_id IN (3, 7)                           THEN 11  -- Rajshahi & Rangpur divisions
         WHEN d.division_id IN (4, 5)                           THEN 12  -- Khulna & Barishal divisions
         ELSE 2                                                          -- Dhaka & Mymensingh divisions via Central Hub
       END,
       CASE WHEN d.id IN (25, 33, 49) THEN 'own_fleet' ELSE 'partner' END,
       CASE WHEN d.id IN (25, 33, 49) THEN NULL ELSE 1 END,
       CASE WHEN t.zone_id = 5 THEN 120 ELSE 72 END
  FROM thanas t
  JOIN districts d ON d.id = t.district_id
 WHERE t.id > 48;

-- 14.8 Hub routing --------------------------------------------------------
INSERT INTO `routing_rules` (`origin_hub_id`, `destination_hub_id`, `via_hub_id`, `transit_hours`, `line_haul_mode`, `departure_time`, `priority`) VALUES
 (2, 3, NULL, 12, 'road', '22:00:00', 1),
 (3, 2, NULL, 12, 'road', '22:00:00', 1);

-- 14.9 Roles ----------------------------------------------------------------
INSERT INTO `roles` (`id`, `slug`, `name_en`, `name_bn`, `description`, `scope`, `is_system`) VALUES
 (1, 'super_admin',      'Super Admin',          'সুপার অ্যাডমিন',        'Full system access, consolidated multi-branch view, system settings', 'global', 1),
 (2, 'branch_manager',   'Branch Manager',       'শাখা ব্যবস্থাপক',        'Branch operations, rider assignment, branch cash & accounts',         'branch', 1),
 (3, 'booking_operator', 'Booking Operator',     'বুকিং অপারেটর',          'Parcel booking, label printing, retail payment collection',          'branch', 1),
 (4, 'hub_officer',      'Hub/Dispatch Officer', 'হাব/ডিসপ্যাচ অফিসার',    'Manifests, bag/line-haul dispatch, incoming scanning',               'branch', 1),
 (5, 'rider',            'Delivery Rider',       'ডেলিভারি রাইডার',        'Run-sheet, POD capture, COD collection, status updates',             'self',   1),
 (6, 'accountant',       'Accountant',           'হিসাবরক্ষক',             'Ledger, COD payouts, vouchers, financial reports',                   'global', 1),
 (7, 'merchant',         'Corporate Merchant',   'কর্পোরেট মার্চেন্ট',      'Self-service booking, tracking, COD invoices & payouts',             'self',   1);

-- 14.10 Permissions --------------------------------------------------------
INSERT INTO `permissions` (`slug`, `module`, `name_en`, `name_bn`) VALUES
 ('dashboard.view',            'dashboard',  'View dashboard',                         'ড্যাশবোর্ড দেখা'),
 ('dashboard.consolidated',    'dashboard',  'View consolidated (all-branch) dashboard', 'সমন্বিত ড্যাশবোর্ড দেখা'),
 ('branch.view',               'branch',     'View branches',                          'শাখা দেখা'),
 ('branch.manage',             'branch',     'Create/edit branches & routing',         'শাখা ও রাউটিং ব্যবস্থাপনা'),
 ('geo.manage',                'branch',     'Manage divisions, districts, thanas & zones', 'বিভাগ, জেলা, থানা ও জোন ব্যবস্থাপনা'),
 ('user.view',                 'user',       'View users',                             'ব্যবহারকারী দেখা'),
 ('user.manage',               'user',       'Create/edit users',                      'ব্যবহারকারী ব্যবস্থাপনা'),
 ('role.manage',               'user',       'Manage roles & permissions',             'রোল ও পারমিশন ব্যবস্থাপনা'),
 ('merchant.view',             'merchant',   'View merchants',                         'মার্চেন্ট দেখা'),
 ('merchant.manage',           'merchant',   'Create/edit merchants',                  'মার্চেন্ট ব্যবস্থাপনা'),
 ('merchant.approve',          'merchant',   'Approve merchants & rate agreements',    'মার্চেন্ট ও রেট চুক্তি অনুমোদন'),
 ('customer.view',             'customer',   'View customers',                         'গ্রাহক দেখা'),
 ('customer.manage',           'customer',   'Create/edit customers',                  'গ্রাহক ব্যবস্থাপনা'),
 ('pricing.view',              'pricing',    'View rate cards',                        'রেট কার্ড দেখা'),
 ('pricing.manage',            'pricing',    'Manage rate cards & tariffs',            'রেট কার্ড ও ট্যারিফ ব্যবস্থাপনা'),
 ('parcel.view',               'parcel',     'View parcels',                           'পার্সেল দেখা'),
 ('parcel.view_all_branches',  'parcel',     'View parcels of all branches',           'সকল শাখার পার্সেল দেখা'),
 ('parcel.create',             'parcel',     'Book parcels',                           'পার্সেল বুকিং'),
 ('parcel.update',             'parcel',     'Edit parcels',                           'পার্সেল সম্পাদনা'),
 ('parcel.cancel',             'parcel',     'Cancel parcels',                         'পার্সেল বাতিল'),
 ('parcel.bulk_upload',        'parcel',     'Bulk CSV booking',                       'বাল্ক CSV বুকিং'),
 ('parcel.label_print',        'parcel',     'Print shipping labels',                  'শিপিং লেবেল প্রিন্ট'),
 ('parcel.track',              'parcel',     'Track parcels',                          'পার্সেল ট্র্যাকিং'),
 ('parcel.status_override',    'parcel',     'Override parcel status',                 'পার্সেল স্ট্যাটাস ওভাররাইড'),
 ('pickup.view',               'pickup',     'View pickup requests',                   'পিকআপ অনুরোধ দেখা'),
 ('pickup.manage',             'pickup',     'Assign & complete pickups',              'পিকআপ ব্যবস্থাপনা'),
 ('manifest.view',             'manifest',   'View manifests',                         'ম্যানিফেস্ট দেখা'),
 ('manifest.create',           'manifest',   'Create manifests & bags',                'ম্যানিফেস্ট ও ব্যাগ তৈরি'),
 ('manifest.dispatch',         'manifest',   'Dispatch manifests (scan-out)',          'ম্যানিফেস্ট প্রেরণ (স্ক্যান-আউট)'),
 ('manifest.receive',          'manifest',   'Receive manifests (scan-in)',            'ম্যানিফেস্ট গ্রহণ (স্ক্যান-ইন)'),
 ('drs.view',                  'delivery',   'View delivery run-sheets',               'ডেলিভারি রান-শিট দেখা'),
 ('drs.create',                'delivery',   'Create run-sheets & assign riders/partners', 'রান-শিট তৈরি ও রাইডার/পার্টনার নির্ধারণ'),
 ('drs.close',                 'delivery',   'Close run-sheets',                       'রান-শিট বন্ধ করা'),
 ('delivery.update',           'delivery',   'Update delivery status',                 'ডেলিভারি স্ট্যাটাস হালনাগাদ'),
 ('delivery.pod',              'delivery',   'Capture proof of delivery',              'ডেলিভারি প্রমাণ সংগ্রহ'),
 ('delivery.cod_collect',      'delivery',   'Collect COD',                            'সিওডি সংগ্রহ'),
 ('rto.view',                  'delivery',   'View returns (RTO)',                     'রিটার্ন (RTO) দেখা'),
 ('rto.manage',                'delivery',   'Initiate/approve/close returns',         'রিটার্ন ব্যবস্থাপনা'),
 ('partner.view',              'delivery',   'View courier partners',                  'কুরিয়ার পার্টনার দেখা'),
 ('partner.manage',            'delivery',   'Manage courier partners',                'কুরিয়ার পার্টনার ব্যবস্থাপনা'),
 ('rider.view',                'delivery',   'View riders',                            'রাইডার দেখা'),
 ('rider.manage',              'delivery',   'Manage riders',                          'রাইডার ব্যবস্থাপনা'),
 ('cash.handover_receive',     'cash',       'Receive rider cash handover',            'রাইডারের নগদ জমা গ্রহণ'),
 ('cash.handover_view',        'cash',       'View cash handovers',                    'নগদ জমা দেখা'),
 ('accounts.view',             'accounts',   'View ledgers',                           'লেজার দেখা'),
 ('accounts.coa_manage',       'accounts',   'Manage chart of accounts',               'হিসাব তালিকা ব্যবস্থাপনা'),
 ('accounts.journal_create',   'accounts',   'Create journal entries',                 'জার্নাল এন্ট্রি তৈরি'),
 ('accounts.journal_post',     'accounts',   'Post journal entries',                   'জার্নাল এন্ট্রি পোস্ট'),
 ('accounts.journal_reverse',  'accounts',   'Reverse journal entries',                'জার্নাল এন্ট্রি রিভার্স'),
 ('accounts.period_close',     'accounts',   'Lock periods / close fiscal year',       'হিসাবকাল লক / অর্থবছর সমাপ্তি'),
 ('voucher.create',            'accounts',   'Create vouchers',                        'ভাউচার তৈরি'),
 ('voucher.approve',           'accounts',   'Approve vouchers',                       'ভাউচার অনুমোদন'),
 ('invoice.view',              'billing',    'View invoices',                          'ইনভয়েস দেখা'),
 ('invoice.create',            'billing',    'Create & issue invoices',                'ইনভয়েস তৈরি ও ইস্যু'),
 ('invoice.payment',           'billing',    'Record invoice payments',                'ইনভয়েস পেমেন্ট গ্রহণ'),
 ('settlement.view',           'billing',    'View merchant settlements',              'মার্চেন্ট সেটেলমেন্ট দেখা'),
 ('settlement.create',         'billing',    'Prepare merchant settlements',           'মার্চেন্ট সেটেলমেন্ট প্রস্তুত'),
 ('settlement.approve',        'billing',    'Approve merchant settlements',           'মার্চেন্ট সেটেলমেন্ট অনুমোদন'),
 ('settlement.pay',            'billing',    'Pay merchant settlements',               'মার্চেন্ট সেটেলমেন্ট পরিশোধ'),
 ('report.operations',         'report',     'Operational reports',                    'অপারেশনাল রিপোর্ট'),
 ('report.financial',          'report',     'Financial reports',                      'আর্থিক রিপোর্ট'),
 ('report.consolidated',       'report',     'Consolidated reports (all branches)',    'সমন্বিত রিপোর্ট (সকল শাখা)'),
 ('report.export',             'report',     'Export reports (CSV/PDF)',               'রিপোর্ট এক্সপোর্ট'),
 ('audit.view',                'system',     'View audit trail',                       'অডিট ট্রেইল দেখা'),
 ('settings.manage',           'system',     'Manage system settings',                 'সিস্টেম সেটিংস ব্যবস্থাপনা'),
 ('merchant_portal.access',    'portal',     'Merchant portal access',                 'মার্চেন্ট পোর্টাল প্রবেশ'),
 ('merchant_portal.booking',   'portal',     'Book parcels from merchant portal',      'মার্চেন্ট পোর্টাল থেকে বুকিং'),
 ('merchant_portal.bulk',      'portal',     'Bulk upload from merchant portal',       'মার্চেন্ট পোর্টাল থেকে বাল্ক আপলোড'),
 ('merchant_portal.finance',   'portal',     'View COD invoices & payouts',            'সিওডি ইনভয়েস ও পেআউট দেখা'),
 ('merchant_portal.api_keys',  'portal',     'Manage API keys',                        'API কী ব্যবস্থাপনা'),
 ('merchant_portal.pickup',    'portal',     'Request pickups',                        'পিকআপ অনুরোধ'),
 ('rider_portal.access',       'portal',     'Rider app access',                       'রাইডার অ্যাপ প্রবেশ');

-- 14.11 Role → permission matrix ------------------------------------------
-- Super Admin: everything
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 1, id FROM permissions;

-- Branch Manager
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 2, id FROM permissions WHERE slug IN (
 'dashboard.view','branch.view','user.view','merchant.view','merchant.manage','customer.view','customer.manage',
 'pricing.view','parcel.view','parcel.create','parcel.update','parcel.cancel','parcel.bulk_upload','parcel.label_print',
 'parcel.track','parcel.status_override','pickup.view','pickup.manage','manifest.view','manifest.create','manifest.dispatch',
 'manifest.receive','drs.view','drs.create','drs.close','delivery.update','rto.view','rto.manage','partner.view',
 'rider.view','rider.manage','cash.handover_receive','cash.handover_view','accounts.view','voucher.create',
 'invoice.view','settlement.view','report.operations','report.financial','report.export','audit.view');

-- Booking Operator
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 3, id FROM permissions WHERE slug IN (
 'dashboard.view','merchant.view','customer.view','customer.manage','pricing.view','parcel.view','parcel.create',
 'parcel.update','parcel.bulk_upload','parcel.label_print','parcel.track','pickup.view');

-- Hub / Dispatch Officer
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 4, id FROM permissions WHERE slug IN (
 'dashboard.view','parcel.view','parcel.track','parcel.label_print','manifest.view','manifest.create','manifest.dispatch',
 'manifest.receive','rto.view','drs.view','report.operations');

-- Delivery Rider
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 5, id FROM permissions WHERE slug IN (
 'rider_portal.access','drs.view','delivery.update','delivery.pod','delivery.cod_collect','parcel.track');

-- Accountant
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 6, id FROM permissions WHERE slug IN (
 'dashboard.view','dashboard.consolidated','branch.view','merchant.view','parcel.view','parcel.view_all_branches','parcel.track',
 'partner.view','rider.view','cash.handover_receive','cash.handover_view','accounts.view','accounts.coa_manage',
 'accounts.journal_create','accounts.journal_post','accounts.journal_reverse','accounts.period_close','voucher.create',
 'voucher.approve','invoice.view','invoice.create','invoice.payment','settlement.view','settlement.create',
 'settlement.approve','settlement.pay','report.financial','report.consolidated','report.export','audit.view');

-- Corporate Merchant
INSERT INTO `role_permissions` (`role_id`, `permission_id`) SELECT 7, id FROM permissions WHERE slug IN (
 'merchant_portal.access','merchant_portal.booking','merchant_portal.bulk','merchant_portal.finance',
 'merchant_portal.api_keys','merchant_portal.pickup','parcel.track');

-- 14.12 Service types & packaging ----------------------------------------
INSERT INTO `service_types` (`id`, `code`, `name_en`, `name_bn`, `sla_hours`, `is_fragile`, `same_zone_only`, `booking_cutoff`, `sort_order`) VALUES
 (1, 'REG', 'Regular',            'রেগুলার',            72, 0, 0, NULL,       1),
 (2, 'EXP', 'Express',            'এক্সপ্রেস',          24, 0, 0, '18:00:00', 2),
 (3, 'SDD', 'Same-Day Delivery',  'একই দিনে ডেলিভারি',  10, 0, 1, '12:00:00', 3),
 (4, 'FRG', 'Fragile Handling',   'ভঙ্গুর পণ্য',          72, 1, 0, NULL,       4);

INSERT INTO `packaging_types` (`id`, `code`, `name_en`, `name_bn`, `charge`) VALUES
 (1, 'NONE',     'No packaging (merchant packed)', 'প্যাকেজিং ছাড়া',         0.00),
 (2, 'POLY',     'Poly mailer',                    'পলি মেইলার',            10.00),
 (3, 'BOX_S',    'Carton box — small',             'কার্টন বক্স — ছোট',      30.00),
 (4, 'BOX_M',    'Carton box — medium',            'কার্টন বক্স — মাঝারি',    50.00),
 (5, 'BUBBLE',   'Bubble wrap',                    'বাবল র‍্যাপ',            25.00);

-- 14.13 Default rate card (Zone × Zone × Service) -------------------------
INSERT INTO `rate_cards` (`id`, `code`, `name`, `card_type`, `is_default`, `volumetric_divisor`, `effective_from`, `status`) VALUES
 (1, 'STD-2026', 'Standard Rate Card 2026', 'standard', 1, 5000, '2026-07-01', 'active');

INSERT INTO `rate_card_items`
  (`rate_card_id`, `origin_zone_id`, `destination_zone_id`, `service_type_id`, `base_weight_kg`, `base_charge`,
   `additional_weight_unit_kg`, `additional_charge`, `max_weight_kg`, `cod_percent`, `cod_min_charge`,
   `return_charge_percent`, `fragile_surcharge`, `sla_hours`)
SELECT 1, o.id, d.id, s.id, 1.000,
       -- base charge by zone pair
       (CASE
          WHEN o.code = 'RMT' OR d.code = 'RMT'                         THEN 150
          WHEN o.id = d.id AND o.code IN ('ISD', 'CTG')                THEN 60
          WHEN o.code IN ('ISD', 'SUB') AND d.code IN ('ISD', 'SUB')   THEN 80
          ELSE 120
        END)
       -- service premium
       + (CASE s.code WHEN 'EXP' THEN 40 WHEN 'SDD' THEN 70 WHEN 'FRG' THEN 20 ELSE 0 END),
       1.000,
       (CASE
          WHEN o.code = 'RMT' OR d.code = 'RMT'                         THEN 25
          WHEN o.id = d.id AND o.code IN ('ISD', 'CTG')                THEN 15
          ELSE 20
        END),
       (CASE WHEN s.code = 'SDD' THEN 5.000 ELSE 30.000 END),
       1.00,
       (CASE WHEN o.id = d.id AND o.code IN ('ISD', 'CTG') THEN 0.00 ELSE 10.00 END),
       50.00,
       (CASE WHEN s.code = 'FRG' THEN 30.00 ELSE 0.00 END),
       (CASE
          WHEN s.code = 'SDD'                                          THEN 10
          WHEN s.code = 'EXP'                                          THEN (CASE WHEN o.id = d.id THEN 24 ELSE 48 END)
          WHEN o.id = d.id AND o.code IN ('ISD', 'CTG')                THEN 48
          WHEN o.code = 'RMT' OR d.code = 'RMT'                         THEN 120
          ELSE 72
        END)
  FROM zones o
  CROSS JOIN zones d
  CROSS JOIN service_types s
 WHERE s.same_zone_only = 0
    OR (o.id = d.id AND o.code IN ('ISD', 'CTG'));

-- 14.14 Parcel statuses (bilingual) ----------------------------------------
INSERT INTO `parcel_statuses` (`code`, `name_en`, `name_bn`, `stage`, `badge_class`, `is_terminal`, `is_public`, `sort_order`) VALUES
 ('booked',                'Booked',                        'বুক করা হয়েছে',                'booking',     'secondary', 0, 1,  1),
 ('pickup_pending',        'Pickup Pending',                'পিকআপের অপেক্ষায়',              'pickup',      'secondary', 0, 1,  2),
 ('picked_up',             'Picked Up',                     'পিকআপ সম্পন্ন',                 'pickup',      'info',      0, 1,  3),
 ('at_origin_branch',      'Received at Origin Branch',     'উৎস শাখায় গৃহীত',              'origin',      'info',      0, 1,  4),
 ('in_transit_to_hub',     'In Transit to Hub',             'হাবের পথে',                    'transit',     'primary',   0, 1,  5),
 ('at_hub',                'Received at Hub',               'হাবে গৃহীত',                   'transit',     'primary',   0, 1,  6),
 ('in_transit',            'In Transit to Destination',     'গন্তব্যের পথে',                 'transit',     'primary',   0, 1,  7),
 ('at_destination_branch', 'Received at Destination Branch','গন্তব্য শাখায় পৌঁছেছে',         'destination', 'primary',   0, 1,  8),
 ('handed_to_partner',     'Handed to Delivery Partner',    'ডেলিভারি পার্টনারের কাছে হস্তান্তর', 'delivery',   'info',      0, 1,  9),
 ('out_for_delivery',      'Out for Delivery',              'ডেলিভারির জন্য বের হয়েছে',      'delivery',    'warning',   0, 1, 10),
 ('rescheduled',           'Rescheduled',                   'পুনঃনির্ধারিত',                 'delivery',    'warning',   0, 1, 11),
 ('on_hold',               'On Hold',                       'হোল্ডে রাখা হয়েছে',             'delivery',    'warning',   0, 1, 12),
 ('delivered',             'Delivered',                     'ডেলিভারি সম্পন্ন',               'closed',      'success',   1, 1, 13),
 ('partially_delivered',   'Partially Delivered',           'আংশিক ডেলিভারি',               'closed',      'success',   1, 1, 14),
 ('rto_initiated',         'Return Initiated',              'রিটার্ন শুরু হয়েছে',             'return',      'danger',    0, 1, 15),
 ('return_in_transit',     'Return in Transit',             'রিটার্ন পথে',                   'return',      'danger',    0, 1, 16),
 ('returned_to_origin',    'Returned to Origin Branch',     'উৎস শাখায় ফেরত এসেছে',         'return',      'danger',    0, 1, 17),
 ('returned_to_merchant',  'Returned to Merchant',          'মার্চেন্টকে ফেরত দেওয়া হয়েছে',   'closed',      'dark',      1, 1, 18),
 ('cancelled',             'Cancelled',                     'বাতিল',                        'closed',      'dark',      1, 1, 19),
 ('lost',                  'Lost',                          'হারিয়ে গেছে',                   'closed',      'danger',    1, 0, 20),
 ('damaged',               'Damaged',                       'ক্ষতিগ্রস্ত',                    'closed',      'danger',    1, 0, 21);

-- 14.15 Delivery failure reasons -------------------------------------------
INSERT INTO `delivery_failure_reasons` (`code`, `name_en`, `name_bn`, `outcome`, `counts_attempt`) VALUES
 ('NOT_REACHABLE',   'Receiver phone not reachable',       'প্রাপকের ফোনে যোগাযোগ করা যায়নি',     'reschedule', 1),
 ('NOT_AT_HOME',     'Receiver not available at address',  'প্রাপক ঠিকানায় উপস্থিত নেই',         'reschedule', 1),
 ('CUST_RESCHEDULE', 'Receiver requested another date',    'প্রাপক অন্য তারিখ চেয়েছেন',          'reschedule', 0),
 ('WRONG_ADDRESS',   'Incomplete or wrong address',        'অসম্পূর্ণ বা ভুল ঠিকানা',             'hold',       1),
 ('COD_DISPUTE',     'Receiver disputes COD amount',       'সিওডি টাকার পরিমাণ নিয়ে আপত্তি',      'hold',       1),
 ('REFUSED',         'Receiver refused the parcel',        'প্রাপক পার্সেল নিতে অস্বীকার করেছেন',   'return',     1),
 ('DAMAGED',         'Product damaged / wrong product',    'পণ্য ক্ষতিগ্রস্ত / ভুল পণ্য',          'return',     1),
 ('MERCHANT_CANCEL', 'Merchant cancelled the order',       'মার্চেন্ট অর্ডার বাতিল করেছেন',       'return',     0),
 ('AREA_UNREACHABLE','Area unreachable (weather/strike)',  'এলাকায় পৌঁছানো যায়নি (আবহাওয়া/হরতাল)', 'reschedule', 0),
 ('MAX_ATTEMPTS',    'Maximum delivery attempts reached',  'সর্বোচ্চ ডেলিভারি চেষ্টা সম্পন্ন',     'return',     0);

-- 14.16 Chart of Accounts ---------------------------------------------------
-- system_key values are what the posting engine (Phase 3) looks up; codes
-- and names may be changed freely, system_key may not.
INSERT INTO `chart_of_accounts`
 (`id`, `code`, `name_en`, `name_bn`, `parent_id`, `account_class`, `normal_balance`, `report_group`, `system_key`, `is_group`, `is_system`, `is_cash`, `is_bank`, `requires_party`) VALUES
 -- ASSETS
 (  1, '1000', 'Assets',                               'সম্পদ',                              NULL, 'asset', 'debit', 'current_asset', 'GRP_ASSETS',           1, 1, 0, 0, 'none'),
 (  2, '1100', 'Current Assets',                       'চলতি সম্পদ',                          1,   'asset', 'debit', 'current_asset', 'GRP_CURRENT_ASSETS',   1, 1, 0, 0, 'none'),
 (  3, '1110', 'Cash in Hand',                         'হাতে নগদ',                            2,   'asset', 'debit', 'current_asset', 'CASH_IN_HAND',         0, 1, 1, 0, 'none'),
 (  4, '1111', 'Petty Cash',                           'পেটি ক্যাশ',                          2,   'asset', 'debit', 'current_asset', 'PETTY_CASH',           0, 1, 1, 0, 'none'),
 (  5, '1115', 'Cash in Transit (Branch → Bank/HO)',   'ট্রানজিটে নগদ (শাখা → ব্যাংক/প্রধান কার্যালয়)', 2, 'asset', 'debit', 'current_asset', 'CASH_IN_TRANSIT', 0, 1, 1, 0, 'none'),
 (  6, '1120', 'Bank Accounts',                        'ব্যাংক হিসাবসমূহ',                    2,   'asset', 'debit', 'current_asset', 'GRP_BANK',             1, 1, 0, 0, 'none'),
 (  7, '1121', 'Main Operating Bank Account',          'প্রধান পরিচালন ব্যাংক হিসাব',          6,   'asset', 'debit', 'current_asset', 'BANK_MAIN',            0, 1, 0, 1, 'none'),
 (  8, '1122', 'COD Collection Bank Account',          'সিওডি সংগ্রহ ব্যাংক হিসাব',            6,   'asset', 'debit', 'current_asset', 'BANK_COD',             0, 1, 0, 1, 'none'),
 (  9, '1130', 'Mobile Financial Services',            'মোবাইল ফাইন্যান্সিয়াল সার্ভিস',        2,   'asset', 'debit', 'current_asset', 'GRP_MFS',              1, 1, 0, 0, 'none'),
 ( 10, '1131', 'bKash Merchant Wallet',                'বিকাশ মার্চেন্ট ওয়ালেট',              9,   'asset', 'debit', 'current_asset', 'MFS_BKASH',            0, 1, 0, 1, 'none'),
 ( 11, '1132', 'Nagad Merchant Wallet',                'নগদ মার্চেন্ট ওয়ালেট',                9,   'asset', 'debit', 'current_asset', 'MFS_NAGAD',            0, 1, 0, 1, 'none'),
 ( 12, '1140', 'Rider Cash Receivable',                'রাইডারের কাছে প্রাপ্য নগদ',            2,   'asset', 'debit', 'current_asset', 'RIDER_RECEIVABLE',     0, 1, 0, 0, 'rider'),
 ( 13, '1150', 'COD Receivable — Courier Partners',    'সিওডি প্রাপ্য — কুরিয়ার পার্টনার',      2,   'asset', 'debit', 'current_asset', 'PARTNER_COD_RECEIVABLE', 0, 1, 0, 0, 'partner'),
 ( 14, '1160', 'Accounts Receivable — Merchants',      'প্রাপ্য হিসাব — মার্চেন্ট',             2,   'asset', 'debit', 'current_asset', 'AR_MERCHANT',          0, 1, 0, 0, 'merchant'),
 ( 15, '1165', 'Accounts Receivable — Customers',      'প্রাপ্য হিসাব — গ্রাহক',               2,   'asset', 'debit', 'current_asset', 'AR_CUSTOMER',          0, 1, 0, 0, 'customer'),
 ( 16, '1170', 'Advances & Prepayments',               'অগ্রিম ও পূর্বপরিশোধ',                 2,   'asset', 'debit', 'current_asset', 'ADVANCES',             0, 1, 0, 0, 'none'),
 ( 17, '1175', 'Employee Advances',                    'কর্মচারী অগ্রিম',                      2,   'asset', 'debit', 'current_asset', 'EMPLOYEE_ADVANCE',     0, 1, 0, 0, 'employee'),
 ( 18, '1180', 'Inter-Branch Current Account',         'আন্তঃশাখা চলতি হিসাব',                 2,   'asset', 'debit', 'current_asset', 'INTER_BRANCH',         0, 1, 0, 0, 'none'),
 ( 19, '1190', 'Advance Income Tax (AIT)',             'অগ্রিম আয়কর',                          2,   'asset', 'debit', 'current_asset', 'AIT',                  0, 1, 0, 0, 'none'),
 ( 20, '1195', 'Packaging Materials Inventory',        'প্যাকেজিং সামগ্রী মজুদ',                2,   'asset', 'debit', 'current_asset', 'INVENTORY_PACKAGING',  0, 1, 0, 0, 'none'),
 ( 21, '1500', 'Fixed Assets',                         'স্থায়ী সম্পদ',                         1,   'asset', 'debit', 'fixed_asset',   'GRP_FIXED_ASSETS',     1, 1, 0, 0, 'none'),
 ( 22, '1510', 'Vehicles',                             'যানবাহন',                              21,  'asset', 'debit', 'fixed_asset',   'FA_VEHICLES',          0, 1, 0, 0, 'none'),
 ( 23, '1520', 'Office Equipment',                     'অফিস সরঞ্জাম',                         21,  'asset', 'debit', 'fixed_asset',   'FA_EQUIPMENT',         0, 1, 0, 0, 'none'),
 ( 24, '1530', 'Furniture & Fixtures',                 'আসবাবপত্র ও ফিক্সচার',                 21,  'asset', 'debit', 'fixed_asset',   'FA_FURNITURE',         0, 1, 0, 0, 'none'),
 ( 25, '1540', 'Computers & IT Equipment',             'কম্পিউটার ও আইটি সরঞ্জাম',             21,  'asset', 'debit', 'fixed_asset',   'FA_IT',                0, 1, 0, 0, 'none'),
 ( 26, '1590', 'Accumulated Depreciation',             'পুঞ্জীভূত অবচয়',                       21,  'asset', 'credit','fixed_asset',   'ACCUM_DEPRECIATION',   0, 1, 0, 0, 'none'),
 -- LIABILITIES
 ( 30, '2000', 'Liabilities',                          'দায়',                                 NULL,'liability','credit','current_liability','GRP_LIABILITIES',   1, 1, 0, 0, 'none'),
 ( 31, '2100', 'Current Liabilities',                  'চলতি দায়',                             30,  'liability','credit','current_liability','GRP_CURRENT_LIAB',  1, 1, 0, 0, 'none'),
 ( 32, '2110', 'Merchant COD Payable',                 'মার্চেন্ট সিওডি প্রদেয়',                31,  'liability','credit','current_liability','MERCHANT_COD_PAYABLE', 0, 1, 0, 0, 'merchant'),
 ( 33, '2120', 'Courier Partner Payable',              'কুরিয়ার পার্টনার প্রদেয়',               31,  'liability','credit','current_liability','PARTNER_PAYABLE',   0, 1, 0, 0, 'partner'),
 ( 34, '2130', 'Rider Commission Payable',             'রাইডার কমিশন প্রদেয়',                  31,  'liability','credit','current_liability','RIDER_COMMISSION_PAYABLE', 0, 1, 0, 0, 'rider'),
 ( 35, '2140', 'VAT Payable',                          'প্রদেয় ভ্যাট',                         31,  'liability','credit','current_liability','VAT_PAYABLE',       0, 1, 0, 0, 'none'),
 ( 36, '2150', 'Tax Deducted at Source Payable',       'উৎসে কর্তিত কর প্রদেয়',                 31,  'liability','credit','current_liability','TDS_PAYABLE',       0, 1, 0, 0, 'none'),
 ( 37, '2160', 'Salaries & Wages Payable',             'প্রদেয় বেতন ও মজুরি',                   31,  'liability','credit','current_liability','SALARY_PAYABLE',    0, 1, 0, 0, 'none'),
 ( 38, '2170', 'Accrued Expenses',                     'বকেয়া খরচ',                            31,  'liability','credit','current_liability','ACCRUED_EXPENSES',  0, 1, 0, 0, 'none'),
 ( 39, '2180', 'Customer Advance / Unearned Revenue',  'গ্রাহক অগ্রিম / অনুপার্জিত রাজস্ব',      31,  'liability','credit','current_liability','UNEARNED_REVENUE',  0, 1, 0, 0, 'none'),
 ( 40, '2190', 'Merchant Security Deposits',           'মার্চেন্ট জামানত',                      31,  'liability','credit','current_liability','MERCHANT_DEPOSIT',  0, 1, 0, 0, 'merchant'),
 ( 41, '2195', 'Parcel Damage/Loss Claims Payable',    'পার্সেল ক্ষতি/হারানো দাবি প্রদেয়',        31,  'liability','credit','current_liability','CLAIMS_PAYABLE',    0, 1, 0, 0, 'merchant'),
 ( 42, '2500', 'Long-term Liabilities',                'দীর্ঘমেয়াদি দায়',                      30,  'liability','credit','long_term_liability','GRP_LT_LIAB',     1, 1, 0, 0, 'none'),
 ( 43, '2510', 'Bank Loan',                            'ব্যাংক ঋণ',                             42,  'liability','credit','long_term_liability','BANK_LOAN',       0, 1, 0, 0, 'none'),
 -- EQUITY
 ( 50, '3000', 'Equity',                               'মালিকানা স্বত্ব',                       NULL,'equity','credit','equity',          'GRP_EQUITY',           1, 1, 0, 0, 'none'),
 ( 51, '3100', 'Owner''s Capital',                     'মালিকের মূলধন',                         50,  'equity','credit','equity',          'CAPITAL',              0, 1, 0, 0, 'none'),
 ( 52, '3200', 'Retained Earnings',                    'সংরক্ষিত আয়',                          50,  'equity','credit','equity',          'RETAINED_EARNINGS',    0, 1, 0, 0, 'none'),
 ( 53, '3300', 'Owner''s Drawings',                    'মালিকের উত্তোলন',                       50,  'equity','debit', 'equity',          'DRAWINGS',             0, 1, 0, 0, 'none'),
 ( 54, '3900', 'Opening Balance Equity',               'প্রারম্ভিক জের সমন্বয়',                  50,  'equity','credit','equity',          'OPENING_BALANCE',      0, 1, 0, 0, 'none'),
 -- INCOME
 ( 60, '4000', 'Income',                               'আয়',                                  NULL,'income','credit','operating_income','GRP_INCOME',           1, 1, 0, 0, 'none'),
 ( 61, '4100', 'Operating Revenue',                    'পরিচালন রাজস্ব',                        60,  'income','credit','operating_income','GRP_OPERATING_REV',    1, 1, 0, 0, 'none'),
 ( 62, '4110', 'Delivery Charge Revenue',              'ডেলিভারি চার্জ রাজস্ব',                  61,  'income','credit','operating_income','REV_DELIVERY',         0, 1, 0, 0, 'none'),
 ( 63, '4120', 'COD Fee Revenue',                      'সিওডি ফি রাজস্ব',                        61,  'income','credit','operating_income','REV_COD_FEE',          0, 1, 0, 0, 'none'),
 ( 64, '4130', 'Return (RTO) Charge Revenue',          'রিটার্ন চার্জ রাজস্ব',                   61,  'income','credit','operating_income','REV_RETURN',           0, 1, 0, 0, 'none'),
 ( 65, '4140', 'Packaging Fee Revenue',                'প্যাকেজিং ফি রাজস্ব',                    61,  'income','credit','operating_income','REV_PACKAGING',        0, 1, 0, 0, 'none'),
 ( 66, '4150', 'Fragile / Special Handling Revenue',   'ভঙ্গুর / বিশেষ হ্যান্ডলিং রাজস্ব',         61,  'income','credit','operating_income','REV_SURCHARGE',        0, 1, 0, 0, 'none'),
 ( 67, '4190', 'Other Operating Income',               'অন্যান্য পরিচালন আয়',                   61,  'income','credit','operating_income','REV_OTHER',            0, 1, 0, 0, 'none'),
 ( 68, '4200', 'Non-operating Income',                 'অ-পরিচালন আয়',                          60,  'income','credit','other_income',    'GRP_OTHER_INCOME',     1, 1, 0, 0, 'none'),
 ( 69, '4210', 'Bank Interest Income',                 'ব্যাংক সুদ আয়',                          68,  'income','credit','other_income',    'REV_INTEREST',         0, 1, 0, 0, 'none'),
 ( 70, '4220', 'Cash Overage',                         'নগদ উদ্বৃত্ত',                            68,  'income','credit','other_income',    'REV_CASH_OVER',        0, 1, 0, 0, 'none'),
 -- EXPENSES
 ( 80, '5000', 'Expenses',                             'ব্যয়',                                  NULL,'expense','debit','direct_expense',  'GRP_EXPENSES',         1, 1, 0, 0, 'none'),
 ( 81, '5100', 'Direct Operating Expenses',            'প্রত্যক্ষ পরিচালন ব্যয়',                 80,  'expense','debit','direct_expense',  'GRP_DIRECT_EXP',       1, 1, 0, 0, 'none'),
 ( 82, '5110', 'Rider Commission',                     'রাইডার কমিশন',                          81,  'expense','debit','direct_expense',  'EXP_RIDER_COMMISSION', 0, 1, 0, 0, 'none'),
 ( 83, '5120', 'Fuel & Line-haul Cost',                'জ্বালানি ও লাইন-হল খরচ',                 81,  'expense','debit','direct_expense',  'EXP_LINEHAUL',         0, 1, 0, 0, 'none'),
 ( 84, '5130', 'Courier Partner Charges',              'কুরিয়ার পার্টনার চার্জ',                  81,  'expense','debit','direct_expense',  'EXP_PARTNER',          0, 1, 0, 0, 'none'),
 ( 85, '5140', 'Packaging Materials Used',             'ব্যবহৃত প্যাকেজিং সামগ্রী',               81,  'expense','debit','direct_expense',  'EXP_PACKAGING',        0, 1, 0, 0, 'none'),
 ( 86, '5150', 'Lost / Damaged Parcel Compensation',   'হারানো / ক্ষতিগ্রস্ত পার্সেল ক্ষতিপূরণ',    81,  'expense','debit','direct_expense',  'EXP_COMPENSATION',     0, 1, 0, 0, 'none'),
 ( 87, '5160', 'Vehicle Maintenance',                  'যানবাহন রক্ষণাবেক্ষণ',                   81,  'expense','debit','direct_expense',  'EXP_VEHICLE',          0, 1, 0, 0, 'none'),
 ( 88, '5200', 'Administrative Expenses',              'প্রশাসনিক ব্যয়',                        80,  'expense','debit','admin_expense',   'GRP_ADMIN_EXP',        1, 1, 0, 0, 'none'),
 ( 89, '5210', 'Branch Rent',                          'শাখা ভাড়া',                             88,  'expense','debit','admin_expense',   'EXP_RENT',             0, 1, 0, 0, 'none'),
 ( 90, '5220', 'Utilities (Electricity, Water, Gas)',  'ইউটিলিটি (বিদ্যুৎ, পানি, গ্যাস)',          88,  'expense','debit','admin_expense',   'EXP_UTILITIES',        0, 1, 0, 0, 'none'),
 ( 91, '5230', 'Salaries & Wages',                     'বেতন ও মজুরি',                           88,  'expense','debit','admin_expense',   'EXP_SALARY',           0, 1, 0, 0, 'none'),
 ( 92, '5240', 'Internet & Telephone',                 'ইন্টারনেট ও টেলিফোন',                    88,  'expense','debit','admin_expense',   'EXP_TELECOM',          0, 1, 0, 0, 'none'),
 ( 93, '5250', 'SMS & Communication',                  'এসএমএস ও যোগাযোগ',                      88,  'expense','debit','admin_expense',   'EXP_SMS',              0, 1, 0, 0, 'none'),
 ( 94, '5260', 'Printing & Stationery',                'মুদ্রণ ও স্টেশনারি',                      88,  'expense','debit','admin_expense',   'EXP_STATIONERY',       0, 1, 0, 0, 'none'),
 ( 95, '5270', 'Repairs & Maintenance',                'মেরামত ও রক্ষণাবেক্ষণ',                   88,  'expense','debit','admin_expense',   'EXP_REPAIRS',          0, 1, 0, 0, 'none'),
 ( 96, '5280', 'Depreciation',                         'অবচয়',                                 88,  'expense','debit','admin_expense',   'EXP_DEPRECIATION',     0, 1, 0, 0, 'none'),
 ( 97, '5290', 'Entertainment & Office Supplies',      'আপ্যায়ন ও অফিস সামগ্রী',                 88,  'expense','debit','admin_expense',   'EXP_OFFICE',           0, 1, 0, 0, 'none'),
 ( 98, '5300', 'Financial Expenses',                   'আর্থিক ব্যয়',                           80,  'expense','debit','financial_expense','GRP_FIN_EXP',         1, 1, 0, 0, 'none'),
 ( 99, '5310', 'Bank Charges',                         'ব্যাংক চার্জ',                           98,  'expense','debit','financial_expense','EXP_BANK_CHARGES',    0, 1, 0, 0, 'none'),
 (100, '5320', 'MFS Transaction Charges',              'এমএফএস লেনদেন চার্জ',                    98,  'expense','debit','financial_expense','EXP_MFS_CHARGES',     0, 1, 0, 0, 'none'),
 (101, '5330', 'Cash Shortage',                        'নগদ ঘাটতি',                              98,  'expense','debit','financial_expense','EXP_CASH_SHORT',      0, 1, 0, 0, 'none'),
 (102, '5340', 'Discount Allowed',                     'প্রদত্ত ছাড়',                            98,  'expense','debit','financial_expense','EXP_DISCOUNT',        0, 1, 0, 0, 'none'),
 (103, '5350', 'Loan Interest',                        'ঋণের সুদ',                               98,  'expense','debit','financial_expense','EXP_INTEREST',        0, 1, 0, 0, 'none'),
 (104, '5390', 'Miscellaneous Expenses',               'বিবিধ ব্যয়',                             98,  'expense','debit','financial_expense','EXP_MISC',            0, 1, 0, 0, 'none');

-- 14.17 Fiscal year & bank ledger --------------------------------------------
INSERT INTO `fiscal_years` (`id`, `name`, `start_date`, `end_date`) VALUES
 (1, 'FY 2026-27', '2026-07-01', '2027-06-30');

INSERT INTO `bank_accounts` (`account_id`, `branch_id`, `bank_name`, `bank_branch`, `account_title`, `account_number`, `account_type`) VALUES
 (7, 1, 'Operating Bank (configure)', 'Head Office', 'Courier Management Service — Operating', 'CHANGE-ME-001', 'current'),
 (8, 1, 'Operating Bank (configure)', 'Head Office', 'Courier Management Service — COD Collection', 'CHANGE-ME-002', 'current');

-- 14.18 System settings --------------------------------------------------------
INSERT INTO `settings` (`setting_group`, `setting_key`, `setting_value`, `value_type`, `is_public`, `description`) VALUES
 ('company',   'company_name_en',           'Courier Management Service',  'string',  1, 'Company name (English)'),
 ('company',   'company_name_bn',           'কুরিয়ার ম্যানেজমেন্ট সার্ভিস', 'string',  1, 'Company name (Bangla)'),
 ('company',   'company_address',           'Tejgaon Industrial Area, Dhaka 1208, Bangladesh', 'string', 1, 'Printed on labels and invoices'),
 ('company',   'company_phone',             '01700000001',                 'string',  1, 'Hotline'),
 ('company',   'company_email',             'support@courier.local',       'string',  1, 'Support email'),
 ('company',   'company_bin',               '',                            'string',  1, 'VAT BIN printed on invoices'),
 ('localization','default_locale',          'bn',                          'string',  1, 'bn or en'),
 ('localization','date_format',             'd/m/Y',                       'string',  1, 'PHP date format for display'),
 ('localization','currency_code',           'BDT',                         'string',  1, NULL),
 ('localization','currency_symbol',         '৳',                           'string',  1, NULL),
 ('tax',       'vat_percent',               '15.00',                       'decimal', 0, 'VAT on courier service charges'),
 ('tax',       'vat_inclusive',             '0',                           'bool',    0, '1 = tariff prices already include VAT'),
 ('booking',   'tracking_prefix',           'CMS',                         'string',  0, 'AWB prefix: CMS-YYYYMMDD-XXXXXX'),
 ('booking',   'default_rate_card_id',      '1',                           'int',     0, 'Fallback tariff'),
 ('booking',   'volumetric_divisor',        '5000',                        'int',     0, 'cm³ per kg'),
 ('booking',   'max_cod_amount',            '100000',                      'decimal', 0, 'Upper limit per parcel'),
 ('booking',   'label_size',                '4x6',                         'string',  0, 'Thermal label size in inches'),
 ('booking',   'bulk_upload_max_rows',      '1000',                        'int',     0, 'Rows per CSV'),
 ('delivery',  'max_delivery_attempts',     '3',                           'int',     0, 'Auto-RTO after this many failed attempts'),
 ('delivery',  'otp_required',              '1',                           'bool',    0, 'Require receiver OTP for POD'),
 ('delivery',  'otp_length',                '6',                           'int',     0, NULL),
 ('delivery',  'otp_expiry_minutes',        '1440',                        'int',     0, 'OTP validity after out-for-delivery'),
 ('delivery',  'pod_photo_required',        '0',                           'bool',    0, NULL),
 ('delivery',  'hold_max_days',             '7',                           'int',     0, 'Max days a parcel may stay on hold'),
 ('cash',      'rider_default_cash_limit',  '20000',                       'decimal', 0, 'Default COD carry limit per rider'),
 ('cash',      'handover_tolerance',        '0.00',                        'decimal', 0, 'Allowed shortage before dispute'),
 ('settlement','settlement_hold_days',      '1',                           'int',     0, 'Days after delivery before COD is payable'),
 ('settlement','min_payout_amount',         '100',                         'decimal', 0, NULL),
 ('accounting','fiscal_year_start_month',   '7',                           'int',     0, 'Bangladesh FY starts in July'),
 ('accounting','auto_post_operational_journals', '1',                      'bool',    0, 'Post booking/delivery journals immediately'),
 ('security',  'session_idle_minutes',      '30',                          'int',     0, NULL),
 ('security',  'password_expiry_days',      '0',                           'int',     0, '0 = never'),
 ('sms',       'sms_enabled',               '0',                           'bool',    0, 'Enable outbound SMS'),
 ('sms',       'sms_provider',              '',                            'string',  0, 'Gateway driver name');

-- 14.19 Document number sequences ---------------------------------------------
INSERT INTO `number_sequences` (`seq_key`, `seq_period`, `last_value`) VALUES
 ('merchant', 'ALL', 0),
 ('rider',    'ALL', 0);

-- 14.20 Super Admin -----------------------------------------------------------------
-- Login: admin@courier.local  /  Admin@12345   (bcrypt; must change on first login,
-- transparently re-hashed to Argon2id by the application).
INSERT INTO `users` (`id`, `role_id`, `branch_id`, `employee_code`, `name`, `name_bn`, `email`, `phone`, `password_hash`, `locale`, `status`, `must_change_password`, `password_changed_at`) VALUES
 (1, 1, 1, 'EMP-0001', 'System Administrator', 'সিস্টেম অ্যাডমিনিস্ট্রেটর', 'admin@courier.local', '01700000000',
  '$2y$12$bRqWOBiWF2udP8gJOR/ds.O5TpKQL.A67KTz6vZoSpp6S7zALqXBu', 'bn', 'active', 1, NOW());

UPDATE `branches` SET `manager_user_id` = 1 WHERE `id` = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  END OF SCHEMA
-- =====================================================================
