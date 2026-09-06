<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup;

use Illuminate\Support\ServiceProvider;

class GoogleDriveBackupServiceProvider extends ServiceProvider
{
    /**
     * Register package services and merge configuration.
     */
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

    /**
     * Bootstrap package services, assets, and publishable config.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/google-drive-backup.php' => config_path('google-drive-backup.php'),
            ], 'google-drive-backup-config');

            $this->registerCommands();
        }
    }

    /**
     * Register the Artisan commands for Google Drive Backup.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \HassanShahriar\GoogleDriveBackup\Commands\BackupTestCommand::class,
        ]);
    }
}
