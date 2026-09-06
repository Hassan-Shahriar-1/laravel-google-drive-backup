<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google Drive Backup Status
    |--------------------------------------------------------------------------
    |
    | Enables or disables the backup execution. Useful for preventing
    | accidental runs in testing or development environments.
    |
    */
    'enabled' => env('GOOGLE_DRIVE_BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Application & Environment Identifiers
    |--------------------------------------------------------------------------
    |
    | Used to namespace backups on Google Drive and generate unique filenames.
    |
    */
    'application_name' => env('GOOGLE_DRIVE_BACKUP_APP_NAME', env('APP_NAME', 'laravel')),

    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Google Drive Credentials & Root Folder
    |--------------------------------------------------------------------------
    |
    | The OAuth 2.0 credentials and destination folder ID for backups.
    |
    */
    'google' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),
        'folder_name' => env('GOOGLE_DRIVE_BACKUP_FOLDER_NAME', 'Laravel Backups'),
        'shared_drive_id' => env('GOOGLE_DRIVE_SHARED_DRIVE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filename Strategy
    |--------------------------------------------------------------------------
    |
    | Template format for naming backup zip files.
    | Available tokens: {app}, {env}, {type}, {date}, {time}, {uuid}
    |
    */
    'filename' => [
        'format' => '{app}-{env}-{type}-{date}-{time}-{uuid}.zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Scope
    |--------------------------------------------------------------------------
    |
    | Configure whether database dumps and/or application files are included.
    |
    */
    'backup' => [
        'database' => true,
        'files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression & Encryption
    |--------------------------------------------------------------------------
    |
    | Backup files are compressed as ZIP archives. Optional AES encryption
    | can be enabled for sensitive environments.
    |
    */
    'compression' => [
        'enabled' => true,
        'format' => 'zip',
    ],

    'encryption' => [
        'enabled' => env('GOOGLE_DRIVE_BACKUP_ENCRYPTION_ENABLED', false),
        'cipher' => 'aes-256-cbc',
        'key' => env('GOOGLE_DRIVE_BACKUP_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Strategy
    |--------------------------------------------------------------------------
    |
    | Post-upload verification mode. Options: 'none', 'metadata', 'checksum', 'full-download'
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
    | Specify how many daily, weekly, and monthly backups to retain.
    |
    */
    'retention' => [
        'daily' => 30,
        'weekly' => 8,
        'monthly' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Channel
    |--------------------------------------------------------------------------
    |
    | Logging configuration. Sensitive credentials will always be redacted.
    |
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('GOOGLE_DRIVE_BACKUP_LOG_CHANNEL', 'stack'),
    ],

];
