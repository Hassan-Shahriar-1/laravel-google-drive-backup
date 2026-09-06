<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Feature;

use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    public function test_backup_run_command_fails_gracefully_without_credentials(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive')
            ->assertFailed();
    }

    public function test_backup_disabled_skips_without_force(): void
    {
        config()->set('google-drive-backup.enabled', false);

        $this->artisan('backup:google-drive')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_backup_run_with_invalid_policy_fails(): void
    {
        config()->set('google-drive-backup.google.client_id', 'id');
        config()->set('google-drive-backup.google.client_secret', 'secret');
        config()->set('google-drive-backup.google.refresh_token', 'token');

        $this->artisan('backup:google-drive', ['--policy' => 'nonexistent'])
            ->assertFailed();
    }

    public function test_backup_list_command_fails_gracefully_without_credentials(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive:list')
            ->assertFailed();
    }

    public function test_backup_clean_dry_run_requires_no_confirmation(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        // Will fail at connection stage, not at confirmation stage
        $this->artisan('backup:google-drive:clean', ['--dry-run' => true])
            ->assertFailed();
    }

    public function test_backup_verify_command_requires_id_or_all_option(): void
    {
        $this->artisan('backup:google-drive:verify')
            ->expectsOutputToContain('--id')
            ->assertFailed();
    }

    public function test_backup_test_command_fails_without_credentials(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive:test')
            ->expectsOutputToContain('Missing Google OAuth credentials')
            ->assertFailed();
    }
}
