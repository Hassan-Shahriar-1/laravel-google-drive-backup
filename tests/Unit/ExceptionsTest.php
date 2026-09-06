<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveAuthenticationException;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_exceptions_redact_sensitive_tokens_in_messages(): void
    {
        $rawMessage = 'Error connecting with client_secret=super_secret_key&refresh_token=1//abc123secret and access_token=ya29.xyz';
        $exception = new GoogleDriveAuthenticationException($rawMessage);

        $this->assertStringNotContainsString('super_secret_key', $exception->getMessage());
        $this->assertStringNotContainsString('1//abc123secret', $exception->getMessage());
        $this->assertStringNotContainsString('ya29.xyz', $exception->getMessage());

        $this->assertStringContainsString('client_secret=[REDACTED]', $exception->getMessage());
        $this->assertStringContainsString('refresh_token=[REDACTED]', $exception->getMessage());
        $this->assertStringContainsString('access_token=[REDACTED]', $exception->getMessage());
    }
}
