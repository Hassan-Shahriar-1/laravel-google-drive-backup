<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup;

use Illuminate\Contracts\Foundation\Application;

class GoogleDriveBackupManager
{
    public function __construct(
        protected Application $app
    ) {}

    /**
     * Get package version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Check if backups are enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('google-drive-backup.enabled', true);
    }
}
