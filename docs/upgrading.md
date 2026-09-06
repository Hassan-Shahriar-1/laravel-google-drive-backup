# Upgrading

## From 1.x to 2.x (future)

No breaking changes are planned for 1.x. This guide will be updated when a major version is released.

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
