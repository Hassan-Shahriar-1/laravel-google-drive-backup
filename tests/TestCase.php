<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests;

use HassanShahriar\GoogleDriveBackup\GoogleDriveBackupServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            GoogleDriveBackupServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        // Setup default configuration for tests
        $app['config']->set('google-drive-backup.enabled', true);
        $app['config']->set('google-drive-backup.application_name', 'test-app');
        $app['config']->set('google-drive-backup.environment', 'testing');
    }
}
