# RTTI – Training Institute Management System

A complete web application for **RDRS Technical Training Institute (RTTI)** to run training centers in several districts from one central Head Office.
It replaces the old PHPRunner application. Built with **PHP 8.1+ (8.2 recommended), MySQL 8 / MariaDB 10.6+, Bootstrap 5, jQuery, Chart.js**, with no Composer dependencies. All JS/CSS libraries are bundled, so it works offline on a LAN.

## Key features

| Area | What it does |
|---|---|
| **Multi-center management** | One Head Office + any number of district centers. Every student, enrollment, batch, receipt, expense, staff and asset belongs to a center. Head-office roles see all centers (consolidated); center users see only their assigned centers, and the server enforces this on every page, query and action. |
| **Lifetime Student ID** | One permanent Student ID per person (for example `RTTI-26-000123`). Duplicate NID or birth-registration numbers are blocked across **all** centers. The same student can enroll in many courses over the years. |
| **Financial control** | Fee is calculated automatically (center fee structure → course fee − discount). Supports partial payments, installments, dues and auto-numbered money receipts (student and office copies). Every charge, discount, payment and reversal goes into an **append-only ledger**. Reversals never delete anything. Expenses use maker-checker approval. |
| **Certificate verification** | Unique certificate numbers. Each certificate carries a QR code that opens a **public verification page** (no login needed, minimal personal data). Cancelled certificates show as CANCELLED. Reprints are logged. |
| **Consolidated reporting** | 25 reports (student, academic, financial, administrative). Each one can be filtered by date, district, center, course and batch, and exported to Excel, CSV or Print/PDF. Head Office gets consolidated figures. |

Other modules: dashboard with live charts, global search, batches with capacity control and transfer, class routine with clash detection, trainer workload, attendance (students and staff), exams with subject-wise theory/practical marks, grades and merit lists, assignments, documents (verify/reject), staff register, asset register (transfer, maintenance, disposal), RBAC with 11 roles and a menu-wise permission matrix, audit trail, login history, account lockout, Excel/CSV import with preview, backups, and an English/বাংলা interface.

## Requirements

* PHP 8.1 or newer with `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (optional), `zip` (for .xlsx import) and `zlib` (compressed backups)
* MySQL 8.0+ or MariaDB 10.6+
* Apache with mod_rewrite (XAMPP, Windows Server or Linux) or Nginx

## Installation with Docker (recommended for servers)

Requires Docker Desktop (Windows/Mac) or Docker Engine with the compose plugin (Linux).

```bash
cd rtti
cp .env.example .env        # then edit .env: set DB_PASS, DB_ROOT_PASS and ADMIN_PASSWORD
docker compose up -d --build
```
Open **http://localhost:8080** and sign in with `ADMIN_USERNAME` / `ADMIN_PASSWORD` from `.env`.

* On the first start the database is created automatically. Set `LOAD_DEMO_DATA=1` in `.env` **before** the first start to load sample data.
* Data persists in Docker volumes: `db_data` (MySQL 8) and `rtti_storage` (uploads, backups, logs).
* Logs: `docker compose logs -f app` · Stop: `docker compose down` · Update after code changes: `docker compose up -d --build`.
* phpMyAdmin (optional): `docker compose --profile tools up -d` → http://localhost:8081
* Database backup: `docker compose exec db mysqldump -u root -p rtti_training_management > rtti.sql`
* **Start again from an empty database** (deletes all data!): `docker compose down -v`
* For internet access, put a reverse proxy with HTTPS (Nginx, Caddy, Traefik) in front of port 8080.

## Installation (XAMPP on Windows)

1. Copy the `rtti` folder to `C:\xampp\htdocs\rtti`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open **http://localhost/rtti/public/install.php**.
4. Enter the database details (XAMPP default: user `root`, empty password) and the super-administrator account.
   Tick *Load sample data* only on a training or test copy.
5. Click **Install**, then delete `public/install.php`.
6. Sign in at **http://localhost/rtti/public/**.

Linux / production: point the web server's document root to `rtti/public` (recommended), or keep the root `.htaccess`, which redirects to `public/` and blocks `app/`, `config/`, `storage/`.
Make `config/` and `storage/` writable by the web server user.

Command-line installation:
```bash
php database/install_cli.php --host=127.0.0.1 --user=root --pass= --db=rtti_training_management --admin=admin --admin-pass='Admin@12345' --demo
```

Manual installation: import `database/schema.sql` and then `database/seed.sql`, copy `config/config.sample.php` to `config/config.php`, then run the CLI installer, which also creates permissions and the admin user.

### Demo accounts (only when sample data was loaded)
Password for all: `Demo@1234`, including `hoadmin` (Head Office admin), `hoaccounts`, `rangpur` and `kurigram` (center managers), `rngaccounts` (accounts officer), and `trainer`.

## Folder structure
```
rtti/
  app/Controllers   request handlers (one per module)
  app/Core          framework: DB (PDO), Auth/RBAC, CSRF, validation, uploads, export, sequences
  app/Services      business rules: enrollment, fees, certificates, exams, backup, installer, demo data
  app/Views         templates (layouts, partials, modules)
  config/           config.php (generated), permissions.php (RBAC catalogue), menu.php, master.php
  database/         schema.sql, seed.sql, install_cli.php
  docs/             ERD, administrator manual, backup & restore
  public/           web root: index.php, install.php, assets/
  storage/          uploads (outside web root), logs, backups
```

## Security
password_hash/verify · CSRF tokens on every POST · PDO prepared statements only · output escaping · server-side permission and center checks on every action ·
uploads checked by extension and real MIME, renamed, stored outside the web root and served through an access-checked route ·
login lockout per account and per IP · session timeout, fingerprint and forced sign-out · maker-checker on expenses · immutable financial ledger · full audit log.

See `docs/ADMIN_MANUAL.md`, `docs/BACKUP_RESTORE.md`, `docs/ERD.md`.
