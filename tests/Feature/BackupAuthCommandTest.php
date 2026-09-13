<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Feature;

use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class BackupAuthCommandTest extends TestCase
{
    public function test_fails_when_client_id_is_empty(): void
    {
        $this->artisan('backup:google-drive:refresh-token')
            ->expectsQuestion('Enter your Google OAuth Client ID', '')
            ->expectsOutputToContain('Client ID is required')
            ->assertFailed();
    }

    public function test_fails_when_client_secret_is_empty(): void
    {
        $this->artisan('backup:google-drive:refresh-token', ['--client-id' => 'test-client-id'])
            ->expectsQuestion('Enter your Google OAuth Client Secret', '')
            ->expectsOutputToContain('Client Secret is required')
            ->assertFailed();
    }

    public function test_alias_token_command_also_works(): void
    {
        $this->artisan('backup:google-drive:token')
            ->expectsQuestion('Enter your Google OAuth Client ID', '')
            ->expectsOutputToContain('Client ID is required')
            ->assertFailed();
    }

    public function test_auto_detects_credentials_from_config(): void
    {
        config()->set('google-drive-backup.google.client_id', 'my-client-id-123456');
        config()->set('google-drive-backup.google.client_secret', 'my-secret');

        $this->artisan('backup:google-drive:refresh-token')
            ->expectsOutputToContain('Found Client ID from .env / config')
            ->expectsOutputToContain('Found Client Secret from .env / config')
            ->expectsQuestion('Paste the authorization code here', '')
            ->assertFailed();
    }
}
