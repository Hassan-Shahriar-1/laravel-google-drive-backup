<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Commands;

use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveClient;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFolderManager;
use Illuminate\Console\Command;
use Throwable;

class BackupTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:google-drive:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Google Drive OAuth credentials, API access, and target folder permissions.';

    public function handle(): int
    {
        $this->info('Testing Google Drive Backup Configuration & Connectivity...');
        $this->newLine();

        $config = (array) config('google-drive-backup.google', []);
        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;
        $refreshToken = $config['refresh_token'] ?? null;

        // 1. Validate credentials present
        $this->comment('1. Checking credentials configuration...');
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            $this->error('   ✗ Missing Google OAuth credentials.');
            $this->line('     Ensure GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET, and GOOGLE_DRIVE_REFRESH_TOKEN are set.');
            return Command::FAILURE;
        }
        $this->info('   ✓ OAuth credentials found in configuration.');

        // 2. Test Google Drive Client Authentication
        $this->comment('2. Connecting to Google Drive API...');
        $client = new GoogleDriveClient($config);
        $status = $client->testConnection();

        if (!$status['authenticated']) {
            $this->error('   ✗ Authentication failed: ' . ($status['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        $email = $status['email'] ?? 'Unknown user';
        $this->info("   ✓ Authenticated successfully as [{$email}].");

        if ($status['quota_usage'] !== null && $status['quota_limit'] !== null && $status['quota_limit'] > 0) {
            $usedMb = round($status['quota_usage'] / 1024 / 1024, 2);
            $limitMb = round($status['quota_limit'] / 1024 / 1024, 2);
            $this->line("     Storage Quota: {$usedMb} MB used of {$limitMb} MB.");
        }

        // 3. Test Folder Access
        $this->comment('3. Validating Google Drive backup folder...');
        $folderManager = new GoogleDriveFolderManager($client, $config);

        try {
            $folderId = $folderManager->resolveRootFolderId();
            $this->info("   ✓ Target backup folder resolved [ID: {$folderId}].");
        } catch (Throwable $e) {
            $this->error('   ✗ Folder resolution failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('All connectivity and permission checks passed successfully!');
        return Command::SUCCESS;
    }
}
