# Laravel Google Drive Backup

[![Tests](https://github.com/Hassan-Shahriar-1/laravel-google-drive-backup/workflows/Tests/badge.svg)](https://github.com/Hassan-Shahriar-1/laravel-google-drive-backup/actions)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%2F12-red)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Policy-based Laravel backup management with Google Drive as a backup destination.**

This package provides a focused integration layer that connects Laravel applications to Google Drive for automated backups with configurable multi-tier retention (daily / weekly / monthly), post-upload integrity verification, and a full suite of Artisan commands — all without replacing your existing backup engine.

---

## Features

- ✅ Google OAuth 2.0 authentication with refresh token support
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
| PHP     | ^8.3    |
| Laravel | ^11.0 \| ^12.0 |

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
GOOGLE_DRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REFRESH_TOKEN=your-refresh-token
GOOGLE_DRIVE_BACKUP_FOLDER_ID=your-folder-id   # optional, created automatically if omitted
GOOGLE_DRIVE_BACKUP_FOLDER_NAME="Laravel Backups"
GOOGLE_DRIVE_BACKUP_ENABLED=true
GOOGLE_DRIVE_BACKUP_LOG_CHANNEL=stack
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

## Artisan Commands

| Command | Description |
|---------|-------------|
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

Add to your `routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:google-drive')->dailyAt('02:00');
Schedule::command('backup:google-drive:clean')->dailyAt('03:00');
Schedule::command('backup:google-drive:verify')->dailyAt('04:00');
```

---

## Retention Policy

```php
// config/google-drive-backup.php
'retention' => [
    'daily'   => 30,   // keep 30 daily backups
    'weekly'  => 8,    // keep 8 weekly backups (one per week)
    'monthly' => 12,   // keep 12 monthly backups (one per month)
],
```

A backup that qualifies for both daily and weekly retention is **not deleted** until it expires from all applicable categories.

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
| 1.x     | 11.x / 12.x | 8.3+ |

---

## License

MIT © Hassan Shahriar — see [LICENSE](LICENSE).

