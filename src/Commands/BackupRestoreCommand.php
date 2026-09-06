<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use HassanShahriar\GoogleDriveBackup\Services\DatabaseRestorer;
use Illuminate\Console\Command;
use ZipArchive;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:google-drive:restore
                            {id                  : Google Drive file ID of the backup to restore}
                            {--destination=      : Directory to extract files into (for file/full backups)}
                            {--db-restore        : Automatically restore the database from the backup}
                            {--connection=       : Database connection to restore into (default: DB_CONNECTION)}
                            {--force             : Skip confirmation prompts}';

    protected $description = 'Download and restore a Google Drive backup (files and/or database).';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $fileId       = (string) $this->argument('id');
        $destination  = (string) ($this->option('destination') ?? storage_path('app/restore'));
        $dbRestore    = (bool) $this->option('db-restore');
        $connection   = $this->option('connection') ?: null;
        $force        = (bool) $this->option('force');

        $this->newLine();
        $this->warn('⚠  WARNING: Restore is a destructive operation.');
        if ($dbRestore) {
            $this->warn('⚠  --db-restore will OVERWRITE your current database.');
        }
        $this->newLine();

        if (!$force && !$this->confirm("Restore backup [{$fileId}]?")) {
            $this->info('Restore cancelled.');
            return Command::SUCCESS;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'gdrive-restore-') . '.zip';

        try {
            // ── Step 1: Download ──────────────────────────────────────────────
            $this->info('[1/4] Downloading backup from Google Drive...');
            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $storage->download($fileId, $tmpZip);
            $sizeMb = round(filesize($tmpZip) / 1024 / 1024, 2);
            $this->info("     ✓ Downloaded ({$sizeMb} MB).");

            // ── Step 2: Validate ──────────────────────────────────────────────
            $this->info('[2/4] Validating archive...');
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                $this->error('Downloaded file is not a valid ZIP archive.');
                return Command::FAILURE;
            }
            $entries = $zip->count();

            // Detect backup type from contents
            $isFull     = $this->zipContainsFile($zip, 'database.zip') && $this->zipContainsFile($zip, 'files.zip');
            $isDbOnly   = !$isFull && $this->zipContainsSql($zip);
            $isFilesOnly = !$isFull && !$isDbOnly;

            $zip->close();
            $this->info("     ✓ Valid archive ({$entries} entries). Type: " . ($isFull ? 'full' : ($isDbOnly ? 'database' : 'files')) . '.');

            // ── Step 3: Database Restore ──────────────────────────────────────
            if ($dbRestore && ($isFull || $isDbOnly)) {
                $this->info('[3/4] Restoring database...');

                $dbZipToRestore = $tmpZip;

                // Full backup: extract the inner database.zip first
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
                    $dbConf = (string) ($connection ?? config('database.default'));
                    if (!$force && !$this->confirm("    This will OVERWRITE the [{$dbConf}] database. Continue?")) {
                        $this->warn('    Database restore skipped.');
                    } else {
                        $restorer = new DatabaseRestorer();
                        $restorer->restoreFromZip($dbZipToRestore, $connection);
                        $this->info('     ✓ Database restored successfully.');
                    }
                } finally {
                    if (isset($innerDbZip) && file_exists($innerDbZip)) {
                        @unlink($innerDbZip);
                    }
                }
            } elseif ($dbRestore) {
                $this->warn('[3/4] No database dump found in archive. Skipping database restore.');
            } else {
                $this->info('[3/4] Skipping database restore (use --db-restore to enable).');
            }

            // ── Step 4: Files Extraction ──────────────────────────────────────
            if ($isFull || $isFilesOnly) {
                $this->info("[4/4] Extracting files to [{$destination}]...");

                if (!is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }

                $zip = new ZipArchive();
                $zip->open($tmpZip);

                if ($isFull) {
                    // Extract inner files.zip then expand it
                    $innerFilesZip = tempnam(sys_get_temp_dir(), 'gdrive-files-inner-') . '.zip';
                    $zip->extractTo(sys_get_temp_dir(), 'files.zip');
                    $zip->close();
                    rename(sys_get_temp_dir() . '/files.zip', $innerFilesZip);

                    $zip2 = new ZipArchive();
                    $zip2->open($innerFilesZip);
                    $zip2->extractTo($destination);
                    $zip2->close();
                    @unlink($innerFilesZip);
                } else {
                    $zip->extractTo($destination);
                    $zip->close();
                }

                $this->info('     ✓ Files extracted.');
            } elseif ($isDbOnly) {
                $this->info('[4/4] Database-only backup — no files to extract.');
            }

            $this->newLine();
            $this->info('Restore complete!');

            if ($isFull || $isFilesOnly) {
                $this->line("  Files extracted to: {$destination}");
            }
            if ($dbRestore) {
                $this->line('  Database has been restored.');
            } elseif ($isDbOnly) {
                $this->line('  Tip: Run again with --db-restore to restore the database automatically.');
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
            if ($name !== false && str_ends_with($name, '.sql')) {
                return true;
            }
        }
        return false;
    }
}
