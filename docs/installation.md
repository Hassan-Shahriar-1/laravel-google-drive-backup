# Installation

## Requirements

- PHP 8.3 or higher
- Laravel 11 or 12
- Composer
- A Google Cloud project with Drive API enabled

## Install via Composer

```bash
composer require hassan-shahriar-1/laravel-google-drive-backup
```

Laravel will auto-discover the service provider via the `extra.laravel` section in `composer.json`.

## Publish Configuration

```bash
php artisan vendor:publish --tag=google-drive-backup-config
```

This creates `config/google-drive-backup.php` in your application.

## Next Steps

1. Follow [google-drive-setup.md](google-drive-setup.md) to obtain OAuth credentials.
2. Add credentials to your `.env` file.
3. Run `php artisan backup:google-drive:test` to verify connectivity.
4. Run `php artisan backup:google-drive` to create your first backup.
