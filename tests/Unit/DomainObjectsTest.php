<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use DateTimeImmutable;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\BackupMetadata;
use HassanShahriar\GoogleDriveBackup\Domain\RetentionResult;
use HassanShahriar\GoogleDriveBackup\Domain\StorageResult;
use HassanShahriar\GoogleDriveBackup\Domain\VerificationResult;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class DomainObjectsTest extends TestCase
{
    public function test_backup_metadata_serialization_and_deserialization(): void
    {
        $metadata = new BackupMetadata(
            package: 'hassan-shahriar-1/laravel-google-drive-backup',
            packageVersion: '1.0.0',
            application: 'my-app',
            environment: 'production',
            type: 'full',
            createdAt: new DateTimeImmutable('2026-09-06T12:00:00Z'),
            size: 1048576,
            checksumAlgorithm: 'sha256',
            checksum: 'test-hash',
            policy: 'daily-policy',
            encrypted: true
        );

        $json = $metadata->toJson();
        $this->assertJson($json);

        $restored = BackupMetadata::from($json);
        $this->assertSame('my-app', $restored->application);
        $this->assertSame('production', $restored->environment);
        $this->assertSame('full', $restored->type);
        $this->assertSame(1048576, $restored->size);
        $this->assertSame('test-hash', $restored->checksum);
        $this->assertSame('daily-policy', $restored->policy);
        $this->assertTrue($restored->encrypted);
    }

    public function test_backup_artifact_properties_and_helpers(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_art_');
        file_put_contents($tempFile, 'dummy backup data');

        $artifact = new BackupArtifact(
            id: 'backup-123',
            filename: 'test-backup.zip',
            path: $tempFile,
            type: 'database',
            size: 1024 * 1024 * 5 // 5 MB
        );

        $this->assertTrue($artifact->existsLocally());
        $this->assertSame('5 MB', $artifact->formattedSize());

        $checksum = $artifact->calculateChecksum('sha256');
        $this->assertNotEmpty($checksum);
        $this->assertSame(hash_file('sha256', $tempFile), $checksum);

        unlink($tempFile);
    }

    public function test_storage_result_factory_methods(): void
    {
        $success = StorageResult::success('file-id-123', 'backup.zip', 2048, 'hash123');
        $this->assertTrue($success->success);
        $this->assertSame('file-id-123', $success->fileId);
        $this->assertNull($success->error);

        $failure = StorageResult::failure('Quota exceeded', 'backup.zip');
        $this->assertFalse($failure->success);
        $this->assertSame('Quota exceeded', $failure->error);
    }

    public function test_verification_result_factory_methods(): void
    {
        $pass = VerificationResult::pass('metadata', 'file-id-123', ['size' => 100]);
        $this->assertTrue($pass->passed);
        $this->assertSame('metadata', $pass->mode);

        $fail = VerificationResult::fail('checksum', 'Checksum mismatch', 'file-id-123');
        $this->assertFalse($fail->passed);
        $this->assertSame('Checksum mismatch', $fail->error);
    }

    public function test_retention_result_counts(): void
    {
        $artifact1 = new BackupArtifact('1', 'a.zip');
        $artifact2 = new BackupArtifact('2', 'b.zip');

        $result = new RetentionResult(
            kept: [$artifact1],
            toDelete: [$artifact2]
        );

        $this->assertSame(1, $result->countKept());
        $this->assertSame(1, $result->countToDelete());
    }
}
