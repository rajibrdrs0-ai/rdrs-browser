# সেটআপ গাইড — RTTI ম্যানেজমেন্ট সফটওয়্যার

এই গাইডে তিনভাবে সফটওয়্যারটি চালু করার নিয়ম আছে:

| ব্যবহার | পদ্ধতি |
|---|---|
| নিজের কম্পিউটারে চালিয়ে দেখা বা প্রশিক্ষণ | **ক) Windows + Laragon** (সুপারিশকৃত) অথবা **খ) XAMPP** |
| অফিস বা সব কেন্দ্র থেকে অনলাইনে ব্যবহার | **গ) Linux সার্ভার (Ubuntu)**, ক্লাউড VPS বা অফিসের সার্ভার |

সব পদ্ধতিতেই শেষ ধাপে একই ইনস্টলার চালাতে হয়: `php bin/install.php`।
ইনস্টলারের কাজগুলো হলো:
1. PHP পরীক্ষা করে।
2. ডাটাবেস ও সব টেবিল তৈরি করে।
3. সীমিত অনুমতির একটি ডাটাবেস ইউজার তৈরি করে।
4. নতুন গোপন কী (`APP_KEY`) দিয়ে `.env` ফাইল লেখে।
5. সুপার অ্যাডমিনের পাসওয়ার্ড বসায়।

এর জন্য আলাদাভাবে `mysql` কমান্ড বা phpMyAdmin লাগে না।

---

## প্রয়োজনীয়তা

| | ন্যূনতম |
|---|---|
| PHP | 8.2 বা নতুন। এক্সটেনশন লাগবে: `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `fileinfo` |
| ডাটাবেস | **MySQL 8.0.16 বা নতুন**। MariaDB-তে পরীক্ষা করা হয়নি। |
| ওয়েব সার্ভার | Apache (`mod_rewrite` চালু) অথবা Nginx + PHP-FPM |
| সার্ভারের ক্ষমতা | ২ GB RAM, ২০ GB ডিস্ক হলে ১০–২০টি কেন্দ্র চালানো যায় |

---

## ক) Windows + Laragon (সুপারিশকৃত)

Laragon-এ MySQL 8 থাকে, তাই এটিই সবচেয়ে সহজ পথ।

1. <https://laragon.org> থেকে **Laragon Full** নামিয়ে ইনস্টল করুন। PHP 8.2 বা নতুন থাকা চাই; না থাকলে *Menu → PHP → Version* থেকে যোগ করুন।
2. Laragon খুলে **Start All** চাপুন।
3. প্রজেক্ট ফোল্ডারটি `C:\laragon\www\rtti` হিসেবে রাখুন। ভেতরে `training-institute` ফোল্ডার থাকবে।
4. Laragon-এর **Terminal** বাটন চেপে লিখুন:
   ```bash
   cd C:\laragon\www\rtti\training-institute
   ```
   ```bash
   php bin/install.php
   ```
5. প্রশ্নগুলোর উত্তর দিন:
   - MySQL ইউজার: `root`। পাসওয়ার্ড: Laragon-এ সাধারণত ফাঁকা, তাই শুধু Enter চাপুন।
   - ব্রাউজারের ঠিকানা: `http://localhost/rtti/training-institute/public`
6. ব্রাউজারে ওই ঠিকানা খুলে `superadmin` দিয়ে লগইন করুন।

## খ) Windows + XAMPP

> XAMPP-এ MySQL নয়, **MariaDB** থাকে। সফটওয়্যারটি MySQL 8-এর জন্য তৈরি ও পরীক্ষিত।
> XAMPP-ই ব্যবহার করতে চাইলে প্রথমে পরীক্ষামূলকভাবে চালিয়ে দেখুন। কোনো এরর এলে Laragon ব্যবহার করুন।

1. XAMPP-এর PHP 8.2+ সংস্করণ ইনস্টল করুন। Control Panel থেকে **Apache** ও **MySQL** চালু করুন।
2. `C:\xampp\php\php.ini` ফাইলে নিচের লাইনগুলোর শুরুতে `;` থাকলে মুছে দিন, তারপর Apache রিস্টার্ট করুন:
   `extension=pdo_mysql`, `extension=mbstring`, `extension=openssl`, `extension=sodium`, `extension=fileinfo`
3. প্রজেক্টটি `C:\xampp\htdocs\rtti` ফোল্ডারে রাখুন।
4. Command Prompt খুলে চালান:
   ```bash
   cd C:\xampp\htdocs\rtti\training-institute
   ```
   ```bash
   C:\xampp\php\php.exe bin\install.php
   ```
5. ব্রাউজারে খুলুন: `http://localhost/rtti/training-institute/public`

## গ) Linux সার্ভার (Ubuntu 24.04) — অনলাইনে সব কেন্দ্রের জন্য

```bash
sudo apt update && sudo apt install -y apache2 mysql-server php php-mysql php-mbstring php-xml php-intl php-gd unzip git
```
```bash
sudo mysql_secure_installation
```
```bash
sudo mkdir -p /var/www/rtti && sudo chown $USER /var/www/rtti
```
```bash
git clone https://github.com/rajibrdrs0-ai/rdrs-browser /var/www/rtti
```
```bash
cd /var/www/rtti/training-institute && sudo php bin/install.php --db-host=localhost --db-user=root
```
ইনস্টলার "ব্রাউজারের ঠিকানা" জানতে চাইলে আপনার ডোমেইন লিখুন, যেমন `https://rtti.example.org`।

ফাইলের মালিকানা ও অনুমতি ঠিক করুন:
```bash
sudo chown -R www-data:www-data /var/www/rtti/training-institute/storage && sudo chown www-data /var/www/rtti/training-institute/.env
```

ওয়েব সার্ভার সেট করুন। `deploy/` ফোল্ডারে প্রস্তুত কনফিগ ফাইল আছে:
* **Apache:** `deploy/apache-vhost.conf` ফাইলটি `/etc/apache2/sites-available/rtti.conf` হিসেবে কপি করুন। ডোমেইনের নাম বদলে নিন, তারপর চালান:
  ```bash
  sudo a2enmod rewrite headers && sudo a2ensite rtti && sudo systemctl reload apache2
  ```
* **Nginx:** `deploy/nginx-site.conf` দেখুন।

**HTTPS (বাধ্যতামূলক):** ডোমেইন সার্ভারের দিকে পয়েন্ট করার পর চালান:
```bash
sudo certbot --apache -d rtti.example.org
```

> **গুরুত্বপূর্ণ:** ওয়েব সার্ভারের DocumentRoot হবে শুধু `training-institute/public` ফোল্ডার।
> প্রজেক্টের মূল ফোল্ডার কখনো ওয়েবে খোলা রাখবেন না, কারণ সেখানে `.env` ফাইলে ডাটাবেসের পাসওয়ার্ড থাকে।
> MySQL-এর পোর্ট (3306) ফায়ারওয়াল দিয়ে বাইরে থেকে বন্ধ রাখুন:
> ```bash
> sudo ufw allow OpenSSH && sudo ufw allow 'Apache Full' && sudo ufw enable
> ```

---

## ইনস্টলারের অপশন

প্রশ্ন ছাড়া স্বয়ংক্রিয়ভাবে চালাতে:
```bash
php bin/install.php --yes --db-host=localhost --db-user=root --db-pass='...' --admin-pass='Strong@Pass2026' --app-url=https://rtti.example.org
```

| অপশন | কাজ |
|---|---|
| `--db-host`, `--db-port`, `--db-user`, `--db-pass` | MySQL অ্যাডমিন অ্যাকাউন্ট (ডাটাবেস ও ইউজার তৈরির জন্য) |
| `--db-name` | ডাটাবেসের নাম (ডিফল্ট `rtti_management`) |
| `--app-db-user` | সফটওয়্যারের নিজস্ব সীমিত-অনুমতির ইউজার (ডিফল্ট `rtti_user`; পাসওয়ার্ড নিজে থেকে তৈরি হয়) |
| `--admin-pass` | সুপার অ্যাডমিনের পাসওয়ার্ড বসায় (না দিলে `Admin@12345`, যা প্রথম লগইনে বদলাতে হবে) |
| `--demo` | ডেমো ডাটা লোড করে। **আসল ব্যবহারে দেবেন না।** |
| `--force` | আগের ডাটাবেস **মুছে** নতুন করে ইনস্টল করে। সাবধানে ব্যবহার করুন। |

* আগে থেকে ডাটা আছে এমন ডাটাবেস পেলে ইনস্টলার থেমে যায় এবং নিশ্চিত হতে প্রশ্ন করে। "না" বললে কিছুই বদলায় না।
* পুরনো `.env` ফাইল থাকলে ইনস্টলার মুছে না ফেলে `.env.backup-তারিখ` নামে রেখে দেয়।

---

## ইনস্টলের পরে প্রথম কাজ

1. `superadmin` দিয়ে লগইন করুন। পাসওয়ার্ড না দিয়ে থাকলে এখনই বদলে নিন।
2. **প্রশিক্ষণ কেন্দ্র → প্রধান কার্যালয় → সম্পাদনা** থেকে প্রধান কার্যালয়ের আসল ঠিকানা দিন।
3. **উপজেলা** মেনু থেকে আপনার জেলাগুলোর বাকি উপজেলা যোগ করুন। ডাটাবেসে শুরুতে প্রতিটি জেলার শুধু সদর উপজেলা আছে।
4. প্রতিটি **প্রশিক্ষণ কেন্দ্র** যোগ করুন। প্রতিটির ক্যাশ হিসাব নিজে থেকেই খুলে যাবে।
5. **ট্রেড → কোর্স → ফি কাঠামো** ঠিক করুন।
6. **ব্যবহারকারী** তৈরি করুন: প্রধান কার্যালয়ের অ্যাডমিন, জেলা সমন্বয়কারী, প্রতিটি কেন্দ্রের ব্যবস্থাপক।
   কেন্দ্র ব্যবস্থাপকরা নিজের কেন্দ্রের হিসাবরক্ষক, ভর্তি কর্মকর্তা ও প্রশিক্ষক নিজেরাই তৈরি করতে পারবেন।
7. **কর্মী ও প্রশিক্ষক** যোগ করুন। প্রশিক্ষকের লগইন অ্যাকাউন্ট তাঁর কর্মী রেকর্ডের সাথে যুক্ত করুন।
8. **ব্যাচ** খুলে **ক্লাস রুটিন** সাজান।

## ব্যাকআপ (অবশ্যই চালু করুন)

Linux সার্ভারে প্রতি রাতে ব্যাকআপ নেওয়ার জন্য `deploy/backup.sh` আছে। এটি শেষ ৩০ দিনের ব্যাকআপ রাখে, এবং ট্রিগার ও বাংলা লেখাসহ সব ঠিক থাকে। চালু করার নিয়ম:
```bash
sudo cp deploy/backup.sh /usr/local/bin/rtti-backup && sudo chmod 700 /usr/local/bin/rtti-backup
```
```bash
echo "30 2 * * * root /usr/local/bin/rtti-backup" | sudo tee /etc/cron.d/rtti-backup
```
ব্যাকআপ থেকে ফেরত আনতে:
```bash
gunzip -c /var/backups/rtti/rtti_management-YYYYMMDD-HHMM.sql.gz | mysql rtti_management
```
নিয়মিত ব্যাকআপ ফাইলের একটি কপি সার্ভারের বাইরে রাখুন, যেমন অন্য কম্পিউটার বা ক্লাউড স্টোরেজে।

## হালনাগাদ (নতুন ধাপ এলে)

```bash
cd /var/www/rtti && git pull
```
হালনাগাদের আগে অবশ্যই একটি ব্যাকআপ নিন। ডাটাবেসে পরিবর্তন লাগলে তার জন্য আলাদা মাইগ্রেশন ফাইল দেওয়া হবে।

## সমস্যা হলে

| সমস্যা | সমাধান |
|---|---|
| `Cannot connect to MySQL` | XAMPP/Laragon-এ MySQL চালু আছে কি না দেখুন; ইউজার ও পাসওয়ার্ড ঠিক আছে কি না দেখুন। |
| `Missing PHP extensions` | `php.ini` ফাইলে সংশ্লিষ্ট `extension=` লাইনের শুরুর `;` মুছে Apache রিস্টার্ট করুন। |
| পেজ খোলে কিন্তু ডিজাইন নেই | ওয়েব সার্ভারের রুট অবশ্যই `public/` ফোল্ডার হতে হবে, আর Apache-এ `mod_rewrite` চালু থাকতে হবে। |
| `storage is not writable` | Linux-এ চালান: `sudo chown -R www-data:www-data storage` |
| `/health` পেজে `"database": false` | `.env`-এর `DB_*` মানগুলো দেখুন, অথবা ইনস্টলার আবার চালান। |
| লগইনে "অনেকবার ভুল চেষ্টা" | ১৫ মিনিট অপেক্ষা করুন, অথবা কেন্দ্র ব্যবস্থাপক ব্যবহারকারী তালিকা থেকে অ্যাকাউন্ট আবার সক্রিয় করতে পারেন। |
| অন্য কোনো এরর | `storage/logs/app-তারিখ.log` ফাইলে বিস্তারিত থাকে। এই ফাইলটি পাঠালে সমস্যা খুঁজে বের করা যাবে। |
