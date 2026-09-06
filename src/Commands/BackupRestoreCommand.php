<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use Illuminate\Console\Command;
use ZipArchive;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:google-drive:restore
                            {id : The Google Drive file ID of the backup to restore}
                            {--destination= : Directory to extract the backup into}';

    protected $description = 'Download and extract a Google Drive backup for restoration.';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $fileId       = (string) $this->argument('id');
        $destination  = (string) ($this->option('destination') ?? storage_path('app/restore'));

        $this->warn('WARNING: Restore is a destructive operation. Ensure you have a working backup before proceeding.');

        if (!$this->confirm("Restore backup [{$fileId}] to [{$destination}]?")) {
            $this->info('Restore cancelled.');
            return Command::SUCCESS;
        }

        // Create destination dir
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'gdrive-restore-') . '.zip';

        try {
            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $this->info("Step 1/3: Downloading backup [{$fileId}]...");
            $storage->download($fileId, $tmpZip);
            $this->info('  ✓ Downloaded.');

            $this->info("Step 2/3: Validating backup archive...");
            $zip = new ZipArchive();
            if ($zip->open($tmpZip) !== true) {
                $this->error('Downloaded file is not a valid ZIP archive.');
                return Command::FAILURE;
            }
            $entries = $zip->count();
            $zip->close();
            $this->info("  ✓ Valid archive with {$entries} entries.");

            $this->info("Step 3/3: Extracting to [{$destination}]...");
            $zip = new ZipArchive();
            $zip->open($tmpZip);
            $zip->extractTo($destination);
            $zip->close();
            $this->info('  ✓ Extracted successfully.');

            $this->newLine();
            $this->info("Restore complete. Files are in [{$destination}].");
            $this->warn('Review the extracted files and restore your database/files manually.');
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
}
