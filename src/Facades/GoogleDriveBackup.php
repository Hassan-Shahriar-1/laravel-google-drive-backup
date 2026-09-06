<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string version()
 * @method static bool isEnabled()
 *
 * @see \HassanShahriar\GoogleDriveBackup\GoogleDriveBackupManager
 */
class GoogleDriveBackup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'google-drive-backup';
    }
}
