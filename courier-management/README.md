# Courier Management সার্ভিস

Enterprise courier, line-haul, last-mile delivery and **double-entry accounting** platform
for multi-branch (hub-and-spoke) operations — B2B merchants + B2C walk-in customers,
bilingual **বাংলা / English**.

| | |
|---|---|
| Backend | Core PHP 8.2+ (strict types, MVC, PSR-4, PDO prepared statements) |
| Database | MySQL 8.0+ (InnoDB, utf8mb4, FK, CHECK constraints, triggers, views) |
| Frontend | Bootstrap 5.3, jQuery/Vanilla JS, AJAX, Chart.js, JsBarcode, QRCode.js |

## Build status (phased delivery)

| Phase | Scope | Status |
|---|---|---|
| 1 | Architecture, folder structure, routing, config, security helpers | ✅ done |
| 2 | Complete MySQL 8 schema + seed data (`database/schema.sql`) | ✅ done |
| 3 | PDO singleton, Auth/RBAC middleware, `__()` language loader, Accounting engine, tracking/barcode generator | next |
| 4 | UI: layout + BN/EN switch, booking + 4×6 label, manifests, rider DRS, ledger & settlement views | — |
| 5 | XAMPP/Laragon & Linux (Nginx/Apache) deployment guide | — |

---

## 1. Architecture

```
Browser ──► public/index.php (front controller)
              │
              ▼
        bootstrap/app.php ── Env (.env) ── Config (config/*.php) ── ErrorHandler
              │
              ▼
        Core\Application::handle()
              ├─ Session::start()        hardened cookie session (not for /api)
              ├─ routes/web.php, routes/api.php
              ├─ global middleware
              ├─ Router::dispatch() ──► route middleware pipeline
              │                         csrf → auth → permission:<slug> → branch
              │                              ▼
              │                         Controller ──► Services ──► Repositories/Models ──► PDO
              └─ SecurityHeaders::apply()  CSP nonce, HSTS, X-Frame-Options … on EVERY response
```

* **Fail-closed middleware** — a route naming a middleware whose class doesn't exist throws
  instead of running unprotected.
* **Errors** — every PHP warning becomes an exception; unhandled exceptions are logged to
  `storage/logs/app-YYYY-MM-DD.log`; users see a safe page (JSON for AJAX/API). Traces only
  with `APP_DEBUG=true`.
* **Validation** — `Controller::validate()` throws `ValidationException` → redirect back with
  `errors()`/`old()` for forms, or HTTP 422 JSON for AJAX.

## 2. Directory structure

```
courier-management/
├── .htaccess                 # root guard → forwards to /public, blocks app dirs
├── .env.example              # copy to .env
├── composer.json             # PSR-4  App\ → app/   (Composer optional)
├── app/
│   ├── Core/                 # framework kernel
│   │   ├── Application.php   # HTTP kernel
│   │   ├── Router.php        # groups, {id:\d+} params, named routes, middleware pipeline
│   │   ├── Request.php       # method spoofing, JSON body, trusted-proxy aware IP
│   │   ├── Response.php      # html/json/redirect(open-redirect safe)/download
│   │   ├── Session.php       # strict mode, idle+absolute timeout, ID rotation, flash
│   │   ├── View.php          # PHP templates with layouts & sections
│   │   ├── Controller.php    # base controller
│   │   ├── Env.php, Config.php, ErrorHandler.php
│   │   ├── MiddlewareInterface.php, HttpException.php, ValidationException.php
│   ├── Security/
│   │   ├── Csrf.php          # synchronizer token, hash_equals
│   │   ├── Password.php      # Argon2id (bcrypt fallback), policy, OTP, tokens
│   │   ├── Crypto.php        # libsodium secretbox (partner API keys), HMAC lookups
│   │   ├── Validator.php     # rules incl. bd_phone, decimal:n, tracking_no
│   │   ├── FileUpload.php    # finfo MIME check, random names, outside web root
│   │   └── SecurityHeaders.php
│   ├── Middleware/           # CsrfMiddleware (Auth/Permission/Branch in Phase 3)
│   ├── Helpers/functions.php # e(), url(), asset(), csrf_field(), old(), money(), bn_digits() …
│   ├── Controllers/{Admin,Merchant,Rider,Api}/
│   ├── Models/  Repositories/  Services/
│   └── Views/{layouts,partials,errors}/
├── bootstrap/app.php
├── config/{app,database,security}.php
├── database/schema.sql       # Phase 2
├── lang/                     # bn.php / en.php (Phase 3)
├── public/                   # ← web server document root
│   ├── index.php, .htaccess
│   ├── assets/{css,js,img,vendor}/
│   └── uploads/.htaccess     # script execution disabled
├── routes/{web,api}.php
└── storage/{logs,cache,sessions,uploads/{pod,signatures,bulk,attachments}}/   # not web-accessible
```

## 3. Security baseline (Phase 1)

| Threat | Control |
|---|---|
| SQL injection | PDO with native prepared statements (`ATTR_EMULATE_PREPARES=false`) |
| XSS | `e()` escaping in every template, CSP with per-request nonce (`csp_nonce()`) |
| CSRF | per-session token, `csrf_field()` / `X-CSRF-TOKEN` header, 419 on mismatch |
| Session hijack/fixation | strict mode, HttpOnly + SameSite + Secure, ID rotation every 15 min and on login, UA fingerprint, idle/absolute timeout |
| Password storage | Argon2id (64 MB, t=4), automatic re-hash of legacy bcrypt |
| Brute force | `login_attempts` table + per-user lockout columns (enforced in Phase 3) |
| Clickjacking / sniffing | `X-Frame-Options`, `X-Content-Type-Options`, `frame-ancestors` |
| Malicious uploads | finfo MIME whitelist, size limit, random names, stored under `storage/` |
| Open redirect | `Response::redirect()` only allows same-app absolute URLs |
| Secrets at rest | partner API keys encrypted with libsodium (`APP_KEY`) |
| Info leakage | no traces in production, `X-Powered-By` removed |

## 4. Database (Phase 2)

`database/schema.sql` — 64 tables, 20 triggers, 7 views, full seed data.

| Domain | Tables |
|---|---|
| Geography | `divisions` (8), `districts` (all 64), `zones` (ISD/SUB/CTG/OSD/RMT), `thanas` |
| Organisation | `branches` (HO, 2 hubs, 9 branches), `branch_coverage` (thana → delivering branch, own fleet / 3PL), `routing_rules`, `vehicles` |
| Identity & RBAC | `roles` (7), `permissions` (72), `role_permissions`, `user_permissions` (per-user grant/revoke), `user_branch_access`, `users`, `login_attempts`, `password_resets`, `user_sessions` |
| Merchants / customers | `merchants`, `merchant_payout_accounts`, `merchant_pickup_addresses`, `merchant_api_keys`, `customers` |
| Tariff engine | `service_types` (REG/EXP/SDD/FRG), `packaging_types`, `rate_cards`, `rate_card_items` (zone × zone × service, base kg + per-kg slabs, COD %, RTO %), `merchant_rate_agreements` |
| Parcels | `parcels`, `parcel_items`, `parcel_statuses` (bilingual), `parcel_tracking_events`, `scan_logs`, `bulk_uploads`, `pickup_requests`, `number_sequences` |
| Line-haul | `manifests`, `bags`, `manifest_items` (scan-out / scan-in reconciliation) |
| Last mile | `riders`, `courier_partners`, `delivery_run_sheets`, `drs_items`, `delivery_failure_reasons`, `delivery_otps`, `proof_of_delivery`, `rto_requests`, `rider_cash_handovers` |
| Accounting | `fiscal_years`, `chart_of_accounts` (81 accounts with `system_key`), `bank_accounts`, `journal_entries`, `journal_lines`, `vouchers`, `voucher_lines`, `invoices`, `invoice_items`, `invoice_payments`, `money_receipts`, `merchant_settlements`, `merchant_settlement_items` |
| System | `settings`, `audit_logs`, `notifications`, `sms_logs` |

### Ledger guarantees enforced *inside MySQL* (not just in PHP)

* An entry can only be **posted** when Σdebit = Σcredit > 0 with ≥ 2 lines, inside an open fiscal year and after the lock date.
* Posted entries and their lines are **immutable** — corrections are reversals (`reversal_of_id` / `reversed_by_id`).
* Each line is debit **xor** credit; group accounts can't be posted to; accounts flagged `requires_party` (Merchant COD Payable, Rider Receivable …) reject lines without the matching merchant/rider/partner sub-ledger.
* `idempotency_key` (unique) stops the same business event from being posted twice.
* Settlement maker ≠ checker (`CHECK approved_by <> prepared_by`); parcel amounts lock once in settlement.

### Posting map used by the engine (Phase 3)

| Event | Debit | Credit |
|---|---|---|
| COD parcel delivered | Rider Cash Receivable *(rider)* = collected | Merchant COD Payable *(merchant)* = collected − charges · Delivery / COD Fee / … Revenue · VAT Payable |
| Rider hands over cash | Cash in Hand (branch) · Cash Shortage | Rider Cash Receivable *(rider)* |
| B2C prepaid counter booking | Cash in Hand | Delivery Revenue · VAT Payable |
| B2B credit invoice | AR – Merchants *(merchant)* | Delivery Revenue · COD Fee · VAT Payable |
| Merchant payout | Merchant COD Payable *(merchant)* | Bank / bKash / Cash |
| Branch → bank deposit (contra) | Cash in Transit → Bank | Cash in Hand |
| RTO charge | Merchant COD Payable or AR *(merchant)* | Return Charge Revenue |

### Audit trail

The PDO layer runs `SET @app_user_id, @app_branch_id, @app_ip` after login; triggers write
`audit_logs` (old/new JSON) for parcel create/status/amount/address changes, user role/branch/
status/password changes, permission grants, journal post/reverse, voucher & settlement status,
tariff edits and cash handovers. `audit_logs` itself rejects UPDATE/DELETE.

## 5. Quick start (Phases 1–2)

```bash
cp .env.example .env
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"   # paste into APP_KEY
mysql -u root -p < database/schema.sql
mysql -u root -p -e "CREATE USER 'cms_user'@'localhost' IDENTIFIED BY 'strong-pass'; GRANT SELECT,INSERT,UPDATE,DELETE,EXECUTE ON courier_management.* TO 'cms_user'@'localhost';"
php -S 127.0.0.1:8000 -t public public/index.php
curl http://127.0.0.1:8000/health      # {"status":"ok", ... "database":true}
```

Default Super Admin (forced password change on first login):
**admin@courier.local / Admin@12345**

> **XAMPP note:** XAMPP ships MariaDB, not MySQL. The schema uses `utf8mb4_unicode_ci`
> for compatibility, but MySQL 8.0 (Laragon, or a standalone MySQL install) is the supported target.

---
### বাংলা সারসংক্ষেপ
প্রথম ধাপে MVC ফোল্ডার কাঠামো, রাউটিং, নিরাপত্তা (CSRF, সেশন, পাসওয়ার্ড হ্যাশিং, CSP) তৈরি হয়েছে।
দ্বিতীয় ধাপে ৬৪টি টেবিলসহ সম্পূর্ণ MySQL স্কিমা, ডাবল-এন্ট্রি লেজারের ডাটাবেস-স্তরের সুরক্ষা ট্রিগার,
অডিট ট্রেইল, রিপোর্ট ভিউ এবং সকল সিড ডাটা (৬৪ জেলা, শাখা, রোল-পারমিশন, হিসাব তালিকা, রেট কার্ড) যুক্ত হয়েছে।
পরবর্তী কমান্ড: **"Now generate Phase 3 …"**
