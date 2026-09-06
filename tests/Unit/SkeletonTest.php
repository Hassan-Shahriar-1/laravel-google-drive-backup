<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use HassanShahriar\GoogleDriveBackup\Facades\GoogleDriveBackup;
use HassanShahriar\GoogleDriveBackup\GoogleDriveBackupManager;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class SkeletonTest extends TestCase
{
    public function test_service_provider_binds_manager_correctly(): void
    {
        $manager = $this->app->make('google-drive-backup');

        $this->assertInstanceOf(GoogleDriveBackupManager::class, $manager);
    }

    public function test_facade_resolves_correctly(): void
    {
        $this->assertSame('1.0.0', GoogleDriveBackup::version());
        $this->assertTrue(GoogleDriveBackup::isEnabled());
    }

    public function test_config_merges_successfully(): void
    {
        $this->assertSame('test-app', config('google-drive-backup.application_name'));
        $this->assertSame('testing', config('google-drive-backup.environment'));
        $this->assertSame(30, config('google-drive-backup.retention.daily'));
    }
}
