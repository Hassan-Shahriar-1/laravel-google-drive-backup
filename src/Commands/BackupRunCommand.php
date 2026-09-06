<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use DateTimeImmutable;
use DateTimeZone;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\BackupMetadata;
use HassanShahriar\GoogleDriveBackup\Events\BackupFailed;
use HassanShahriar\GoogleDriveBackup\Events\BackupStarted;
use HassanShahriar\GoogleDriveBackup\Events\BackupUploaded;
use HassanShahriar\GoogleDriveBackup\Events\BackupVerified;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use HassanShahriar\GoogleDriveBackup\Policy\BackupPolicyManager;
use HassanShahriar\GoogleDriveBackup\Services\DatabaseDumper;
use HassanShahriar\GoogleDriveBackup\Services\FilenameGenerator;
use HassanShahriar\GoogleDriveBackup\Services\TemporaryFileManager;
use HassanShahriar\GoogleDriveBackup\Verification\BackupVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Throwable;
use ZipArchive;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:google-drive
                            {--policy=default    : Policy name to use}
                            {--type=full         : Backup type: full | database | files}
                            {--connection=       : Database connection to dump (default: DB_CONNECTION)}
                            {--force             : Run even if backups are disabled}
                            {--no-verify         : Skip post-upload verification}';

    protected $description = 'Create a backup and upload it to Google Drive.';

    public function handle(): int
    {
        $config     = (array) config('google-drive-backup', []);
        $enabled    = (bool) ($config['enabled'] ?? true);
        $policyName = (string) $this->option('policy');
        $type       = (string) $this->option('type');
        $noVerify   = (bool) $this->option('no-verify');
        $connection = $this->option('connection') ?: null;

        if (!$enabled && !$this->option('force')) {
            $this->warn('Google Drive backups are disabled. Use --force to override.');
            return Command::SUCCESS;
        }

        $tmpManager = new TemporaryFileManager();

        try {
            // ── Resolve policy ────────────────────────────────────────────────
            $policyMgr = new BackupPolicyManager($config);
            $policy    = $policyMgr->resolve($policyName);

            // If policy has a fixed type, use it (unless overridden on CLI)
            if ($policy->type() !== 'full' && $this->option('type') === 'full') {
                $type = $policy->type();
            }

            Event::dispatch(new BackupStarted($type, $policyName));
            $this->info("Starting [{$type}] backup using policy [{$policyName}]...");

            // ── Build filename ────────────────────────────────────────────────
            $filenameGen = new FilenameGenerator();
            $appName     = (string) ($config['application_name'] ?? config('app.name', 'laravel'));
            $env         = (string) ($config['environment']      ?? app()->environment());
            $format      = (string) ($config['filename']['format'] ?? '{app}-{env}-{type}-{date}-{time}-{uuid}.zip');
            $filename    = $filenameGen->generate($appName, $env, $type, $format);

            // ── Create the backup archive ─────────────────────────────────────
            $tmpPath = $tmpManager->create('gdrive-backup-', '.zip');
            $this->createBackupArchive($tmpPath, $type, $connection);

            $size     = (int) filesize($tmpPath);
            $checksum = (string) hash_file('sha256', $tmpPath);
            $now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            $metadata = new BackupMetadata(
                application: $appName,
                environment: $env,
                type: $type,
                createdAt: $now,
                size: $size,
                checksumAlgorithm: 'sha256',
                checksum: $checksum,
                policy: $policyName,
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

            // ── Upload to Google Drive ────────────────────────────────────────
            $googleConfig = (array) ($config['google'] ?? []);
            $client       = new GoogleDriveClient($googleConfig);
            $folderMgr    = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr      = new GoogleDriveFileManager($client);
            $storage      = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $this->info("Uploading [{$filename}] (" . $artifact->formattedSize() . ") to Google Drive...");
            $result = $storage->put($artifact);

            if (!$result->success) {
                $this->error('Upload failed: ' . $result->error);
                Event::dispatch(new BackupFailed($type, $policyName, new \RuntimeException($result->error ?? 'Unknown')));
                return Command::FAILURE;
            }

            $this->info("✓ Uploaded. File ID: [{$result->fileId}], Size: " . number_format($result->size) . ' bytes.');
            $artifact->storageLocation = $result->fileId;
            Event::dispatch(new BackupUploaded($artifact, $result));

            // ── Verify ────────────────────────────────────────────────────────
            if (!$noVerify && $policy->requiresVerification()) {
                $this->info('Verifying backup integrity...');
                $verifier     = new BackupVerificationService($fileMgr, $config);
                $verifyResult = $verifier->verify($artifact, $result->fileId);
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
            Event::dispatch(new BackupFailed($type ?? 'full', $policyName ?? 'default', $e));
            return Command::FAILURE;
        } finally {
            // Always delete temp files even on exception
            $tmpManager->cleanup();
        }
    }

    /**
     * Create the backup archive at $zipPath based on type.
     *
     * @throws \HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException
     */
    private function createBackupArchive(string $zipPath, string $type, ?string $connection): void
    {
        match ($type) {
            'database' => $this->createDatabaseBackup($zipPath, $connection),
            'files'    => $this->createFilesBackup($zipPath),
            'full'     => $this->createFullBackup($zipPath, $connection),
            default    => $this->createDatabaseBackup($zipPath, $connection),
        };
    }

    private function createDatabaseBackup(string $zipPath, ?string $connection): void
    {
        $this->line('  Dumping database...');
        $dumper = new DatabaseDumper();
        $dumper->dumpToZip($zipPath, $connection);
        $this->line('  ✓ Database dump complete.');
    }

    private function createFilesBackup(string $zipPath): void
    {
        $this->line('  Archiving application files...');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $basePath = base_path();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $excluded = ['vendor', 'node_modules', '.git', 'storage/logs', 'storage/framework/cache'];

        foreach ($iterator as $file) {
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());

            // Skip excluded directories
            foreach ($excluded as $ex) {
                if (str_starts_with($relativePath, $ex)) {
                    continue 2;
                }
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
        $this->line('  ✓ Files archive complete.');
    }

    private function createFullBackup(string $zipPath, ?string $connection): void
    {
        // Create separate archives then combine into one
        $tmpManager = new TemporaryFileManager();

        $dbZip    = $tmpManager->create('gdrive-db-',    '.zip');
        $filesZip = $tmpManager->create('gdrive-files-', '.zip');

        try {
            $this->createDatabaseBackup($dbZip, $connection);
            $this->createFilesBackup($filesZip);

            // Merge both into the final ZIP
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($dbZip,    'database.zip');
            $zip->addFile($filesZip, 'files.zip');
            $zip->close();
        } finally {
            $tmpManager->cleanup();
        }
    }
}
