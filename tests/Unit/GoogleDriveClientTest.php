<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use Google\Client as GoogleClient;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveAuthenticationException;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class GoogleDriveClientTest extends TestCase
{
    public function test_throws_exception_when_credentials_are_missing(): void
    {
        $this->expectException(GoogleDriveAuthenticationException::class);
        $this->expectExceptionMessage('Missing Google Drive OAuth credentials');

        $client = new GoogleDriveClient([
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',
        ]);

        $client->getClient();
    }

    public function test_test_connection_returns_error_on_invalid_credentials(): void
    {
        $client = new GoogleDriveClient([
            'client_id' => 'invalid_client_id',
            'client_secret' => 'invalid_secret',
            'refresh_token' => 'invalid_refresh_token',
        ]);

        $result = $client->testConnection();

        $this->assertFalse($result['authenticated']);
        $this->assertNotNull($result['error']);
    }
}
