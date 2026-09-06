<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use Illuminate\Console\Command;

class BackupDownloadCommand extends Command
{
    protected $signature = 'backup:google-drive:download
                            {id : The Google Drive file ID of the backup to download}
                            {destination : Local path to save the downloaded backup}';

    protected $description = 'Download a backup from Google Drive to a local path.';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $fileId       = (string) $this->argument('id');
        $destination  = (string) $this->argument('destination');

        if (file_exists($destination)) {
            if (!$this->confirm("File [{$destination}] already exists. Overwrite?")) {
                $this->info('Download cancelled.');
                return Command::SUCCESS;
            }
        }

        $dir = dirname($destination);
        if (!is_dir($dir)) {
            $this->error("Destination directory [{$dir}] does not exist.");
            return Command::FAILURE;
        }

        try {
            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $this->info("Downloading backup [{$fileId}]...");
            $storage->download($fileId, $destination);

            $size = filesize($destination);
            $this->info("✓ Downloaded to [{$destination}] (" . number_format((int) $size) . ' bytes).');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Download failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
