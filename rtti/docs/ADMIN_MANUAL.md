# Administrator manual

## 1. First steps after installation
1. **System Settings**: institute name, address, phone, logo, receipt footer, certificate requirements and number formats.
2. **Master Entry → Center**: add every district training center (code such as `RNG`, `KUR`; the code appears in receipt, batch and asset numbers).
3. **Master Entry → Location**: all 8 divisions and 64 districts are preloaded, and upazilas for Rangpur division. Add more upazilas, unions and villages as needed.
4. **Master Entry → Accounts**: payment methods, cash/bank/bKash accounts, expense heads, financial year, and center-wise fee overrides (*Center-wise Fee Structure*).
5. **Master Entry → Trade / Academic**: trades, education levels, grade scale, designations, document categories, certificate template and signatories.
6. **Staff Register**: add staff and mark trainers.
7. **User Accounts**: create a user for each staff member, choose roles and assigned centers. Head-office roles (marked *All centers*) see every center.
8. **Academics → Courses** (with subjects/marks distribution), then **Batches**.

## 2. Roles
| Role | Scope | Typical use |
|---|---|---|
| Super Administrator | all | everything, restore backups, sync permissions |
| Head Office Administrator | all | monitor and control all centers |
| Head Office Accounts | all | fees, expenses and financial reports for all centers |
| Academic Manager | all | courses, batches, exams, certificates |
| Center Manager | assigned centers | runs a center, approves expenses and enrollments |
| Center Administrator / Admission Officer / Accounts Officer / Trainer / Data Entry | assigned centers | day-to-day work |
| Report Viewer | all | read-only reports |

Change any role in *Administration → Roles & Permissions* (menu × action matrix). Permissions are checked on the server, not only hidden in the menu.
A user cannot grant roles or permissions that they do not hold.

## 3. Daily workflow
1. **Register student** (*Students → Student Register → New*). The system warns about the same mobile and **blocks** the same NID/birth registration. An existing person must be enrolled with their existing ID.
2. **Enroll** (*Save & enroll*, or *Student Enrollment → New*): choose course and batch. Seats, fee and discount are calculated automatically, and an initial payment produces a money receipt.
3. **Collect installments** (*Accounts → Fee Collection → New*, or the *Collect* button in the Due List). Print the receipt.
4. **Attendance** (*Academics → Attendance → Take attendance*) for each class day.
5. **Exams**: create the *Final* exam for a batch, enter marks, **Publish**. The final result is written to each enrollment.
6. **Complete batch**, then **Certificates → Issue**. Only trainees meeting the configured requirements (pass, attendance %, full payment) can be selected.
7. **Expenses**: the accounts officer enters a voucher with the bill attached, and the center manager approves it. A person cannot approve their own voucher.

## 4. Corrections, without deleting history
* Wrong receipt: **Reverse** it with a reason. The original stays, marked *reversed*, and a counter entry goes into the ledger.
* Wrong discount: *Enrollment → Discount / remarks*. The ledger records the adjustment.
* Student leaves: *Drop out* or *Cancel*. Money already paid stays on record and the remaining due is written off.
* Wrong batch: *Batch transfer*. Payments move with the student.
* Certificate error: **Cancel** it (public verification shows *cancelled*) and issue again.
* Approved expense: can only be *cancelled* with a reason.

## 5. Reports
*Reports* lists 25 reports. Use District/Center filters for separate figures. Without them, head office sees consolidated totals.
Every report and list has **Excel**, **CSV** and **Print/PDF** buttons. For PDF, choose *Save as PDF* in the print dialog.

## 6. Import
*Administration → Data Import*: download the template, fill it in Excel, upload (.xlsx or .csv), review the preview (errors shown per row, downloadable error report), then confirm.
Supported: students, staff, courses, batches, assets.

## 7. Security tips
* Use HTTPS in production. Delete `public/install.php` after installation.
* Accounts lock after 5 failed sign-ins (configurable). Unlock with *Activate* or *Reset password*.
* *User Accounts → force sign-out* ends a user's session immediately. Deactivating a staff member also deactivates their user account.
* Review *Audit Log* and *Login history* regularly.
