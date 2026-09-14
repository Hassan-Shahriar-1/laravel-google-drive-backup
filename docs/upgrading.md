# Upgrading Guide

This guide covers how to update `laravel-google-drive-backup` in your Laravel application.

## General Upgrade Steps

To update the package to the latest release at any time:

### 1. Update the Package via Composer

Run:
```bash
composer update hassan-shahriar-1/laravel-google-drive-backup
```

Or to pull the latest release:
```bash
composer require hassan-shahriar-1/laravel-google-drive-backup
```

### 2. Update Configuration (If Needed)

If a release introduced new configuration keys or updated default settings, re-publish the configuration file:
```bash
php artisan vendor:publish --tag=google-drive-backup-config --force
```

> **Note:** If you have custom edits in `config/google-drive-backup.php`, review the changes or back up your file before using `--force`.

### 3. Clear Configuration Cache

Whenever updating packages or publishing configuration changes in Laravel, clear the cached configuration:
```bash
php artisan config:clear
```

### 4. Review Release Notes

Check [CHANGELOG.md](../CHANGELOG.md) or the GitHub Releases page for specific details on each release, new features, and bug fixes.
