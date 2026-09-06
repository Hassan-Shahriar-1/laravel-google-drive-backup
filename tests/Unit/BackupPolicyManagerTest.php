<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use HassanShahriar\GoogleDriveBackup\Exceptions\BackupPolicyException;
use HassanShahriar\GoogleDriveBackup\Policy\BackupPolicyManager;
use HassanShahriar\GoogleDriveBackup\Policy\DefaultBackupPolicy;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class BackupPolicyManagerTest extends TestCase
{
    public function test_resolves_default_policy(): void
    {
        $manager = new BackupPolicyManager(['retention' => ['daily' => 30, 'weekly' => 8, 'monthly' => 12]]);
        $policy  = $manager->resolve('default');

        $this->assertSame('default', $policy->name());
        $this->assertSame('full', $policy->type());
        $this->assertSame(['daily' => 30, 'weekly' => 8, 'monthly' => 12], $policy->retentionRules());
        $this->assertTrue($policy->requiresVerification());
    }

    public function test_throws_on_unknown_policy(): void
    {
        $this->expectException(BackupPolicyException::class);
        $this->expectExceptionMessage('Backup policy [nonexistent] is not registered');

        $manager = new BackupPolicyManager();
        $manager->resolve('nonexistent');
    }

    public function test_registers_and_resolves_custom_policy(): void
    {
        $manager = new BackupPolicyManager();
        $custom  = new DefaultBackupPolicy('nightly', 'database', ['daily' => 7, 'weekly' => 4, 'monthly' => 3], false);
        $manager->register($custom);

        $resolved = $manager->resolve('nightly');
        $this->assertSame('nightly', $resolved->name());
        $this->assertSame('database', $resolved->type());
        $this->assertFalse($resolved->requiresVerification());
    }

    public function test_lists_all_registered_policies(): void
    {
        $manager = new BackupPolicyManager();
        $this->assertArrayHasKey('default', $manager->all());
    }
}
