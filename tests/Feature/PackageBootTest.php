<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Feature;

use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class PackageBootTest extends TestCase
{
    public function test_package_can_boot_in_laravel_application(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(\HassanShahriar\GoogleDriveBackup\GoogleDriveBackupServiceProvider::class));
    }
}
