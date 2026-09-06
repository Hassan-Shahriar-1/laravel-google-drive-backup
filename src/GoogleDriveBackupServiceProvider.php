<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup;

use HassanShahriar\GoogleDriveBackup\Commands\BackupCleanCommand;
use HassanShahriar\GoogleDriveBackup\Commands\BackupDownloadCommand;
use HassanShahriar\GoogleDriveBackup\Commands\BackupListCommand;
use HassanShahriar\GoogleDriveBackup\Commands\BackupRestoreCommand;
use HassanShahriar\GoogleDriveBackup\Commands\BackupRunCommand;
use HassanShahriar\GoogleDriveBackup\Commands\BackupTestCommand;
use HassanShahriar\GoogleDriveBackup\Commands\BackupVerifyCommand;
use Illuminate\Support\ServiceProvider;

class GoogleDriveBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/google-drive-backup.php',
            'google-drive-backup'
        );

        $this->app->singleton('google-drive-backup', function ($app) {
            return new GoogleDriveBackupManager($app);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/google-drive-backup.php' => config_path('google-drive-backup.php'),
            ], 'google-drive-backup-config');

            $this->registerCommands();
        }
    }

    protected function registerCommands(): void
    {
        $this->commands([
            BackupRunCommand::class,
            BackupTestCommand::class,
            BackupListCommand::class,
            BackupCleanCommand::class,
            BackupVerifyCommand::class,
            BackupDownloadCommand::class,
            BackupRestoreCommand::class,
        ]);
    }
}
