# Upgrading

## Upgrading to 2.x

Version 2.x introduces direct `--db`, `--files`, and `--full` flags, dynamic path targeting via `--path`, automatic database connection detection (including SQL Server `sqlsrv`), and multi-tier calendar retention.

## General Upgrade Steps

1. Update your `composer.json` constraint:
   ```bash
   composer require hassan-shahriar-1/laravel-google-drive-backup:^2.0
   ```
2. Re-publish the configuration if new options were added:
   ```bash
   php artisan vendor:publish --tag=google-drive-backup-config --force
   ```
3. Review the [CHANGELOG](../CHANGELOG.md) for any breaking changes.
4. Run your test suite.

