<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Feature;

use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class BackupTestCommandTest extends TestCase
{
    public function test_command_fails_when_credentials_not_configured(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive:test')
            ->expectsOutputToContain('Missing Google OAuth credentials')
            ->assertFailed();
    }

    public function test_command_fails_when_auth_fails(): void
    {
        config()->set('google-drive-backup.google.client_id', 'dummy_id');
        config()->set('google-drive-backup.google.client_secret', 'dummy_secret');
        config()->set('google-drive-backup.google.refresh_token', 'dummy_refresh');

        $this->artisan('backup:google-drive:test')
            ->expectsOutputToContain('OAuth credentials found in configuration')
            ->expectsOutputToContain('Authentication failed')
            ->assertFailed();
    }
}
