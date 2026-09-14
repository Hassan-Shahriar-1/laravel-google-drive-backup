# Laravel Google Drive Backup

[![Tests](https://github.com/Hassan-Shahriar-1/laravel-google-drive-backup/workflows/Tests/badge.svg)](https://github.com/Hassan-Shahriar-1/laravel-google-drive-backup/actions)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-red)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Policy-based Laravel backup management with Google Drive as a backup destination.**

This package provides a focused integration layer that connects Laravel applications to Google Drive for automated backups with configurable multi-tier retention (daily / weekly / monthly), post-upload integrity verification, and a full suite of Artisan commands — all without replacing your existing backup engine.

---

## Features

- ✅ Google OAuth 2.0 authentication with refresh token support
- ✅ Interactive CLI command (`backup:google-drive:refresh-token`) to generate OAuth refresh tokens
- ✅ Chunked, streaming upload for large backup archives (no memory exhaustion)
- ✅ Paginated backup listing from Google Drive
- ✅ Multi-tier retention policy (daily, weekly, monthly) with overlap protection
- ✅ Post-upload verification (`metadata`, `checksum`, or `full-download` modes)
- ✅ Deterministic filename generation with UTC timestamps
- ✅ Automatic temp-file cleanup with `try/finally` safety
- ✅ Laravel events for every lifecycle stage
- ✅ Full Artisan CLI suite
- ✅ 90%+ test coverage with PHPUnit 11 + Orchestra Testbench 10
- ✅ Credential sanitization — secrets are never written to logs or exceptions

---

## Requirements

| Package | Version |
|---------|---------|
| PHP     | ^8.3 \| ^8.4 \| ^8.5 |
| Laravel | >= 10.0 (supports 10.x, 11.x, 12.x, 13.x+) |

---

## Installation

```bash
composer require hassan-shahriar-1/laravel-google-drive-backup
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=google-drive-backup-config
```

---

## Google Drive Setup

See [docs/google-drive-setup.md](docs/google-drive-setup.md) for step-by-step instructions on creating a Google Cloud project, enabling the Drive API, and obtaining an OAuth 2.0 refresh token.

---

## Configuration

Add these variables to your `.env`:

```env
# ── Google OAuth 2.0 Credentials (Required) ─────────────────────────
GOOGLE_DRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token

# ── Target Google Drive Folder (Optional) ───────────────────────────
GOOGLE_DRIVE_BACKUP_FOLDER_ID=                   # Specific folder ID from Drive URL (leave blank to auto-use)
GOOGLE_DRIVE_BACKUP_FOLDER_NAME="Laravel Backups" # Name used if folder ID is empty

# ── Retention Policy Rules (Optional) ───────────────────────────────
GOOGLE_DRIVE_BACKUP_RETENTION_DAILY=30           # Number of daily backups to keep (all intraday for latest day)
GOOGLE_DRIVE_BACKUP_RETENTION_WEEKLY=8           # Number of weekly backups to keep (1 per week)
GOOGLE_DRIVE_BACKUP_RETENTION_MONTHLY=12         # Number of monthly backups to keep (1 per month)

# ── General Settings (Optional) ─────────────────────────────────────
GOOGLE_DRIVE_BACKUP_ENABLED=true                 # Set false to disable backups in local/staging
GOOGLE_DRIVE_BACKUP_VERIFY_MODE=metadata         # metadata | checksum | full-download | none
```

Full configuration reference: [docs/configuration.md](docs/configuration.md)

---

## Quick Start

Test your connection:

```bash
php artisan backup:google-drive:test
```

Run your first backup:

```bash
php artisan backup:google-drive
```

List backups on Google Drive:

```bash
php artisan backup:google-drive:list
```

---

## Database Backups & Supported Drivers

The package natively dumps and restores all major database drivers used in Laravel:

| Driver | Engine | Tool Used |
|---|---|---|
| `mysql` | MySQL | `mysqldump` |
| `mariadb` | MariaDB | `mysqldump` |
| `pgsql` | PostgreSQL | `pg_dump` |
| `sqlite` | SQLite | `sqlite3` (or raw file copy) |
| `sqlsrv` | SQL Server / Azure SQL | `sqlcmd` |

### Database Backup Examples

```bash
# Dump default database (taken automatically from DB_CONNECTION in .env)
php artisan backup:google-drive --db
# or simply (defaults to database backup):
php artisan backup:google-drive

# Dump a specific connection defined in config/database.php:
php artisan backup:google-drive --db --connection=pgsql
php artisan backup:google-drive --db --connection=sqlsrv
php artisan backup:google-drive --db --connection=sqlite

# If --connection is NOT passed, it automatically resolves:
# 1. DB_CONNECTION from .env
# 2. config('database.default')
```

### Database Restore

```bash
# Download and automatically restore database
php artisan backup:google-drive:restore {FILE_ID} --db-restore

# Restore to a specific database connection
php artisan backup:google-drive:restore {FILE_ID} --db-restore --connection=pgsql
```

---

## Subfolder Organization (Folder Separation)

By default, backups are uploaded **directly into the root folder** specified in your `.env` (`GOOGLE_DRIVE_BACKUP_FOLDER_ID` or `GOOGLE_DRIVE_BACKUP_FOLDER_NAME`).

If you want to separate backups into subfolders (e.g. `database/`, `others/`):

### 1. Specify a Subfolder in the Command
```bash
# Upload database backup into "database/" subfolder:
php artisan backup:google-drive --db --subfolder=database

# Upload file backup into "others/" subfolder:
php artisan backup:google-drive --files --subfolder=others

# Upload into nested subfolders (e.g. database/monthly):
php artisan backup:google-drive --db --subfolder=database/monthly

# Dynamic date and type tokens in --subfolder (year, month, day, date, type):
php artisan backup:google-drive --db --subfolder="{year}/{month}"

# If you do NOT pass --subfolder, it uploads directly to your root folder from .env!
php artisan backup:google-drive --db
```

### 2. Auto-organize by Backup Type (`--by-type`)
```bash
# Automatically uploads into "database/", "files/", or "full/" subfolder:
php artisan backup:google-drive --db --by-type
```

### 3. Listing Backups (Root and Subfolders)
```bash
# List all backups across root and all subfolders:
php artisan backup:google-drive:list

# Filter list to only a specific subfolder:
php artisan backup:google-drive:list --subfolder=database

# Clean expired backups in a specific subfolder:
php artisan backup:google-drive:clean --subfolder=database --dry-run
```

---

## Backing Up Specific Folders or Files (`--path`)

By default, `--files` archives your entire application (excluding `vendor`, `node_modules`, `.git`, etc.).
If you only need to back up specific folders (such as uploaded images, documents, or media), use the `--path` option:

```bash
# Back up only the images folder:
php artisan backup:google-drive --files --path="storage/app/public/images"

# Back up only images and store them in an "images" subfolder on Google Drive:
php artisan backup:google-drive --path="storage/app/public/images" --subfolder=images

# Back up multiple folders (comma-separated or repeatable):
php artisan backup:google-drive --files --path="storage/app/public/images,public/uploads"
php artisan backup:google-drive --files --path="storage/app/public" --path="public/uploads"

# Back up specific files:
php artisan backup:google-drive --files --path=".env" --path="composer.json"
```

> **Note:** If `--path` is passed, the backup type automatically defaults to `files` without needing to pass `--files` explicitly.


---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `backup:google-drive:refresh-token` | Interactively generate OAuth refresh token & save to `.env` |
| `backup:google-drive` | Create and upload a backup |
| `backup:google-drive:test` | Test credentials and folder access |
| `backup:google-drive:list` | List all remote backups |
| `backup:google-drive:clean` | Apply retention policy and delete expired backups |
| `backup:google-drive:verify` | Verify remote backup integrity |
| `backup:google-drive:download {id} {destination}` | Download a backup locally |
| `backup:google-drive:restore {id}` | Download and extract a backup for restoration |

Use `--dry-run` with `backup:google-drive:clean` to preview what would be deleted without making changes.

---

## Scheduling

Add to your `routes/console.php` (Laravel 11, 12, 13+):

```php
use Illuminate\Support\Facades\Schedule;

// Run database backup every 4 hours
Schedule::command('backup:google-drive --db')->everyFourHours();

// Run files backup weekly on Sunday
Schedule::command('backup:google-drive --files')->weeklyOn(0, '02:00');

// Automatically clean expired backups once daily at night
Schedule::command('backup:google-drive:clean')->dailyAt('03:00');

// Optionally verify backup integrity
Schedule::command('backup:google-drive:verify --all')->dailyAt('04:00');
```

---

## Retention Policy

The retention engine works automatically like Spatie's cleanup strategy — you do not pass dates or intervals on the command line. It reads timestamps on Google Drive and groups backups by type (`database` vs `files`) so frequent database backups never delete file backups.

```php
// config/google-drive-backup.php
'retention' => [
    'daily'   => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_DAILY', 30),   // keep 30 distinct calendar days (all intraday backups for latest day)
    'weekly'  => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_WEEKLY', 8),   // keep 8 distinct weeks of backups
    'monthly' => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_MONTHLY', 12), // keep 12 distinct months of backups
],
```

A backup that qualifies for multiple retention tiers (e.g. both daily and weekly) is **never deleted** until it expires from all applicable tiers.

---

## Events

Listen to backup lifecycle events in your application:

```php
use HassanShahriar\GoogleDriveBackup\Events\BackupFailed;
use HassanShahriar\GoogleDriveBackup\Events\BackupUploaded;

Event::listen(BackupUploaded::class, function ($event) {
    // Notify your team, log metrics, etc.
});

Event::listen(BackupFailed::class, function ($event) {
    // Send an alert
});
```

Available events: `BackupStarted`, `BackupCreated`, `BackupUploaded`, `BackupVerified`, `BackupFailed`, `BackupDeleted`, `CleanupCompleted`.

---

## Testing

```bash
vendor/bin/phpunit
```

---

## Security

- OAuth tokens are never written to logs or stack traces.
- Temporary backup files are always deleted after use.
- Filenames are sanitized against path-traversal attacks.
- See [SECURITY.md](SECURITY.md) for the vulnerability reporting policy.

---

## Documentation

| Topic | Link |
|-------|------|
| Installation | [docs/installation.md](docs/installation.md) |
| Google Drive Setup | [docs/google-drive-setup.md](docs/google-drive-setup.md) |
| Configuration | [docs/configuration.md](docs/configuration.md) |
| Retention Policy | [docs/retention.md](docs/retention.md) |
| Commands | [docs/commands.md](docs/commands.md) |
| Scheduling | [docs/scheduling.md](docs/scheduling.md) |
| Restoration | [docs/restoration.md](docs/restoration.md) |
| Security | [docs/security.md](docs/security.md) |
| Troubleshooting | [docs/troubleshooting.md](docs/troubleshooting.md) |

---

## Compatibility

| Package | Laravel | PHP  |
|---------|---------|------|
| 2.x     | 10.x / 11.x / 12.x / 13.x+ | 8.3+ |

---

## License

MIT © Hassan Shahriar — see [LICENSE](LICENSE).

