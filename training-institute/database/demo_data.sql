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

-- ---- Phase 2 demo: courses, curriculum, fees, rooms, staff -------------------
INSERT INTO `courses` (`id`, `code`, `trade_id`, `name_en`, `name_bn`, `course_type`, `nsqf_level`, `duration_months`, `total_hours`, `min_qualification`, `min_age`, `max_age`) VALUES
 (101, 'ICT-COA', (SELECT id FROM trades WHERE code='ICT'),  'Computer Office Application', 'কম্পিউটার অফিস অ্যাপ্লিকেশন', 'nsda_cbt', 2, 3.0, 360, 'SSC', 16, 45),
 (102, 'ELEC-HW', (SELECT id FROM trades WHERE code='ELEC'), 'Electrical House Wiring',     'ইলেকট্রিক্যাল হাউস ওয়্যারিং',    'nsda_cbt', 1, 3.0, 360, 'Class 8', 16, 45),
 (103, 'GARM-DM', (SELECT id FROM trades WHERE code='GARM'), 'Dress Making & Tailoring',    'ড্রেস মেকিং ও টেইলারিং',          'short',    NULL, 2.0, 240, NULL, 15, 50);

INSERT INTO `course_modules` (`course_id`, `sequence_no`, `title_en`, `title_bn`, `theory_hours`, `practical_hours`) VALUES
 (101, 1, 'Computer fundamentals & OS', 'কম্পিউটার পরিচিতি ও অপারেটিং সিস্টেম', 20, 40),
 (101, 2, 'Word processing (MS Word / Bangla typing)', 'ওয়ার্ড প্রসেসিং ও বাংলা টাইপিং', 20, 70),
 (101, 3, 'Spreadsheet (MS Excel)', 'স্প্রেডশিট (এমএস এক্সেল)', 20, 70),
 (101, 4, 'Presentation, internet & e-mail', 'প্রেজেন্টেশন, ইন্টারনেট ও ই-মেইল', 20, 60),
 (101, 5, 'Workplace communication & safety', 'কর্মক্ষেত্রে যোগাযোগ ও নিরাপত্তা', 20, 20),
 (102, 1, 'Electrical safety & tools', 'বৈদ্যুতিক নিরাপত্তা ও যন্ত্রপাতি', 30, 30),
 (102, 2, 'House wiring circuits', 'হাউস ওয়্যারিং সার্কিট', 30, 150),
 (102, 3, 'Earthing & fault finding', 'আর্থিং ও ত্রুটি নির্ণয়', 20, 100);

INSERT INTO `course_fees` (`course_id`, `fee_head_id`, `center_id`, `amount`, `is_optional`, `effective_from`) VALUES
 (101, (SELECT id FROM fee_heads WHERE code='ADMISSION'),   NULL, 500.00,  0, '2026-07-01'),
 (101, (SELECT id FROM fee_heads WHERE code='COURSE'),      NULL, 6000.00, 0, '2026-07-01'),
 (101, (SELECT id FROM fee_heads WHERE code='EXAM'),        NULL, 500.00,  0, '2026-07-01'),
 (101, (SELECT id FROM fee_heads WHERE code='RESIDENTIAL'), NULL, 3000.00, 1, '2026-07-01'),
 (101, (SELECT id FROM fee_heads WHERE code='COURSE'),      103,  5000.00, 0, '2026-07-01'),
 (102, (SELECT id FROM fee_heads WHERE code='ADMISSION'),   NULL, 500.00,  0, '2026-07-01'),
 (102, (SELECT id FROM fee_heads WHERE code='COURSE'),      NULL, 7000.00, 0, '2026-07-01'),
 (102, (SELECT id FROM fee_heads WHERE code='MATERIAL'),    NULL, 1500.00, 0, '2026-07-01');

INSERT INTO `rooms` (`center_id`, `name`, `room_type`, `capacity`) VALUES
 (101, 'Computer Lab 1', 'lab', 25), (101, 'Computer Lab 2', 'lab', 25), (101, 'Electrical Workshop', 'workshop', 20),
 (101, 'Classroom A', 'classroom', 40), (103, 'Computer Lab', 'lab', 20), (103, 'Tailoring Room', 'workshop', 20);

INSERT INTO `staff` (`id`, `employee_code`, `center_id`, `designation_id`, `name`, `name_bn`, `gender`, `mobile`, `employment_type`, `joining_date`, `status`) VALUES
 (101, 'RNG-0001', 101, (SELECT id FROM designations WHERE name_en='Senior Trainer'), 'Abdul Karim', 'আবদুল করিম', 'male',   '01720000101', 'permanent',   '2024-01-15', 'active'),
 (102, 'RNG-0002', 101, (SELECT id FROM designations WHERE name_en='Trainer'),        'Nasrin Akter', 'নাসরিন আক্তার', 'female', '01720000102', 'contractual', '2025-03-01', 'active'),
 (103, 'RNG-0003', 101, (SELECT id FROM designations WHERE name_en='Accountant'),     'Rafiqul Islam', 'রফিকুল ইসলাম', 'male',  '01720000103', 'permanent',   '2023-07-01', 'active'),
 (104, 'KUR-0001', 103, (SELECT id FROM designations WHERE name_en='Trainer'),        'Shahana Begum', 'শাহানা বেগম', 'female', '01720000104', 'permanent',   '2024-05-10', 'active');
INSERT INTO `number_sequences` (`seq_key`, `seq_period`, `last_value`) VALUES ('staff', 'RNG', 3), ('staff', 'KUR', 1);

-- Link the demo trainer login to trainer Nasrin Akter.
UPDATE `users` SET `staff_id` = 102 WHERE `username` = 'trainer.rangpur';

-- ---- Phase 4 demo: trainees and a batch open for admission ------------------------
INSERT INTO `students` (`id`, `registration_no`, `center_id`, `name_en`, `name_bn`, `gender`, `date_of_birth`, `nid_no`, `birth_reg_no`, `mobile`,
                        `father_name`, `father_mobile`, `mother_name`, `permanent_upazila_id`, `permanent_address`, `highest_education`, `disability`,
                        `employment_status_before`, `household_income_monthly`) VALUES
 (101, 'RNG-2026-00001', 101, 'Md. Rakib Hasan',  'মোঃ রাকিব হাসান',  'male',   '2003-04-12', '4612345678', NULL, '01810000101', 'Abdul Hamid', '01810000201', 'Rokeya Begum', (SELECT u.id FROM upazilas u JOIN districts d ON d.id=u.district_id WHERE d.name_en='Rangpur'), 'Village Lalbag', 'HSC', 'none', 'unemployed', 12000),
 (102, 'RNG-2026-00002', 101, 'Sumaiya Akter',    'সুমাইয়া আক্তার',   'female', '2004-09-03', '4612345679', NULL, '01810000102', 'Mofizul Islam', NULL, 'Shirin Akter', (SELECT u.id FROM upazilas u JOIN districts d ON d.id=u.district_id WHERE d.name_en='Rangpur'), 'Village Kamal Kachna', 'SSC', 'none', 'student', 9000),
 (103, 'RNG-2026-00003', 101, 'Jahid Islam',      'জাহিদ ইসলাম',     'male',   '2001-01-20', '4612345680', NULL, '01810000103', 'Nurul Islam', NULL, 'Hasina Begum', NULL, NULL, 'SSC', 'physical', 'unemployed', 7000),
 (104, 'RNG-2026-00004', 101, 'Moushumi Rani',    'মৌসুমী রানী',      'female', '2002-06-15', '4612345681', NULL, '01810000104', 'Sukumar Roy', NULL, 'Anjali Rani', NULL, NULL, 'HSC', 'none', 'self_employed', 15000),
 (105, 'RNG-2026-00005', 101, 'Shafiqul Alam',    'শফিকুল আলম',      'male',   '1999-11-30', '4612345682', NULL, '01810000105', 'Rahmat Ali', NULL, 'Jahanara Begum', NULL, NULL, 'Degree', 'none', 'unemployed', 11000),
 (106, 'RNG-2026-00006', 101, 'Tania Khatun',     'তানিয়া খাতুন',     'female', '2011-02-10', NULL, '20111234567890123', '01810000106', 'Abul Kalam', '01810000206', 'Morjina Begum', NULL, NULL, 'Class 8', 'none', 'student', 6000);
INSERT INTO `number_sequences` (`seq_key`, `seq_period`, `last_value`) VALUES ('student:RNG', '2026', 6);

INSERT INTO `batches` (`id`, `batch_code`, `center_id`, `course_id`, `name`, `shift`, `start_date`, `end_date`, `class_start_time`, `class_end_time`,
                       `class_days`, `capacity`, `room_id`, `lead_trainer_id`, `funding_source`, `status`) VALUES
 (101, 'RNG-ICT-COA-2026-90', 101, 101, 'November Morning (demo)', 'morning', '2026-11-01', '2027-01-31', '09:00:00', '13:00:00',
  'sat,sun,mon,tue,wed,thu', 25, (SELECT id FROM rooms WHERE center_id=101 AND name='Computer Lab 1'), 101, 'Self-financed', 'admission_open');
INSERT INTO `batch_trainers` (`batch_id`, `staff_id`, `role`) VALUES (101, 101, 'lead'), (101, 102, 'assistant');
INSERT INTO `class_routines` (`batch_id`, `weekday`, `start_time`, `end_time`, `module_id`, `staff_id`, `room_id`, `session_type`)
SELECT 101, d.day, '09:00:00', '11:00:00', (SELECT id FROM course_modules WHERE course_id=101 AND sequence_no=1), 101, (SELECT id FROM rooms WHERE center_id=101 AND name='Computer Lab 1'), 'both'
  FROM (SELECT 'sat' AS day UNION ALL SELECT 'sun' UNION ALL SELECT 'mon' UNION ALL SELECT 'tue' UNION ALL SELECT 'wed' UNION ALL SELECT 'thu') d;
INSERT INTO `class_routines` (`batch_id`, `weekday`, `start_time`, `end_time`, `module_id`, `staff_id`, `room_id`, `session_type`)
SELECT 101, d.day, '11:00:00', '13:00:00', (SELECT id FROM course_modules WHERE course_id=101 AND sequence_no=2), 102, (SELECT id FROM rooms WHERE center_id=101 AND name='Computer Lab 1'), 'practical'
  FROM (SELECT 'sat' AS day UNION ALL SELECT 'sun' UNION ALL SELECT 'mon' UNION ALL SELECT 'tue' UNION ALL SELECT 'wed' UNION ALL SELECT 'thu') d;

-- ---- Phase 5 demo: more money accounts --------------------------------------------
INSERT INTO `finance_accounts` (`center_id`, `account_type`, `name`, `bank_name`, `account_number`) VALUES
 (101, 'mobile_wallet', 'Rangpur bKash Merchant', 'bKash', '01710000999'),
 (101, 'bank', 'Rangpur Bank Account', 'Demo Bank, Rangpur Branch', '0000-DEMO-101'),
 (1,   'bank', 'Head Office Bank Account', 'Demo Bank, Head Office', '0000-DEMO-001');
