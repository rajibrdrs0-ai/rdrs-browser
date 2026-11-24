# Database ERD

Database `rtti_training_management`: 65 InnoDB tables, utf8mb4, with foreign keys enforced. Executable schema: `database/schema.sql`.

## Core entity-relationship diagram

```mermaid
erDiagram
    centers ||--o{ accounts : "center_id"
    centers ||--o{ assets : "center_id"
    staff ||--o{ assets : "assigned_staff_id"
    centers ||--o{ asset_assignments : "from_center_id"
    staff ||--o{ asset_assignments : "staff_id"
    centers ||--o{ asset_assignments : "to_center_id"
    assets ||--o{ asset_assignments : "asset_id"
    assets ||--o{ asset_maintenance : "asset_id"
    batches ||--o{ assignments : "batch_id"
    courses ||--o{ assignments : "course_id"
    assignments ||--o{ assignment_submissions : "assignment_id"
    enrollments ||--o{ assignment_submissions : "enrollment_id"
    batches ||--o{ attendance : "batch_id"
    staff ||--o{ attendance : "trainer_id"
    attendance ||--o{ attendance_details : "attendance_id"
    enrollments ||--o{ attendance_details : "enrollment_id"
    centers ||--o{ batches : "center_id"
    courses ||--o{ batches : "course_id"
    batch_statuses ||--o{ batches : "batch_status_id"
    staff ||--o{ batches : "trainer_id"
    districts ||--o{ centers : "district_id"
    divisions ||--o{ centers : "division_id"
    upazilas ||--o{ centers : "upazila_id"
    batches ||--o{ certificates : "batch_id"
    centers ||--o{ certificates : "center_id"
    courses ||--o{ certificates : "course_id"
    enrollments ||--o{ certificates : "enrollment_id"
    students ||--o{ certificates : "student_id"
    certificate_templates ||--o{ certificates : "template_id"
    batches ||--o{ class_routines : "batch_id"
    staff ||--o{ class_routines : "trainer_id"
    course_statuses ||--o{ courses : "course_status_id"
    trades ||--o{ courses : "trade_id"
    courses ||--o{ course_subjects : "course_id"
    divisions ||--o{ districts : "division_id"
    batches ||--o{ enrollments : "batch_id"
    centers ||--o{ enrollments : "center_id"
    courses ||--o{ enrollments : "course_id"
    students ||--o{ enrollments : "student_id"
    enrollments ||--o{ enrollments : "transferred_from_id"
    batches ||--o{ examinations : "batch_id"
    enrollments ||--o{ examination_results : "enrollment_id"
    examinations ||--o{ examination_results : "examination_id"
    payment_methods ||--o{ expenses : "payment_method_id"
    users ||--o{ expenses : "prepared_by"
    accounts ||--o{ expenses : "account_id"
    users ||--o{ expenses : "approved_by"
    centers ||--o{ expenses : "center_id"
    expense_heads ||--o{ expenses : "expense_head_id"
    accounts ||--o{ fee_collections : "account_id"
    centers ||--o{ fee_collections : "center_id"
    enrollments ||--o{ fee_collections : "enrollment_id"
    payment_methods ||--o{ fee_collections : "payment_method_id"
    students ||--o{ fee_collections : "student_id"
    users ||--o{ fee_collections : "received_by"
    centers ||--o{ fee_structures : "center_id"
    courses ||--o{ fee_structures : "course_id"
    centers ||--o{ payment_transactions : "center_id"
    enrollments ||--o{ payment_transactions : "enrollment_id"
    fee_collections ||--o{ payment_transactions : "fee_collection_id"
    students ||--o{ payment_transactions : "student_id"
    permissions ||--o{ role_permissions : "permission_id"
    roles ||--o{ role_permissions : "role_id"
    centers ||--o{ staff : "center_id"
    genders ||--o{ staff : "gender_id"
    trades ||--o{ staff : "trainer_trade_id"
    centers ||--o{ students : "center_id"
    users ||--o{ students : "created_by"
    districts ||--o{ students : "district_id"
    genders ||--o{ students : "gender_id"
    upazilas ||--o{ students : "upazila_id"
    users ||--o{ student_documents : "verified_by"
    students ||--o{ student_documents : "student_id"
    users ||--o{ student_documents : "uploaded_by"
    districts ||--o{ upazilas : "district_id"
    centers ||--o{ users : "default_center_id"
    staff ||--o{ users : "staff_id"
    centers ||--o{ user_centers : "center_id"
    users ||--o{ user_centers : "user_id"
    roles ||--o{ user_roles : "role_id"
    users ||--o{ user_roles : "user_id"
    centers {
        int id PK
        varchar code
        varchar name
        tinyint is_head_office
        int division_id FK
        int district_id FK
        int upazila_id FK
        varchar address
        varchar contact_person
        varchar mobile
        varchar email
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    districts {
        int id PK
        int division_id FK
        varchar name
        varchar name_bn
        varchar code
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    divisions {
        int id PK
        varchar name
        varchar name_bn
        varchar code
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    upazilas {
        int id PK
        int district_id FK
        varchar name
        varchar name_bn
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    students {
        int id PK
        varchar student_code
        varchar name
        varchar name_bn
        varchar father_name
        varchar mother_name
        date date_of_birth
        int gender_id FK
        varchar nid
        varchar birth_reg_no
        varchar mobile
        varchar alt_mobile
        varchar email
        varchar present_address
        varchar permanent_address
        int district_id FK
        int upazila_id FK
        int education_level_id FK
        varchar qualification_details
        varchar occupation
        varchar photo
        varchar emergency_contact_name
        varchar emergency_contact_mobile
        date registration_date
        int center_id FK
        enum status
        text remarks
        int created_by FK
        int updated_by
        datetime created_at
        datetime updated_at
        datetime deleted_at
        int deleted_by
    }
    enrollments {
        int id PK
        varchar enrollment_code
        int student_id FK
        int course_id FK
        int batch_id FK
        int center_id FK
        date enrollment_date
        decimal course_fee
        decimal discount
        varchar discount_reason
        decimal payable_amount
        decimal paid_amount
        decimal due_amount
        enum status
        int approved_by
        datetime approved_at
        int cancelled_by
        datetime cancelled_at
        varchar cancel_reason
        varchar duplicate_override_reason
        int transferred_from_id FK
        decimal result_percent
        varchar result_grade
        enum result_status
        date completion_date
        text remarks
        int created_by
        datetime created_at
        datetime updated_at
    }
    courses {
        int id PK
        varchar code
        varchar name
        int academic_category_id FK
        int trade_id FK
        text description
        int duration
        enum duration_unit
        decimal fee
        int min_education_level_id FK
        text objectives
        text outline
        decimal pass_mark_percent
        decimal min_attendance_percent
        int course_status_id FK
        int created_by
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }
    course_subjects {
        int id PK
        int course_id FK
        varchar name
        decimal theory_full_marks
        decimal practical_full_marks
        int sort_order
        datetime created_at
        datetime updated_at
    }
    batches {
        int id PK
        varchar batch_code
        varchar name
        int course_id FK
        int center_id FK
        int trainer_id FK
        date start_date
        date end_date
        varchar class_days
        time class_start_time
        time class_end_time
        varchar class_schedule
        varchar room
        int max_students
        int batch_status_id FK
        datetime completed_at
        text remarks
        int created_by
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }
    staff {
        int id PK
        varchar employee_code
        varchar name
        int designation_id FK
        int department_id FK
        int center_id FK
        int gender_id FK
        varchar mobile
        varchar email
        varchar nid
        date joining_date
        varchar photo
        varchar present_address
        varchar permanent_address
        tinyint is_trainer
        int trainer_trade_id FK
        enum employment_status
        text remarks
        int created_by
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }
    users {
        int id PK
        varchar name
        varchar username
        varchar email
        varchar mobile
        varchar password_hash
        int staff_id FK
        int default_center_id FK
        char language
        tinyint is_active
        tinyint must_change_password
        int failed_attempts
        datetime locked_until
        datetime last_login_at
        varchar session_token
        datetime created_at
        datetime updated_at
    }
    roles {
        int id PK
        varchar name
        varchar slug
        varchar description
        tinyint all_centers
        tinyint is_system
        datetime created_at
        datetime updated_at
    }
    permissions {
        int id PK
        varchar module
        varchar action
        varchar slug
        varchar label
    }
    role_permissions {
        int role_id PK
        int permission_id PK
    }
    user_roles {
        int user_id PK
        int role_id PK
    }
    user_centers {
        int user_id PK
        int center_id PK
    }
    fee_collections {
        int id PK
        varchar receipt_no
        varchar transaction_ref
        int enrollment_id FK
        int student_id FK
        int center_id FK
        date payment_date
        decimal payable_snapshot
        decimal previous_paid
        decimal amount
        decimal due_after
        int payment_method_id FK
        int account_id FK
        int received_by FK
        varchar remarks
        enum status
        int reversed_by
        datetime reversed_at
        varchar reversal_reason
        datetime created_at
    }
    payment_transactions {
        int id PK
        int enrollment_id FK
        int student_id FK
        int center_id FK
        int fee_collection_id FK
        date txn_date
        enum txn_type
        decimal debit
        decimal credit
        varchar narration
        int created_by
        datetime created_at
    }
    expenses {
        int id PK
        varchar voucher_no
        date expense_date
        int center_id FK
        int expense_head_id FK
        varchar description
        decimal amount
        int payment_method_id FK
        int account_id FK
        varchar paid_to
        varchar supporting_document
        enum status
        int prepared_by FK
        int approved_by FK
        datetime approved_at
        varchar rejection_reason
        varchar remarks
        datetime created_at
        datetime updated_at
    }
    expense_heads {
        int id PK
        int expense_category_id FK
        int account_head_id FK
        varchar name
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    certificates {
        int id PK
        varchar certificate_no
        char verification_token
        int enrollment_id FK
        int student_id FK
        int course_id FK
        int batch_id FK
        int center_id FK
        int template_id FK
        date completion_date
        date issue_date
        varchar grade
        enum status
        int issued_by
        int cancelled_by
        datetime cancelled_at
        varchar cancel_reason
        int print_count
        datetime created_at
        datetime updated_at
    }
    certificate_templates {
        int id PK
        varchar name
        varchar heading
        text body_text
        varchar signatory1_name
        varchar signatory1_title
        varchar signatory2_name
        varchar signatory2_title
        tinyint is_default
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    attendance {
        int id PK
        int batch_id FK
        date attendance_date
        varchar topic
        int trainer_id FK
        int taken_by
        datetime created_at
        datetime updated_at
    }
    attendance_details {
        int id PK
        int attendance_id FK
        int enrollment_id FK
        enum status
        varchar remarks
    }
    examinations {
        int id PK
        int batch_id FK
        varchar name
        enum exam_type
        date exam_date
        decimal theory_full_marks
        decimal practical_full_marks
        tinyint is_final
        enum status
        datetime published_at
        int published_by
        int created_by
        datetime created_at
        datetime updated_at
    }
    examination_results {
        int id PK
        int examination_id FK
        int enrollment_id FK
        int subject_id
        decimal theory_marks
        decimal practical_marks
        tinyint is_absent
        varchar remarks
        int entered_by
        datetime created_at
        datetime updated_at
    }
    assignments {
        int id PK
        varchar title
        int course_id FK
        int batch_id FK
        text instructions
        datetime deadline
        decimal total_marks
        varchar attachment
        int created_by
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }
    assignment_submissions {
        int id PK
        int assignment_id FK
        int enrollment_id FK
        datetime submitted_at
        text submission_text
        varchar file_path
        decimal marks
        text feedback
        enum status
        int evaluated_by
        datetime evaluated_at
        datetime created_at
        datetime updated_at
    }
    assets {
        int id PK
        varchar asset_code
        varchar name
        int asset_category_id FK
        varchar serial_no
        varchar brand_model
        date purchase_date
        decimal purchase_price
        varchar supplier
        int center_id FK
        int assigned_staff_id FK
        enum asset_condition
        date warranty_expiry
        enum status
        date disposal_date
        varchar disposal_note
        text remarks
        int created_by
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }
    asset_assignments {
        int id PK
        int asset_id FK
        enum movement_type
        int from_center_id FK
        int to_center_id FK
        int staff_id FK
        date movement_date
        varchar note
        int created_by
        datetime created_at
    }
    asset_maintenance {
        int id PK
        int asset_id FK
        date maintenance_date
        varchar description
        decimal cost
        varchar vendor
        date next_due_date
        int created_by
        datetime created_at
    }
    student_documents {
        int id PK
        int student_id FK
        int document_category_id FK
        varchar title
        varchar file_path
        varchar original_name
        varchar mime_type
        int file_size
        enum verification_status
        int verified_by FK
        datetime verified_at
        varchar remarks
        int uploaded_by FK
        datetime created_at
        datetime updated_at
        datetime deleted_at
    }
    trades {
        int id PK
        varchar code
        varchar name
        varchar name_bn
        varchar description
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    batch_statuses {
        int id PK
        varchar code
        varchar name
        varchar color
        tinyint allows_enrollment
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    course_statuses {
        int id PK
        varchar code
        varchar name
        varchar color
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    payment_methods {
        int id PK
        varchar name
        tinyint requires_reference
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    accounts {
        int id PK
        varchar name
        enum account_type
        int bank_id FK
        varchar account_no
        int center_id FK
        decimal opening_balance
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    genders {
        int id PK
        varchar name
        varchar name_bn
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
    class_routines {
        int id PK
        int batch_id FK
        tinyint day_of_week
        time start_time
        time end_time
        varchar subject
        int trainer_id FK
        varchar room
        datetime created_at
        datetime updated_at
    }
    fee_structures {
        int id PK
        int course_id FK
        int center_id FK
        decimal fee
        date effective_from
        tinyint is_active
        datetime created_at
        datetime updated_at
    }
```

## All foreign keys

| Table | Column | References |
|---|---|---|
| accounts | bank_id | banks |
| accounts | center_id | centers |
| assets | asset_category_id | asset_categories |
| assets | center_id | centers |
| assets | assigned_staff_id | staff |
| asset_assignments | from_center_id | centers |
| asset_assignments | staff_id | staff |
| asset_assignments | to_center_id | centers |
| asset_assignments | asset_id | assets |
| asset_maintenance | asset_id | assets |
| assignments | batch_id | batches |
| assignments | course_id | courses |
| assignment_submissions | assignment_id | assignments |
| assignment_submissions | enrollment_id | enrollments |
| attendance | batch_id | batches |
| attendance | trainer_id | staff |
| attendance_details | attendance_id | attendance |
| attendance_details | enrollment_id | enrollments |
| batches | center_id | centers |
| batches | course_id | courses |
| batches | batch_status_id | batch_statuses |
| batches | trainer_id | staff |
| centers | district_id | districts |
| centers | division_id | divisions |
| centers | upazila_id | upazilas |
| certificates | batch_id | batches |
| certificates | center_id | centers |
| certificates | course_id | courses |
| certificates | enrollment_id | enrollments |
| certificates | student_id | students |
| certificates | template_id | certificate_templates |
| certificate_prints | certificate_id | certificates |
| class_routines | batch_id | batches |
| class_routines | trainer_id | staff |
| courses | academic_category_id | academic_categories |
| courses | min_education_level_id | education_levels |
| courses | course_status_id | course_statuses |
| courses | trade_id | trades |
| course_documents | course_id | courses |
| course_subjects | course_id | courses |
| districts | division_id | divisions |
| enrollments | batch_id | batches |
| enrollments | center_id | centers |
| enrollments | course_id | courses |
| enrollments | student_id | students |
| enrollments | transferred_from_id | enrollments |
| examinations | batch_id | batches |
| examination_results | enrollment_id | enrollments |
| examination_results | examination_id | examinations |
| expenses | payment_method_id | payment_methods |
| expenses | prepared_by | users |
| expenses | account_id | accounts |
| expenses | approved_by | users |
| expenses | center_id | centers |
| expenses | expense_head_id | expense_heads |
| expense_heads | account_head_id | account_heads |
| expense_heads | expense_category_id | expense_categories |
| fee_collections | account_id | accounts |
| fee_collections | center_id | centers |
| fee_collections | enrollment_id | enrollments |
| fee_collections | payment_method_id | payment_methods |
| fee_collections | student_id | students |
| fee_collections | received_by | users |
| fee_structures | center_id | centers |
| fee_structures | course_id | courses |
| payment_transactions | center_id | centers |
| payment_transactions | enrollment_id | enrollments |
| payment_transactions | fee_collection_id | fee_collections |
| payment_transactions | student_id | students |
| qualifications | education_level_id | education_levels |
| role_permissions | permission_id | permissions |
| role_permissions | role_id | roles |
| staff | center_id | centers |
| staff | department_id | departments |
| staff | designation_id | designations |
| staff | gender_id | genders |
| staff | trainer_trade_id | trades |
| staff_attendance | staff_id | staff |
| students | center_id | centers |
| students | created_by | users |
| students | district_id | districts |
| students | education_level_id | education_levels |
| students | gender_id | genders |
| students | upazila_id | upazilas |
| student_documents | verified_by | users |
| student_documents | document_category_id | document_categories |
| student_documents | student_id | students |
| student_documents | uploaded_by | users |
| unions | upazila_id | upazilas |
| upazilas | district_id | districts |
| users | default_center_id | centers |
| users | staff_id | staff |
| user_centers | center_id | centers |
| user_centers | user_id | users |
| user_roles | role_id | roles |
| user_roles | user_id | users |
| villages | union_id | unions |

## Design notes

* **Lifetime student**: `students.student_code` is unique. The application blocks duplicate NID/BRN across all centers. `enrollments` links one student to many course batches.
* **Center scoping**: every operational table has `center_id`. `user_centers` and `roles.all_centers` decide what each user can see.
* **Money**: only `FeeService` updates `enrollments.paid_amount`. `due_amount` is a generated column. `payment_transactions` is an append-only ledger. `fee_collections` rows are never deleted; a reversal sets `status = reversed`.
* **Numbering**: `sequences` holds row-locked, gap-free counters. Number formats live in `system_settings`.
* **Soft delete** (`deleted_at`) is used for operational records. Master data uses `is_active`.
* **Audit**: `audit_logs` records who, what, when and IP, plus changed fields (old → new). `login_logs` records every sign-in attempt.
