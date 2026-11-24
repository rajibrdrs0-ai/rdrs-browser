# Backup and restore

## From the application
*Administration → System Settings → Database backup → **Backup now*** creates `storage/backups/rtti_YYYYMMDD_HHMMSS_manual.sql.gz`.
Download it with the download button and keep copies **off the server** (external disk, Google Drive, etc.).
Backups older than *Keep backups for (days)* are deleted automatically.

**Restore** (super administrator only): click the restore icon next to a backup and type `RESTORE`.
The system first takes a safety backup (`…_beforerestore`), then replaces the whole database.
To restore a file from elsewhere, copy it into `storage/backups/` with a name like `rtti_20261124_120000_external.sql.gz`.

## Scheduled backup (recommended: daily)
Linux cron (02:30 every night):
```
30 2 * * * cd /var/www/rtti && php -r 'require "app/Core/bootstrap.php"; echo App\Services\Backup::create("auto");' >> storage/logs/backup.log 2>&1
```
Windows Task Scheduler: action `C:\xampp\php\php.exe`, arguments
`-r "require 'app/Core/bootstrap.php'; App\Services\Backup::create('auto');"`, start in `C:\xampp\htdocs\rtti`.

## With mysqldump
```
mysqldump -u root -p --single-transaction --routines rtti_training_management > rtti_backup.sql
mysql -u root -p rtti_training_management < rtti_backup.sql
```

## Also back up files
Copy `storage/uploads/` (photos, documents, bills) and `config/config.php` together with the database.
