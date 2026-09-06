<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\Events\BackupVerified;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use HassanShahriar\GoogleDriveBackup\Verification\BackupVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:google-drive:verify
                            {--id= : Specific backup file ID to verify}
                            {--all : Verify all backups}';

    protected $description = 'Verify the integrity of one or all Google Drive backups.';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $id           = $this->option('id');
        $all          = $this->option('all');

        if (!$id && !$all) {
            $this->error('Please specify --id=<file-id> or --all.');
            return Command::FAILURE;
        }

        try {
            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);
            $verifier  = new BackupVerificationService($fileMgr, $config);

            $artifacts = $all
                ? iterator_to_array($storage->list())
                : array_filter([$storage->get((string) $id)]);

            if (empty($artifacts)) {
                $this->warn('No backups found to verify.');
                return Command::SUCCESS;
            }

            $passed = 0;
            $failed = 0;
            foreach ($artifacts as $artifact) {
                if ($artifact === null) continue;
                $this->line("Verifying: {$artifact->filename}...");
                $result = $verifier->verify($artifact, $artifact->storageLocation);
                Event::dispatch(new BackupVerified($artifact, $result));

                if ($result->passed) {
                    $this->info("  ✓ PASSED (mode: {$result->mode})");
                    $passed++;
                } else {
                    $this->error("  ✗ FAILED: {$result->error}");
                    $failed++;
                }
            }

            $this->newLine();
            $this->info("Verification complete. Passed: {$passed} | Failed: {$failed}");
            return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Verification error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
