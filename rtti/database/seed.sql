-- RTTI seed: reference / master data (run after schema.sql)
SET NAMES utf8mb4;

-- Location hierarchy: all 8 divisions and 64 districts of Bangladesh
INSERT INTO divisions (id,name,name_bn,code) VALUES (1,'Barishal','বরিশাল','BAR');
INSERT INTO districts (division_id,name) VALUES (1,'Barguna');
INSERT INTO districts (division_id,name) VALUES (1,'Barishal');
INSERT INTO districts (division_id,name) VALUES (1,'Bhola');
INSERT INTO districts (division_id,name) VALUES (1,'Jhalokati');
INSERT INTO districts (division_id,name) VALUES (1,'Patuakhali');
INSERT INTO districts (division_id,name) VALUES (1,'Pirojpur');
INSERT INTO divisions (id,name,name_bn,code) VALUES (2,'Chattogram','চট্টগ্রাম','CHA');
INSERT INTO districts (division_id,name) VALUES (2,'Bandarban');
INSERT INTO districts (division_id,name) VALUES (2,'Brahmanbaria');
INSERT INTO districts (division_id,name) VALUES (2,'Chandpur');
INSERT INTO districts (division_id,name) VALUES (2,'Chattogram');
INSERT INTO districts (division_id,name) VALUES (2,'Cox''s Bazar');
INSERT INTO districts (division_id,name) VALUES (2,'Cumilla');
INSERT INTO districts (division_id,name) VALUES (2,'Feni');
INSERT INTO districts (division_id,name) VALUES (2,'Khagrachhari');
INSERT INTO districts (division_id,name) VALUES (2,'Lakshmipur');
INSERT INTO districts (division_id,name) VALUES (2,'Noakhali');
INSERT INTO districts (division_id,name) VALUES (2,'Rangamati');
INSERT INTO divisions (id,name,name_bn,code) VALUES (3,'Dhaka','ঢাকা','DHA');
INSERT INTO districts (division_id,name) VALUES (3,'Dhaka');
INSERT INTO districts (division_id,name) VALUES (3,'Faridpur');
INSERT INTO districts (division_id,name) VALUES (3,'Gazipur');
INSERT INTO districts (division_id,name) VALUES (3,'Gopalganj');
INSERT INTO districts (division_id,name) VALUES (3,'Kishoreganj');
INSERT INTO districts (division_id,name) VALUES (3,'Madaripur');
INSERT INTO districts (division_id,name) VALUES (3,'Manikganj');
INSERT INTO districts (division_id,name) VALUES (3,'Munshiganj');
INSERT INTO districts (division_id,name) VALUES (3,'Narayanganj');
INSERT INTO districts (division_id,name) VALUES (3,'Narsingdi');
INSERT INTO districts (division_id,name) VALUES (3,'Rajbari');
INSERT INTO districts (division_id,name) VALUES (3,'Shariatpur');
INSERT INTO districts (division_id,name) VALUES (3,'Tangail');
INSERT INTO divisions (id,name,name_bn,code) VALUES (4,'Khulna','খুলনা','KHU');
INSERT INTO districts (division_id,name) VALUES (4,'Bagerhat');
INSERT INTO districts (division_id,name) VALUES (4,'Chuadanga');
INSERT INTO districts (division_id,name) VALUES (4,'Jashore');
INSERT INTO districts (division_id,name) VALUES (4,'Jhenaidah');
INSERT INTO districts (division_id,name) VALUES (4,'Khulna');
INSERT INTO districts (division_id,name) VALUES (4,'Kushtia');
INSERT INTO districts (division_id,name) VALUES (4,'Magura');
INSERT INTO districts (division_id,name) VALUES (4,'Meherpur');
INSERT INTO districts (division_id,name) VALUES (4,'Narail');
INSERT INTO districts (division_id,name) VALUES (4,'Satkhira');
INSERT INTO divisions (id,name,name_bn,code) VALUES (5,'Mymensingh','ময়মনসিংহ','MYM');
INSERT INTO districts (division_id,name) VALUES (5,'Jamalpur');
INSERT INTO districts (division_id,name) VALUES (5,'Mymensingh');
INSERT INTO districts (division_id,name) VALUES (5,'Netrokona');
INSERT INTO districts (division_id,name) VALUES (5,'Sherpur');
INSERT INTO divisions (id,name,name_bn,code) VALUES (6,'Rajshahi','রাজশাহী','RAJ');
INSERT INTO districts (division_id,name) VALUES (6,'Bogura');
INSERT INTO districts (division_id,name) VALUES (6,'Chapai Nawabganj');
INSERT INTO districts (division_id,name) VALUES (6,'Joypurhat');
INSERT INTO districts (division_id,name) VALUES (6,'Naogaon');
INSERT INTO districts (division_id,name) VALUES (6,'Natore');
INSERT INTO districts (division_id,name) VALUES (6,'Pabna');
INSERT INTO districts (division_id,name) VALUES (6,'Rajshahi');
INSERT INTO districts (division_id,name) VALUES (6,'Sirajganj');
INSERT INTO divisions (id,name,name_bn,code) VALUES (7,'Rangpur','রংপুর','RAN');
INSERT INTO districts (division_id,name) VALUES (7,'Dinajpur');
INSERT INTO districts (division_id,name) VALUES (7,'Gaibandha');
INSERT INTO districts (division_id,name) VALUES (7,'Kurigram');
INSERT INTO districts (division_id,name) VALUES (7,'Lalmonirhat');
INSERT INTO districts (division_id,name) VALUES (7,'Nilphamari');
INSERT INTO districts (division_id,name) VALUES (7,'Panchagarh');
INSERT INTO districts (division_id,name) VALUES (7,'Rangpur');
INSERT INTO districts (division_id,name) VALUES (7,'Thakurgaon');
INSERT INTO divisions (id,name,name_bn,code) VALUES (8,'Sylhet','সিলেট','SYL');
INSERT INTO districts (division_id,name) VALUES (8,'Habiganj');
INSERT INTO districts (division_id,name) VALUES (8,'Moulvibazar');
INSERT INTO districts (division_id,name) VALUES (8,'Sunamganj');
INSERT INTO districts (division_id,name) VALUES (8,'Sylhet');

-- Upazilas (Rangpur division + Dhaka sample). Add more from Master Entry > Location.
INSERT INTO upazilas (district_id,name) SELECT id,'Rangpur Sadar' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Badarganj' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Gangachara' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Kaunia' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Mithapukur' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Pirgachha' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Pirganj' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Taraganj' FROM districts WHERE name='Rangpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Kurigram Sadar' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Bhurungamari' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Char Rajibpur' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Chilmari' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Phulbari' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Nageshwari' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Rajarhat' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Raomari' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Ulipur' FROM districts WHERE name='Kurigram';
INSERT INTO upazilas (district_id,name) SELECT id,'Lalmonirhat Sadar' FROM districts WHERE name='Lalmonirhat';
INSERT INTO upazilas (district_id,name) SELECT id,'Aditmari' FROM districts WHERE name='Lalmonirhat';
INSERT INTO upazilas (district_id,name) SELECT id,'Hatibandha' FROM districts WHERE name='Lalmonirhat';
INSERT INTO upazilas (district_id,name) SELECT id,'Kaliganj' FROM districts WHERE name='Lalmonirhat';
INSERT INTO upazilas (district_id,name) SELECT id,'Patgram' FROM districts WHERE name='Lalmonirhat';
INSERT INTO upazilas (district_id,name) SELECT id,'Nilphamari Sadar' FROM districts WHERE name='Nilphamari';
INSERT INTO upazilas (district_id,name) SELECT id,'Dimla' FROM districts WHERE name='Nilphamari';
INSERT INTO upazilas (district_id,name) SELECT id,'Domar' FROM districts WHERE name='Nilphamari';
INSERT INTO upazilas (district_id,name) SELECT id,'Jaldhaka' FROM districts WHERE name='Nilphamari';
INSERT INTO upazilas (district_id,name) SELECT id,'Kishoreganj' FROM districts WHERE name='Nilphamari';
INSERT INTO upazilas (district_id,name) SELECT id,'Saidpur' FROM districts WHERE name='Nilphamari';
INSERT INTO upazilas (district_id,name) SELECT id,'Gaibandha Sadar' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Fulchhari' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Gobindaganj' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Palashbari' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Sadullapur' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Saghata' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Sundarganj' FROM districts WHERE name='Gaibandha';
INSERT INTO upazilas (district_id,name) SELECT id,'Dinajpur Sadar' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Birampur' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Birganj' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Biral' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Bochaganj' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Chirirbandar' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Phulbari' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Ghoraghat' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Hakimpur' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Kaharole' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Khansama' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Nawabganj' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Parbatipur' FROM districts WHERE name='Dinajpur';
INSERT INTO upazilas (district_id,name) SELECT id,'Panchagarh Sadar' FROM districts WHERE name='Panchagarh';
INSERT INTO upazilas (district_id,name) SELECT id,'Atwari' FROM districts WHERE name='Panchagarh';
INSERT INTO upazilas (district_id,name) SELECT id,'Boda' FROM districts WHERE name='Panchagarh';
INSERT INTO upazilas (district_id,name) SELECT id,'Debiganj' FROM districts WHERE name='Panchagarh';
INSERT INTO upazilas (district_id,name) SELECT id,'Tetulia' FROM districts WHERE name='Panchagarh';
INSERT INTO upazilas (district_id,name) SELECT id,'Thakurgaon Sadar' FROM districts WHERE name='Thakurgaon';
INSERT INTO upazilas (district_id,name) SELECT id,'Baliadangi' FROM districts WHERE name='Thakurgaon';
INSERT INTO upazilas (district_id,name) SELECT id,'Haripur' FROM districts WHERE name='Thakurgaon';
INSERT INTO upazilas (district_id,name) SELECT id,'Pirganj' FROM districts WHERE name='Thakurgaon';
INSERT INTO upazilas (district_id,name) SELECT id,'Ranisankail' FROM districts WHERE name='Thakurgaon';
INSERT INTO upazilas (district_id,name) SELECT id,'Savar' FROM districts WHERE name='Dhaka';
INSERT INTO upazilas (district_id,name) SELECT id,'Dhamrai' FROM districts WHERE name='Dhaka';
INSERT INTO upazilas (district_id,name) SELECT id,'Keraniganj' FROM districts WHERE name='Dhaka';
INSERT INTO upazilas (district_id,name) SELECT id,'Nawabganj' FROM districts WHERE name='Dhaka';
INSERT INTO upazilas (district_id,name) SELECT id,'Dohar' FROM districts WHERE name='Dhaka';

-- Gender
INSERT INTO genders (name,name_bn) VALUES ('Male','পুরুষ'),('Female','নারী'),('Other','অন্যান্য');

-- Academic master
INSERT INTO education_levels (name,sort_order) VALUES
 ('No formal education',0),('Primary (PSC)',1),('Junior (JSC/JDC)',2),('SSC / Dakhil',3),('HSC / Alim',4),
 ('Diploma',5),('Bachelor (Honours/Pass)',6),('Masters',7);
INSERT INTO qualifications (education_level_id,name) SELECT id,'SSC – Science' FROM education_levels WHERE name='SSC / Dakhil';
INSERT INTO qualifications (education_level_id,name) SELECT id,'SSC – Humanities' FROM education_levels WHERE name='SSC / Dakhil';
INSERT INTO qualifications (education_level_id,name) SELECT id,'SSC – Business Studies' FROM education_levels WHERE name='SSC / Dakhil';
INSERT INTO qualifications (education_level_id,name) SELECT id,'HSC – Science' FROM education_levels WHERE name='HSC / Alim';
INSERT INTO qualifications (education_level_id,name) SELECT id,'Diploma in Engineering' FROM education_levels WHERE name='Diploma';
INSERT INTO academic_categories (name,description) VALUES
 ('Short Course','Up to 3 months'),('Long Course','More than 3 months'),('NTVQF / BTEB Certified','Nationally certified skill course'),('Project Sponsored','Donor / project funded training');
INSERT INTO academic_statuses (name) VALUES ('Regular'),('Irregular'),('Dropped Out'),('Graduated');

-- Trades (configurable)
INSERT INTO trades (code,name,name_bn) VALUES
 ('COM','Computer','কম্পিউটার'),('ELE','Electrical','ইলেকট্রিক্যাল'),('ETR','Electronics','ইলেকট্রনিক্স'),
 ('SEW','Sewing','সেলাই'),('TAI','Tailoring','দর্জি'),('AGR','Agriculture','কৃষি'),('LIV','Livestock','প্রাণিসম্পদ'),('OTH','Other','অন্যান্য');

-- Status masters (code is used by business logic, name/colour are editable)
INSERT INTO course_statuses (code,name,color) VALUES ('ACTIVE','Active','success'),('INACTIVE','Inactive','secondary'),('ARCHIVED','Archived','dark');
INSERT INTO batch_statuses (code,name,color,allows_enrollment) VALUES
 ('UPCOMING','Upcoming','info',1),('RUNNING','Running','success',1),('COMPLETED','Completed','primary',0),
 ('CANCELLED','Cancelled','danger',0),('ON_HOLD','On Hold','warning',0);

INSERT INTO designations (name) VALUES ('Principal'),('Center Manager'),('Senior Trainer'),('Trainer'),('Assistant Trainer'),('Accounts Officer'),('Admission Officer'),('Office Assistant'),('Data Entry Operator'),('Guard');
INSERT INTO departments (name) VALUES ('Administration'),('Academic'),('Accounts'),('IT'),('Support');
INSERT INTO document_categories (name) VALUES ('Photograph'),('NID'),('Birth Certificate'),('Educational Certificate'),('Admission Document'),('Other');
INSERT INTO grade_scales (grade,min_percent,max_percent,grade_point,is_pass) VALUES
 ('A+',80,100,5.00,1),('A',70,79.99,4.00,1),('A-',60,69.99,3.50,1),('B',50,59.99,3.00,1),('C',40,49.99,2.00,1),('F',0,39.99,0.00,0);

-- Accounts master
INSERT INTO payment_methods (name,requires_reference) VALUES ('Cash',0),('bKash',1),('Nagad',1),('Rocket',1),('Bank Deposit',1),('Cheque',1);
INSERT INTO banks (name) VALUES ('Sonali Bank PLC'),('Janata Bank PLC'),('Agrani Bank PLC'),('BRAC Bank PLC'),('Dutch-Bangla Bank PLC');
INSERT INTO account_heads (code,name,head_type) VALUES
 ('4001','Course Fee Income','income'),('4002','Admission / Form Fee','income'),('4003','Other Income','income'),
 ('5001','Salary & Allowances','expense'),('5002','Rent & Utilities','expense'),('5003','Training Materials','expense'),
 ('5004','Repair & Maintenance','expense'),('5005','Travel & Conveyance','expense'),('5006','Office Supplies','expense'),('5099','Miscellaneous','expense'),
 ('1001','Cash in Hand','asset'),('1002','Cash at Bank','asset');
INSERT INTO income_categories (name) VALUES ('Course Fee'),('Admission Fee'),('Exam Fee'),('Certificate Fee'),('Other');
INSERT INTO expense_categories (name) VALUES ('Administrative'),('Academic'),('Utilities'),('Maintenance'),('Others');
INSERT INTO expense_heads (expense_category_id,account_head_id,name) VALUES
 (1,(SELECT id FROM account_heads WHERE code='5001'),'Staff Salary'),
 (1,(SELECT id FROM account_heads WHERE code='5006'),'Stationery & Printing'),
 (3,(SELECT id FROM account_heads WHERE code='5002'),'House Rent'),
 (3,(SELECT id FROM account_heads WHERE code='5002'),'Electricity Bill'),
 (3,(SELECT id FROM account_heads WHERE code='5002'),'Internet Bill'),
 (2,(SELECT id FROM account_heads WHERE code='5003'),'Training Materials'),
 (2,(SELECT id FROM account_heads WHERE code='5003'),'Trainer Honorarium'),
 (4,(SELECT id FROM account_heads WHERE code='5004'),'Equipment Repair'),
 (5,(SELECT id FROM account_heads WHERE code='5005'),'Travel & Conveyance'),
 (5,(SELECT id FROM account_heads WHERE code='5099'),'Miscellaneous');
INSERT INTO financial_years (name,start_date,end_date,is_current) VALUES
 ('2025-26','2025-07-01','2026-06-30',0),('2026-27','2026-07-01','2027-06-30',1);
INSERT INTO voucher_types (code,name) VALUES ('MR','Money Receipt'),('DV','Debit Voucher'),('CV','Credit Voucher'),('JV','Journal Voucher');

INSERT INTO asset_categories (name,code) VALUES ('Computer & IT','IT'),('Furniture','FUR'),('Electrical Equipment','ELE'),('Sewing Machine','SEW'),('Vehicle','VEH'),('Training Equipment','TRN'),('Office Equipment','OFF');

-- Certificate template. Placeholders: {student_name} {father_name} {student_code} {course_name}
-- {course_duration} {batch_name} {center_name} {district} {start_date} {end_date} {grade}
INSERT INTO certificate_templates (name,heading,body_text,signatory1_name,signatory1_title,signatory2_name,signatory2_title,is_default) VALUES
('Standard','Certificate of Completion',
 'This is to certify that {student_name}, son/daughter of {father_name}, Student ID {student_code}, has successfully completed the {course_duration} training course on "{course_name}" ({batch_name}) held at {center_name}, {district} from {start_date} to {end_date} and obtained grade {grade}.',
 '','Course Coordinator','','Principal, RTTI',1);

-- System settings
INSERT INTO system_settings (setting_key,setting_value,setting_group) VALUES
 ('institute_name','RDRS Technical Training Institute (RTTI)','general'),
 ('institute_name_bn','আরডিআরএস টেকনিক্যাল ট্রেনিং ইনস্টিটিউট','general'),
 ('institute_short','RTTI','general'),
 ('organization_name','RDRS Bangladesh','general'),
 ('institute_address','Jail Road, Rangpur, Bangladesh','general'),
 ('institute_phone','','general'),
 ('institute_email','','general'),
 ('institute_website','','general'),
 ('institute_logo','assets/img/logo.jpg','general'),
 ('currency','BDT','general'),
 ('currency_symbol','৳','general'),
 ('date_format','d/m/Y','general'),
 ('timezone','Asia/Dhaka','general'),
 ('default_language','en','general'),
 ('student_id_format','RTTI-{YY}-{SEQ6}','numbering'),
 ('enrollment_id_format','ENR-{YYYY}-{SEQ6}','numbering'),
 ('receipt_no_format','MR-{CENTER}-{YY}-{SEQ5}','numbering'),
 ('voucher_no_format','DV-{CENTER}-{YY}-{SEQ5}','numbering'),
 ('certificate_no_format','RTTI/{YYYY}/{SEQ6}','numbering'),
 ('batch_code_format','{CENTER}-{COURSE}-{YY}-{SEQ3}','numbering'),
 ('employee_id_format','EMP-{SEQ4}','numbering'),
 ('asset_code_format','AST-{CENTER}-{SEQ5}','numbering'),
 ('cert_require_pass','1','certificate'),
 ('cert_require_attendance','1','certificate'),
 ('cert_require_full_payment','1','certificate'),
 ('cert_require_batch_completed','0','certificate'),
 ('enrollment_requires_approval','0','workflow'),
 ('expense_self_approval','0','workflow'),
 ('receipt_footer','Fees once paid are not refundable. Please keep this receipt safely.','receipt'),
 ('max_login_attempts','5','security'),
 ('lockout_minutes','15','security'),
 ('session_timeout_minutes','60','security'),
 ('backup_keep_days','30','backup');
