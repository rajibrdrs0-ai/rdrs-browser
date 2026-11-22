# পুরনো RDRSTTI (PHPRunner) সিস্টেমের গ্যাপ বিশ্লেষণ

পুরনো সিস্টেমে ২৪টি টেবিল ছিল (zip থেকে পাওয়া PHPRunner সেটিংস দেখে)। কোনো SQL ডাম্প ছিল না,
তাই টেবিল ও কলামের তালিকা `include/*_settings.php` থেকে বের করা হয়েছে।

## ১. নিরাপত্তা (সবচেয়ে জরুরি)

| সমস্যা | প্রমাণ | নতুন সিস্টেমে সমাধান |
|---|---|---|
| ডাটাবেস সার্ভারের ঠিকানা, ইউজার ও পাসওয়ার্ড সোর্স কোডে খোলা অবস্থায় | `connections/ConnectionManager.php` (host `103.231.238.x`) | পাসওয়ার্ড শুধু `.env` ফাইলে, যা git-এ যায় না |
| JWT secret কোডে হার্ড-কোড করা | `include/appsettings.php` → `jwtSecret` | `APP_KEY` প্রতিটি ইনস্টলেশনে আলাদা, `.env`-এ |
| পাসওয়ার্ড প্লেইন টেক্সটে সংরক্ষণ | `users.shw_password` কলাম | শুধু Argon2id/bcrypt হ্যাশ; কলামটি বাদ |
| রিসেট টোকেন সরাসরি সংরক্ষণ, মেয়াদ নেই | `users.reset_password_token` | `password_resets`: SHA-256 হ্যাশ + মেয়াদ |
| ভুল পাসওয়ার্ডে সীমা নেই | — | ৫ বার ভুলে ১৫ মিনিট লক (ইউজার ও IP উভয় ভিত্তিতে) |
| প্রথম লগইনে পাসওয়ার্ড বদলের বাধ্যবাধকতা নেই | — | `must_change_password` |

> **এখনই করণীয়:** পুরনো সিস্টেম যদি এখনো অনলাইনে থাকে, তাহলে ওই MySQL ইউজারের পাসওয়ার্ড বদলান,
> সার্ভারের পোর্ট 3306 বাইরে থেকে বন্ধ করুন এবং zip ফাইলটি কারো সাথে শেয়ার করবেন না।

## ২. ইউজার রোল ও ডাটা পরিধি

| সমস্যা | নতুন সিস্টেম |
|---|---|
| ইউজার গ্রুপ নেই (`$cUserGroupField = ""`), `users.perm` একটি ফ্রি-টেক্সট কলাম | ১০টি রোল, ৭৬টি পারমিশন, রোল-পারমিশন ম্যাট্রিক্স এবং প্রতি ইউজারে অতিরিক্ত পারমিশন দেওয়া বা বাদ দেওয়ার ব্যবস্থা |
| কোনো ডাটা পৃথকীকরণ নেই (`OwnerID = ""`): যেকোনো কেন্দ্রের ইউজার সব কেন্দ্রের তথ্য দেখতে পায় | ৪ ধরনের পরিধি: সকল কেন্দ্র / নির্ধারিত জেলা / নির্ধারিত কেন্দ্র / শুধু নিজের তথ্য |
| কে কাকে তৈরি বা পরিবর্তন করতে পারবে তার নিয়ম নেই | স্তর (level) নিয়ম: শুধু নিজের চেয়ে নিচের রোল দেওয়া যায়, নিজের পরিধির কেন্দ্রেই |
| অডিট নেই | প্রতিটি লগইন, রোল বা পারমিশন পরিবর্তন, ফি ও ফলাফলের পরিবর্তন `audit_logs`-এ লেখা হয়; এগুলো মোছা যায় না |

## ৩. ডাটা কাঠামো

* **কেন্দ্রের নাম টেক্সট হিসেবে প্রতিটি টেবিলে আলাদাভাবে লেখা** (`center_name`, `center_Nam`, `center_nam`, `Center_name`…):
  একই কেন্দ্রের নাম ভিন্ন বানানে থাকলে রিপোর্ট ভুল আসে। এখন সব টেবিল `training_centers.id`-এর সাথে FK দিয়ে যুক্ত।
* **শিক্ষার্থীর নাম, কোর্সের নাম ও ফি কয়েকটি টেবিলে কপি করা** (`enrollment.stud_Name`, `fee.student_name`, `Course_nam`, `Course_fee`…):
  একটি টেবিলে বদলালে অন্যগুলো পুরনো থেকে যায়। এখন শুধু id দিয়ে যুক্ত।
* **একই কাজের জন্য দুটি টেবিল:** `enrollment` ও `student_course` → একটি `enrollments`;
  `batch`, `course_batch` ও `batch_status` → একটি `batches`।
* `student` টেবিলে কোর্সের তথ্য (`Which_trade`, `Dur_From`, `Admission_Fee`) রাখা ছিল, ফলে একজন একাধিক কোর্সে ভর্তি হলে তথ্য হারিয়ে যেত।
  এখন ব্যক্তির তথ্য একবার রাখা হয় এবং প্রতিটি কোর্সে ভর্তি আলাদা রেকর্ড হিসেবে থাকে।
* `loc_dis`-এ জেলা ও উপজেলা টেক্সট জোড়া হিসেবে ছিল, বিভাগ ছিল না। এখন বিভাগ, জেলা ও উপজেলা সম্পর্কিত টেবিলে।
* বানান ভুলের টেবিল ও কলাম নাম (`couse_status`, `dicunt`, `Reciden_fee`, `attandance`) ঠিক করা হয়েছে।

## ৪. যে মডিউলগুলো ছিল না

| মডিউল | নতুন টেবিল |
|---|---|
| প্রশিক্ষণার্থীর হাজিরা | `class_sessions`, `student_attendance` (হাজিরা লক করা যায়) |
| ক্লাস রুটিন | `class_routines`, `rooms` |
| পরীক্ষা, নম্বর ও ফলাফল | `assessments`, `assessment_marks` (অনুমোদনের পর নম্বর লক), `results`, `grade_scales` (GPA ও CBT) |
| যাচাইযোগ্য সনদ | `certificates`: অনন্য নম্বর + যাচাই কোড; শুধু পাস করা ফলাফলেই দেওয়া যায় |
| ফি ইনভয়েস ও বকেয়া হিসাব | `fee_invoices`, `fee_invoice_items`, `v_enrollment_dues` |
| মানি রসিদ যা বদলানো যায় না | `fee_payments`: সম্পাদনা বা মোছা যায় না; শুধু কারণসহ বাতিল করা যায় |
| ছাড় বা মওকুফ অনুমোদন | `fee_waivers`: যিনি আবেদন করেন তিনি নিজে অনুমোদন করতে পারেন না |
| ক্যাশ বুক ও ব্যাংক বুক | `finance_accounts`, `account_transactions` (ট্রিগার নিজে থেকেই লেখে) |
| খরচের ক্যাটাগরি ও অনুমোদন | `expense_categories`, `expenses` |
| ফান্ড ট্রান্সফার ও ফেরত | `fund_transfers`, `refunds` |
| কর্মীর ছুটি | `leave_requests` |
| সম্পদ স্থানান্তর | `asset_transfers`, `asset_categories` |
| চাকরি বা কর্মসংস্থান ট্র্যাকিং | `employers`, `placements` |
| নোটিশ ও এসএমএস লগ | `notices`, `sms_logs` |
| দাতা সংস্থার রিপোর্টের জন্য তথ্য | `students.disability`, `is_ethnic_minority`, `household_income_monthly`, `employment_status_before` |

## ৫. পুরনো টেবিল → নতুন টেবিল

| পুরনো | নতুন |
|---|---|
| center_name, academic | training_centers |
| loc_dis | divisions, districts, upazilas |
| users | users (+ roles, permissions, user_center_access, user_district_access) |
| staff | staff (+ designations) |
| staff_attendance | staff_attendance (প্রতিদিন প্রতি কর্মীর একটি রেকর্ড) |
| trade_info | trades |
| course | courses (+ course_modules, course_fees, fee_heads) |
| batch, course_batch, batch_status | batches (+ batch_trainers) |
| couse_status | `enrollments.status` ENUM |
| student, gender_info | students |
| acca_info | student_educations |
| student_docs | student_documents |
| enrollment, student_course | enrollments (+ fee_waivers, enrollment_status_logs) |
| fee | fee_invoices + fee_payments |
| expense | expenses (+ expense_categories) |
| certificate | certificates (+ results) |
| assignment | assignments (+ assignment_submissions) |
| asset_regist | assets (+ asset_categories) |
| todo | tasks |

## ৬. পুরনো ডাটা স্থানান্তর
zip-এ ডাটাবেসের কপি ছিল না। পুরনো MySQL ডাটাবেসের একটি এক্সপোর্ট (`mysqldump`) পেলে একটি মাইগ্রেশন স্ক্রিপ্ট লেখা যাবে।
স্ক্রিপ্টটি টেক্সট আকারে লেখা কেন্দ্রের নাম মিলিয়ে id বসাবে, ডুপ্লিকেট শিক্ষার্থী একত্র করবে এবং পুরনো ফি রেকর্ড থেকে রসিদ তৈরি করবে।
