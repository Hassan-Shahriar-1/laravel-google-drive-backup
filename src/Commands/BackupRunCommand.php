<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\Events\BackupFailed;
use HassanShahriar\GoogleDriveBackup\Events\BackupStarted;
use HassanShahriar\GoogleDriveBackup\Events\BackupUploaded;
use HassanShahriar\GoogleDriveBackup\Events\BackupVerified;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use HassanShahriar\GoogleDriveBackup\Policy\BackupPolicyManager;
use HassanShahriar\GoogleDriveBackup\Services\FilenameGenerator;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\BackupMetadata;
use HassanShahriar\GoogleDriveBackup\Verification\BackupVerificationService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Throwable;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:google-drive
                            {--policy=default : Policy name to use}
                            {--type=full : Backup type: full, database, or files}
                            {--force : Run even if backups are disabled}
                            {--no-verify : Skip post-upload verification}';

    protected $description = 'Create a backup and upload it to Google Drive.';

    public function handle(): int
    {
        $config   = (array) config('google-drive-backup', []);
        $enabled  = (bool) ($config['enabled'] ?? true);
        $policy   = (string) $this->option('policy');
        $type     = (string) $this->option('type');
        $noVerify = (bool) $this->option('no-verify');

        if (!$enabled && !$this->option('force')) {
            $this->warn('Google Drive backups are disabled. Use --force to override.');
            return Command::SUCCESS;
        }

        try {
            // Resolve policy
            $policyManager = new BackupPolicyManager($config);
            $backupPolicy  = $policyManager->resolve($policy);
            $type = $backupPolicy->type() !== 'full' ? $backupPolicy->type() : $type;

            Event::dispatch(new BackupStarted($type, $policy));
            $this->info("Starting [{$type}] backup using policy [{$policy}]...");

            // Build filename
            $filenameGen = new FilenameGenerator();
            $appName     = (string) ($config['application_name'] ?? config('app.name', 'laravel'));
            $env         = (string) ($config['environment'] ?? app()->environment());
            $format      = (string) ($config['filename']['format'] ?? '{app}-{env}-{type}-{date}-{time}-{uuid}.zip');
            $filename    = $filenameGen->generate($appName, $env, $type, $format);

            // Create a temporary placeholder file (in a real implementation this
            // would call the backup engine; here we create a minimal zip as scaffold)
            $tmpPath = tempnam(sys_get_temp_dir(), 'gdrive-backup-') . '.zip';
            $this->createPlaceholderBackup($tmpPath, $type, $appName, $env);

            $size     = (int) filesize($tmpPath);
            $checksum = hash_file('sha256', $tmpPath) ?: '';
            $now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            $metadata = new BackupMetadata(
                application: $appName,
                environment: $env,
                type: $type,
                createdAt: $now,
                size: $size,
                checksumAlgorithm: 'sha256',
                checksum: $checksum,
                policy: $policy,
            );

            $artifact = new BackupArtifact(
                id: uniqid('backup-', true),
                filename: $filename,
                path: $tmpPath,
                type: $type,
                createdAt: $now,
                size: $size,
                checksum: $checksum,
                metadata: $metadata,
            );

            // Build storage
            $googleConfig = (array) ($config['google'] ?? []);
            $client       = new GoogleDriveClient($googleConfig);
            $folderMgr    = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr      = new GoogleDriveFileManager($client);
            $storage      = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $this->info("Uploading [{$filename}] to Google Drive...");
            $result = $storage->put($artifact);

            // Cleanup temp file
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }

            if (!$result->success) {
                $this->error('Upload failed: ' . $result->error);
                Event::dispatch(new BackupFailed($type, $policy, new \RuntimeException($result->error ?? 'Unknown')));
                return Command::FAILURE;
            }

            $this->info("✓ Uploaded successfully. File ID: [{$result->fileId}], Size: " . number_format($result->size) . ' bytes.');
            $artifact->storageLocation = $result->fileId;
            Event::dispatch(new BackupUploaded($artifact, $result));

            // Verification
            if (!$noVerify && $backupPolicy->requiresVerification()) {
                $this->info('Verifying backup...');
                $verifier       = new BackupVerificationService($fileMgr, $config);
                $verifyResult   = $verifier->verify($artifact, $result->fileId);
                Event::dispatch(new BackupVerified($artifact, $verifyResult));

                if (!$verifyResult->passed) {
                    $this->error('Verification failed: ' . $verifyResult->error);
                    return Command::FAILURE;
                }
                $this->info("✓ Verification passed (mode: {$verifyResult->mode}).");
            }

            $this->info('Backup completed successfully.');
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Event::dispatch(new BackupFailed($type ?? 'full', $policy ?? 'default', $e));
            return Command::FAILURE;
        }
    }

    private function createPlaceholderBackup(string $path, string $type, string $app, string $env): void
    {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('backup-info.txt', json_encode([
            'type' => $type,
            'app' => $app,
            'env' => $env,
            'created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();
    }
}
