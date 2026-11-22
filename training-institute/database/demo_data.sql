-- =====================================================================
--  OPTIONAL DEMO DATA — for training / testing only. Do NOT load in production.
--  mysql -u root -p rtti_management < database/demo_data.sql
--  All demo users: password  Demo@12345
-- =====================================================================
USE `rtti_management`;
SET NAMES utf8mb4;

INSERT INTO `training_centers` (`id`, `code`, `name_en`, `name_bn`, `center_type`, `district_id`, `upazila_id`, `address`, `phone`, `seat_capacity`, `has_hostel`) VALUES
 (101, 'RNG', 'Rangpur Training Center',   'রংপুর প্রশিক্ষণ কেন্দ্র',   'training_center', (SELECT id FROM districts WHERE name_en='Rangpur'),   (SELECT u.id FROM upazilas u JOIN districts d ON d.id=u.district_id WHERE d.name_en='Rangpur'),   'Demo address, Rangpur',   '01700000101', 120, 1),
 (102, 'MTN', 'Mithapukur Training Center','মিঠাপুকুর প্রশিক্ষণ কেন্দ্র','training_center', (SELECT id FROM districts WHERE name_en='Rangpur'),   NULL,                                                                                               'Demo address, Mithapukur','01700000102',  60, 0),
 (103, 'KUR', 'Kurigram Training Center',  'কুড়িগ্রাম প্রশিক্ষণ কেন্দ্র','training_center', (SELECT id FROM districts WHERE name_en='Kurigram'),  (SELECT u.id FROM upazilas u JOIN districts d ON d.id=u.district_id WHERE d.name_en='Kurigram'),  'Demo address, Kurigram',  '01700000103',  80, 1);

INSERT INTO `users` (`role_id`, `center_id`, `district_id`, `name`, `name_bn`, `username`, `mobile`, `password_hash`, `must_change_password`) VALUES
 (2, 1,   NULL, 'Head Office Admin (demo)',     'প্রধান কার্যালয় অ্যাডমিন (ডেমো)', 'hoadmin',      '01710000002', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (3, 1,   NULL, 'Exam Controller (demo)',       'পরীক্ষা নিয়ন্ত্রক (ডেমো)',         'exam',         '01710000003', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (4, 1,   NULL, 'Auditor (demo)',               'অডিটর (ডেমো)',                    'auditor',      '01710000004', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (5, NULL,(SELECT id FROM districts WHERE name_en='Rangpur'), 'Rangpur District Coordinator (demo)', 'রংপুর জেলা সমন্বয়কারী (ডেমো)', 'dc.rangpur', '01710000005', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (6, 101, NULL, 'Rangpur Center Manager (demo)','রংপুর কেন্দ্র ব্যবস্থাপক (ডেমো)',  'mgr.rangpur',  '01710000006', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (7, 101, NULL, 'Rangpur Accountant (demo)',    'রংপুর হিসাবরক্ষক (ডেমো)',          'acc.rangpur',  '01710000007', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (8, 101, NULL, 'Rangpur Admission Officer (demo)','রংপুর ভর্তি কর্মকর্তা (ডেমো)',  'adm.rangpur',  '01710000008', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (9, 101, NULL, 'Rangpur Trainer (demo)',       'রংপুর প্রশিক্ষক (ডেমো)',           'trainer.rangpur','01710000009', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0),
 (6, 103, NULL, 'Kurigram Center Manager (demo)','কুড়িগ্রাম কেন্দ্র ব্যবস্থাপক (ডেমো)', 'mgr.kurigram', '01710000010', '$2y$12$ZYvondfyKhFc90pvdzXdUOIuvwPANexA.F6qxyzDZ1K613rAhm6e6', 0);

INSERT INTO `finance_accounts` (`center_id`, `account_type`, `name`) VALUES
 (101, 'cash', 'Rangpur Cash'), (102, 'cash', 'Mithapukur Cash'), (103, 'cash', 'Kurigram Cash');
