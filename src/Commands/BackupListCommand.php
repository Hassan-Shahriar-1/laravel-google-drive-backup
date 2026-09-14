<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use Illuminate\Console\Command;

class BackupListCommand extends Command
{
    protected $signature = 'backup:google-drive:list
                            {--folder=    : Override the target Google Drive folder ID}
                            {--subfolder= : List backups from a specific subfolder (e.g. database, others)}';

    protected $description = 'List all backups stored on Google Drive.';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $folderId     = $this->option('folder');
        $subfolder    = $this->option('subfolder') ?: null;

        try {
            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $targetMsg = $subfolder !== null ? "subfolder [{$subfolder}]" : "root folder";
            $this->info("Fetching backups from Google Drive ({$targetMsg})...");

            $rows = [];
            foreach ($storage->list($folderId ?: null, $subfolder) as $artifact) {
                $rows[] = [
                    substr($artifact->id ?? '-', 0, 20),
                    $artifact->filename,
                    $artifact->type,
                    $artifact->createdAt?->format('Y-m-d H:i') ?? '-',
                    $artifact->formattedSize(),
                    $artifact->metadata?->policy ?? '-',
                ];
            }

            if (empty($rows)) {
                $this->warn('No backups found on Google Drive.');
                return Command::SUCCESS;
            }

            $this->table(
                ['ID', 'Filename', 'Type', 'Created (UTC)', 'Size', 'Policy'],
                $rows
            );

            $this->info(count($rows) . ' backup(s) found.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to list backups: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
