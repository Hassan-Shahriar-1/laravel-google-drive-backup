<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Drive Backup Status
    |--------------------------------------------------------------------------
    |
    | Enables or disables the backup execution globally. Useful for preventing
    | accidental runs in testing, CI/CD, or local staging environments.
    |
    | Use Case: Set to false in .env.testing or staging to suppress backups.
    |
    */
    'enabled' => (bool) env('GOOGLE_DRIVE_BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Application & Environment Identifiers
    |--------------------------------------------------------------------------
    |
    | Used in generated backup filenames and Google Drive folder naming.
    |
    | Use Cases:
    | - application_name: Distinguish multiple apps backing up to the same Drive.
    | - environment: Label archives by stage (e.g. production, staging).
    |
    */
    'application_name' => env('GOOGLE_DRIVE_BACKUP_APP_NAME', env('APP_NAME', 'laravel')),

    'environment' => env('GOOGLE_DRIVE_BACKUP_ENV', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Database Connection & Default Backup Type
    |--------------------------------------------------------------------------
    |
    | database_connection: The database connection to dump.
    | Automatically defaults to standard DB_CONNECTION from .env or
    | config('database.default'). Can be overridden with --connection=<name>.
    | Supported drivers: mysql, mariadb, pgsql, sqlite, sqlsrv.
    |
    | default_type: Default mode when none of --db, --files, or --full is passed.
    | Default: 'database'.
    |
    */
    'database_connection' => env('DB_CONNECTION'),

    'default_type' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Google Drive Credentials & Root Folder
    |--------------------------------------------------------------------------
    |
    | The OAuth 2.0 credentials and destination folder on Google Drive.
    |
    | Use Cases:
    | - client_id, client_secret: From Google Cloud Console OAuth 2.0 Client.
    | - refresh_token: Generated via `php artisan backup:google-drive:refresh-token`.
    | - folder_id: Direct ID from Google Drive URL (leave empty to auto-create).
    | - folder_name: Name of auto-created folder if folder_id is not specified.
    |
    */
    'google' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),
        'folder_name' => env('GOOGLE_DRIVE_BACKUP_FOLDER_NAME', 'Laravel Backups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filename Strategy
    |--------------------------------------------------------------------------
    |
    | Template format for naming backup archive files.
    | Available tokens: {app}, {env}, {type}, {date}, {time}, {uuid}
    |
    | Use Case: Customize file naming pattern to comply with backup conventions.
    |
    */
    'filename' => [
        'format' => env('GOOGLE_DRIVE_BACKUP_FILENAME_FORMAT', '{app}-{env}-{type}-{date}-{time}-{uuid}.zip'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Scope Defaults
    |--------------------------------------------------------------------------
    |
    | Configure whether database dumps and application files are included by default.
    |
    */
    'backup' => [
        'database' => true,
        'files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-Upload Verification Strategy
    |--------------------------------------------------------------------------
    |
    | Verification mode applied immediately after upload:
    | - 'none': Skip post-upload check.
    | - 'metadata': Verify file exists on Drive, is non-empty, and matches file size.
    | - 'checksum': Verify Google Drive MD5 checksum against local MD5.
    | - 'full-download': Download and verify full archive integrity.
    |
    */
    'verification' => [
        'enabled' => true,
        'mode' => env('GOOGLE_DRIVE_BACKUP_VERIFY_MODE', 'metadata'),
        'checksum' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy Rules
    |--------------------------------------------------------------------------
    |
    | Multi-tier retention rules evaluated automatically by `backup:google-drive:clean`.
    |
    | How It Works (Spatie-style automatic cleanup):
    | - Backups are evaluated by type independently (database backups won't delete file backups).
    | - Intra-day backups (e.g. every 4 hours) for the most recent day are all kept.
    | - Daily: Retains 1 backup per distinct calendar day for up to N days.
    | - Weekly: Retains 1 backup per ISO calendar week for up to N weeks.
    | - Monthly: Retains 1 backup per calendar month for up to N months.
    |
    | Use Case: Keep 30 days of daily backups, 8 weeks of weekly, 12 months of monthly.
    |
    */
    'retention' => [
        'daily'   => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_DAILY', 30),
        'weekly'  => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_WEEKLY', 8),
        'monthly' => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_MONTHLY', 12),
    ],

];
