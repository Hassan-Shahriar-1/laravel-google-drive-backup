<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use HassanShahriar\GoogleDriveBackup\Services\DatabaseRestorer;
use Illuminate\Console\Command;
use ZipArchive;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:google-drive:restore-db
                            {id            : Google Drive file ID of the backup to restore}
                            {--connection= : Database connection to restore into (defaults to DB_CONNECTION)}
                            {--force       : Skip confirmation prompts}';

    protected $aliases = [
        'backup:google-drive:restore',
    ];

    protected $description = 'Download a backup from Google Drive and restore the database.';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $fileId       = (string) $this->argument('id');
        $connection   = $this->option('connection')
            ?: config('google-drive-backup.database_connection')
            ?: env('DB_CONNECTION')
            ?: config('database.default');
        $force        = (bool) $this->option('force');

        $this->newLine();
        $this->warn('⚠  WARNING: Database restore is a destructive operation.');
        $this->warn("   This will OVERWRITE your current [{$connection}] database.");
        $this->newLine();

        if (!$force && !$this->confirm("Are you sure you want to restore the database from backup [{$fileId}]?")) {
            $this->info('Database restore cancelled.');
            return Command::SUCCESS;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'gdrive-restore-') . '.zip';

        try {
            // ── Step 1: Download ──────────────────────────────────────────────
            $this->info('[1/3] Downloading backup from Google Drive...');
            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $resolvedFileId = $storage->resolveFileId($fileId);

            $storage->download($resolvedFileId, $tmpZip);
            $sizeMb = round(filesize($tmpZip) / 1024 / 1024, 2);
            $this->info("     ✓ Downloaded ({$sizeMb} MB).");

            // ── Step 2: Validate Archive ──────────────────────────────────────
            $this->info('[2/3] Validating backup archive...');
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                $this->error('Downloaded file is not a valid ZIP archive.');
                return Command::FAILURE;
            }

            $isFull   = $this->zipContainsFile($zip, 'database.zip');
            $isDbOnly = $this->zipContainsSql($zip);
            $zip->close();

            if (!$isFull && !$isDbOnly) {
                $this->error('The selected backup archive does not contain a database dump.');
                $this->line('To download application files, use: php artisan backup:google-drive:download');
                return Command::FAILURE;
            }

            // ── Step 3: Restore Database ──────────────────────────────────────
            $this->info("[3/3] Restoring database into [{$connection}]...");

            $dbZipToRestore = $tmpZip;

            // Full backup: extract inner database.zip first
            if ($isFull) {
                $innerDbZip = tempnam(sys_get_temp_dir(), 'gdrive-db-inner-') . '.zip';
                $zip = new ZipArchive();
                $zip->open($tmpZip);
                $zip->extractTo(sys_get_temp_dir(), 'database.zip');
                $zip->close();
                rename(sys_get_temp_dir() . '/database.zip', $innerDbZip);
                $dbZipToRestore = $innerDbZip;
            }

            try {
                $restorer = new DatabaseRestorer();
                $restorer->restoreFromZip($dbZipToRestore, $connection);
                $this->newLine();
                $this->info("✓ Database [{$connection}] restored successfully!");
            } finally {
                if (isset($innerDbZip) && file_exists($innerDbZip)) {
                    @unlink($innerDbZip);
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Restore failed: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            if (file_exists($tmpZip)) {
                @unlink($tmpZip);
            }
        }
    }

    private function zipContainsFile(ZipArchive $zip, string $name): bool
    {
        return $zip->locateName($name) !== false;
    }

    private function zipContainsSql(ZipArchive $zip): bool
    {
        for ($i = 0; $i < $zip->count(); $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && (str_ends_with($name, '.sql') || str_ends_with($name, '.sqlite') || str_ends_with($name, '.bak'))) {
                return true;
            }
        }
        return false;
    }
}
