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
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
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
                            {--db                : Backup database only}
                            {--files             : Backup application files only}
                            {--full              : Backup both database and files}
                            {--path=*            : Specific directory or file path(s) to backup (relative to application root)}
                            {--connection=       : Database connection (sqlsrv, pgsql, mariadb, mysql, sqlite)}
                            {--subfolder=        : Specific subfolder to upload into (e.g. database, others, or nested a/b)}
                            {--by-type           : Store in subfolder named after backup type (e.g. database/)}
                            {--force             : Run even if backups are disabled}
                            {--no-verify         : Skip post-upload verification}';

    protected $description = 'Create a backup and upload it to Google Drive.';

    public function handle(): int
    {
        $config     = (array) config('google-drive-backup', []);
        $enabled    = (bool) ($config['enabled'] ?? true);
        $policyName = (string) $this->option('policy');
        $noVerify   = (bool) $this->option('no-verify');

        // Resolve connection (CLI option -> config -> DB_CONNECTION -> default)
        $connection = $this->option('connection')
            ?: config('google-drive-backup.database_connection')
            ?: env('DB_CONNECTION')
            ?: config('database.default');

        if (!$enabled && !$this->option('force')) {
            $this->warn('Google Drive backups are disabled. Use --force to override.');
            return Command::SUCCESS;
        }

        $tmpManager = new TemporaryFileManager();

        try {
            // ── Resolve policy ────────────────────────────────────────────────
            $policyMgr = new BackupPolicyManager($config);
            $policy    = $policyMgr->resolve($policyName);

            // ── Resolve custom path(s) ───────────────────────────────────────
            $rawPaths = (array) $this->option('path');
            $customPaths = [];
            foreach ($rawPaths as $rp) {
                if (is_string($rp)) {
                    foreach (explode(',', $rp) as $p) {
                        $p = trim($p);
                        if ($p !== '') {
                            $customPaths[] = $p;
                        }
                    }
                }
            }

            // ── Resolve backup type ───────────────────────────────────────────
            if ($this->option('full') || ($this->option('db') && $this->option('files'))) {
                $type = 'full';
            } elseif ($this->option('files')) {
                $type = 'files';
            } elseif ($this->option('db')) {
                $type = 'database';
            } elseif (!empty($customPaths)) {
                $type = 'files';
            } elseif ($policy->type() !== 'full') {
                $type = $policy->type();
            } else {
                $type = (string) (config('google-drive-backup.default_type', 'database'));
            }

            Event::dispatch(new BackupStarted($type, $policyName));
            $this->info("Starting [{$type}] backup using policy [{$policyName}] (connection: [{$connection}])...");

            // ── Build filename ────────────────────────────────────────────────
            $filenameGen = new FilenameGenerator();
            $appName     = (string) ($config['application_name'] ?? config('app.name', 'laravel'));
            $env         = (string) ($config['environment']      ?? app()->environment());
            $format      = (string) ($config['filename']['format'] ?? '{app}-{env}-{type}-{date}-{time}-{uuid}.zip');
            $filename    = $filenameGen->generate($appName, $env, $type, $format);

            // ── Create the backup archive ─────────────────────────────────────
            $tmpPath = $tmpManager->create('gdrive-backup-', '.zip');
            $this->createBackupArchive($tmpPath, $type, $connection, $customPaths);

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

            // ── Resolve destination subfolder ────────────────────────────────
            $subfolderOption = $this->option('subfolder');
            $subfolder = !empty($subfolderOption) ? (string) $subfolderOption : null;

            if ($subfolder === null && $this->option('by-type')) {
                $subfolder = $type; // e.g. 'database', 'files', 'full'
            }

            if ($subfolder !== null) {
                $subfolder = str_replace(
                    ['{year}', '{month}', '{day}', '{date}', '{type}', '{env}', '{app}'],
                    [
                        $now->format('Y'),
                        $now->format('m'),
                        $now->format('d'),
                        $now->format('Y-m-d'),
                        $type,
                        $env,
                        $appName,
                    ],
                    $subfolder
                );
            }

            $destText = $subfolder !== null ? "[root] / {$subfolder}" : "[root] (folder from .env)";
            $this->info("Uploading [{$filename}] (" . $artifact->formattedSize() . ") to Google Drive ({$destText})...");
            $result = $storage->put($artifact, $subfolder);

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
     * @param array<string> $paths
     * @throws \HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException
     */
    private function createBackupArchive(string $zipPath, string $type, ?string $connection, array $paths = []): void
    {
        match ($type) {
            'database' => $this->createDatabaseBackup($zipPath, $connection),
            'files'    => $this->createFilesBackup($zipPath, $paths),
            'full'     => $this->createFullBackup($zipPath, $connection, $paths),
            default    => $this->createDatabaseBackup($zipPath, $connection),
        };
    }

    private function createDatabaseBackup(string $zipPath, ?string $connection): void
    {
        $conn   = $connection ?? config('database.default');
        $driver = (string) config("database.connections.{$conn}.driver", 'database');

        $this->line("  Dumping database connection [{$conn}] (driver: {$driver})...");
        $dumper = new DatabaseDumper();
        $dumper->dumpToZip($zipPath, $conn);
        $this->line("  ✓ Database dump complete [{$conn} / {$driver}].");
    }

    /**
     * @param array<string> $paths
     * @throws \HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException
     */
    private function createFilesBackup(string $zipPath, array $paths = []): void
    {
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $basePath = base_path();
        $excluded = ['vendor', 'node_modules', '.git', 'storage/logs', 'storage/framework/cache'];

        if (!empty($paths)) {
            $this->line('  Archiving specified path(s)...');
            $filesAdded = 0;

            foreach ($paths as $target) {
                $target = trim($target);
                $fullPath = str_starts_with($target, DIRECTORY_SEPARATOR)
                    ? $target
                    : $basePath . DIRECTORY_SEPARATOR . ltrim($target, '/\\');

                if (!file_exists($fullPath)) {
                    $this->warn("  Path [{$target}] does not exist, skipping.");
                    continue;
                }

                if (is_file($fullPath)) {
                    $relativeInZip = str_starts_with($fullPath, $basePath)
                        ? ltrim(substr($fullPath, strlen($basePath)), '/\\')
                        : basename($fullPath);
                    $zip->addFile($fullPath, $relativeInZip);
                    $filesAdded++;
                } elseif (is_dir($fullPath)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST
                    );

                    foreach ($iterator as $file) {
                        $pathname = $file->getPathname();
                        $relativeInZip = str_starts_with($pathname, $basePath)
                            ? ltrim(substr($pathname, strlen($basePath)), '/\\')
                            : ltrim(substr($pathname, strlen(dirname($fullPath))), '/\\');

                        // Skip excluded directories
                        foreach ($excluded as $ex) {
                            if (str_starts_with($relativeInZip, $ex)) {
                                continue 2;
                            }
                        }

                        if ($file->isDir()) {
                            $zip->addEmptyDir($relativeInZip);
                        } elseif ($file->isFile()) {
                            $zip->addFile($pathname, $relativeInZip);
                            $filesAdded++;
                        }
                    }
                }
            }

            $zip->close();

            if ($filesAdded === 0) {
                throw new GoogleDriveBackupException('No files found to archive in specified path(s): ' . implode(', ', $paths));
            }

            $this->line("  ✓ Files archive complete ({$filesAdded} file(s) archived).");
            return;
        }

        $this->line('  Archiving application files...');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

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

    /**
     * @param array<string> $paths
     */
    private function createFullBackup(string $zipPath, ?string $connection, array $paths = []): void
    {
        // Create separate archives then combine into one
        $tmpManager = new TemporaryFileManager();

        $dbZip    = $tmpManager->create('gdrive-db-',    '.zip');
        $filesZip = $tmpManager->create('gdrive-files-', '.zip');

        try {
            $this->createDatabaseBackup($dbZip, $connection);
            $this->createFilesBackup($filesZip, $paths);

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
