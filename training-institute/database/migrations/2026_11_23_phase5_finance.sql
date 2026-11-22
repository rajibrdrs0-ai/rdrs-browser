-- Upgrade an existing RTTI database (installed before phase 5) to the phase 5 schema.
-- Run once:  mysql -u root -p rtti_management < database/migrations/2026_11_23_phase5_finance.sql
-- Take a backup first (deploy/backup.sh). New installs do not need this file.
ALTER TABLE `fee_waivers`    ADD COLUMN `decision_note` VARCHAR(255) NULL AFTER `approved_at`;
ALTER TABLE `refunds`        ADD COLUMN `paid_by` INT UNSIGNED NULL AFTER `paid_at`,
                             ADD COLUMN `decision_note` VARCHAR(255) NULL AFTER `paid_by`,
                             ADD KEY `idx_refunds_enrollment` (`enrollment_id`, `status`),
                             ADD CONSTRAINT `fk_rf_payer` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE `expenses`       ADD COLUMN `decision_note` VARCHAR(255) NULL AFTER `approved_at`;
ALTER TABLE `fund_transfers` DROP FOREIGN KEY `fk_ft_creator`, DROP FOREIGN KEY `fk_ft_confirmer`;
ALTER TABLE `fund_transfers` ADD COLUMN `confirmed_at` DATETIME NULL AFTER `confirmed_by`,
                             ADD CONSTRAINT `fk_ft_creator`   FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
                             ADD CONSTRAINT `fk_ft_confirmer` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
                             ADD CONSTRAINT `chk_ft_maker_checker` CHECK (`confirmed_by` IS NULL OR `confirmed_by` <> `created_by`);

-- Phase 4 view change (dues exclude cancelled installments)
CREATE OR REPLACE VIEW `v_enrollment_dues` AS
SELECT x.enrollment_id, x.center_id, x.batch_id, x.student_id, x.registration_no, x.name_en, x.mobile,
       x.net_fee, x.invoiced, x.paid, x.invoiced - x.paid AS due, x.overdue
  FROM (
    SELECT e.id AS enrollment_id, e.center_id, e.batch_id, e.student_id, s.registration_no, s.name_en, s.mobile, e.net_fee,
           (SELECT COALESCE(SUM(i.amount), 0) FROM fee_invoices i WHERE i.enrollment_id = e.id AND i.status <> 'cancelled') AS invoiced,
           (SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id
             WHERE i.enrollment_id = e.id AND p.status = 'valid') AS paid,
           (SELECT COALESCE(SUM(i.amount - i.paid_amount), 0) FROM fee_invoices i
             WHERE i.enrollment_id = e.id AND i.status IN ('unpaid','partial') AND i.due_date < CURRENT_DATE) AS overdue
      FROM enrollments e JOIN students s ON s.id = e.student_id
     WHERE e.status <> 'cancelled'
  ) x;
