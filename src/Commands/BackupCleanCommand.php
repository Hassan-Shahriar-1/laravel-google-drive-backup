<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\Events\BackupDeleted;
use HassanShahriar\GoogleDriveBackup\Events\CleanupCompleted;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveStorage;
use HassanShahriar\GoogleDriveBackup\Policy\BackupPolicyManager;
use HassanShahriar\GoogleDriveBackup\Policy\RetentionCalculator;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class BackupCleanCommand extends Command
{
    protected $signature = 'backup:google-drive:clean
                            {--dry-run : Show what would be deleted without deleting}
                            {--policy=default : Policy name to use for retention rules}';

    protected $description = 'Remove expired backups from Google Drive according to retention policy.';

    public function handle(): int
    {
        $config       = (array) config('google-drive-backup', []);
        $googleConfig = (array) ($config['google'] ?? []);
        $dryRun       = (bool) $this->option('dry-run');
        $policyName   = (string) $this->option('policy');

        try {
            $policyMgr = new BackupPolicyManager($config);
            $policy    = $policyMgr->resolve($policyName);
            $rules     = $policy->retentionRules();

            $client    = new GoogleDriveClient($googleConfig);
            $folderMgr = new GoogleDriveFolderManager($client, array_merge($googleConfig, $config));
            $fileMgr   = new GoogleDriveFileManager($client);
            $storage   = new GoogleDriveStorage($client, $folderMgr, $fileMgr, $config);

            $this->info('Fetching backup list from Google Drive...');
            $backups = iterator_to_array($storage->list());

            if (empty($backups)) {
                $this->info('No backups found. Nothing to clean.');
                return Command::SUCCESS;
            }

            $this->info(count($backups) . ' backup(s) found. Calculating retention...');

            $calculator = new RetentionCalculator();
            $result     = $calculator->determineBackupsToDelete($backups, $rules);

            $this->info("Retention rules: daily={$rules['daily']}, weekly={$rules['weekly']}, monthly={$rules['monthly']}");
            $this->info("Keeping: {$result->countKept()} | To delete: {$result->countToDelete()}");

            if ($result->countToDelete() === 0) {
                $this->info('No backups to delete.');
                Event::dispatch(new CleanupCompleted($result, $dryRun));
                return Command::SUCCESS;
            }

            // Show what will be deleted
            $rows = array_map(fn(BackupArtifact $b) => [
                substr($b->id, 0, 20),
                $b->filename,
                $b->createdAt?->format('Y-m-d H:i') ?? '-',
                $b->formattedSize(),
            ], $result->toDelete);

            $this->table(['ID', 'Filename', 'Created (UTC)', 'Size'], $rows);

            if ($dryRun) {
                $this->warn('[DRY-RUN] No files were deleted. Remove --dry-run to execute.');
                Event::dispatch(new CleanupCompleted($result, true));
                return Command::SUCCESS;
            }

            if (!$this->confirm("Delete {$result->countToDelete()} backup(s) from Google Drive?")) {
                $this->info('Cancelled.');
                return Command::SUCCESS;
            }

            $deleted = 0;
            foreach ($result->toDelete as $artifact) {
                $this->line("  Deleting: {$artifact->filename}...");
                $storage->delete($artifact->id);
                Event::dispatch(new BackupDeleted($artifact->id, $artifact->filename));
                $deleted++;
            }

            $this->info("✓ Deleted {$deleted} backup(s) successfully.");
            Event::dispatch(new CleanupCompleted($result, false));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
