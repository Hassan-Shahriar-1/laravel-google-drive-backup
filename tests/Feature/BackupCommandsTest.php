<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Feature;

use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    public function test_backup_run_command_fails_gracefully_without_credentials(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive')
            ->assertFailed();
    }

    public function test_backup_disabled_skips_without_force(): void
    {
        config()->set('google-drive-backup.enabled', false);

        $this->artisan('backup:google-drive')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_backup_run_with_invalid_policy_fails(): void
    {
        config()->set('google-drive-backup.google.client_id', 'id');
        config()->set('google-drive-backup.google.client_secret', 'secret');
        config()->set('google-drive-backup.google.refresh_token', 'token');

        $this->artisan('backup:google-drive', ['--policy' => 'nonexistent'])
            ->assertFailed();
    }

    public function test_backup_list_command_fails_gracefully_without_credentials(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive:list')
            ->assertFailed();
    }

    public function test_backup_clean_dry_run_requires_no_confirmation(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        // Will fail at connection stage, not at confirmation stage
        $this->artisan('backup:google-drive:clean', ['--dry-run' => true])
            ->assertFailed();
    }

    public function test_backup_verify_command_requires_id_or_all_option(): void
    {
        $this->artisan('backup:google-drive:verify')
            ->expectsOutputToContain('--id')
            ->assertFailed();
    }

    public function test_backup_test_command_fails_without_credentials(): void
    {
        config()->set('google-drive-backup.google.client_id', null);
        config()->set('google-drive-backup.google.client_secret', null);
        config()->set('google-drive-backup.google.refresh_token', null);

        $this->artisan('backup:google-drive:test')
            ->expectsOutputToContain('Missing Google OAuth credentials')
            ->assertFailed();
    }

    public function test_backup_run_command_accepts_db_files_full_flags(): void
    {
        config()->set('google-drive-backup.enabled', false);

        $this->artisan('backup:google-drive', ['--db' => true])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->artisan('backup:google-drive', ['--files' => true])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->artisan('backup:google-drive', ['--full' => true])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->artisan('backup:google-drive', ['--path' => ['storage/app/public/images']])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_create_files_backup_archives_only_specified_paths(): void
    {
        $command = new \HassanShahriar\GoogleDriveBackup\Commands\BackupRunCommand();
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        ));
        $refMethod = new \ReflectionMethod($command, 'createFilesBackup');
        $refMethod->setAccessible(true);

        $tmpDir = sys_get_temp_dir() . '/test_backup_paths_' . uniqid();
        mkdir($tmpDir . '/images', 0777, true);
        file_put_contents($tmpDir . '/images/photo.jpg', 'fake-image-content');
        file_put_contents($tmpDir . '/ignored.txt', 'ignored');

        $zipPath = sys_get_temp_dir() . '/test_out_' . uniqid() . '.zip';

        // Call with custom path
        $refMethod->invoke($command, $zipPath, [$tmpDir . '/images']);

        $this->assertFileExists($zipPath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame('fake-image-content', $zip->getFromName('images/photo.jpg'));
        $this->assertFalse($zip->locateName('ignored.txt'));
        $zip->close();

        @unlink($zipPath);
        @unlink($tmpDir . '/images/photo.jpg');
        @rmdir($tmpDir . '/images');
        @unlink($tmpDir . '/ignored.txt');
        @rmdir($tmpDir);
    }

    public function test_create_files_backup_throws_when_paths_not_found(): void
    {
        $command = new \HassanShahriar\GoogleDriveBackup\Commands\BackupRunCommand();
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput()
        ));
        $refMethod = new \ReflectionMethod($command, 'createFilesBackup');
        $refMethod->setAccessible(true);

        $zipPath = sys_get_temp_dir() . '/test_empty_' . uniqid() . '.zip';

        $this->expectException(\HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException::class);
        $this->expectExceptionMessage('No files found to archive in specified path(s)');

        try {
            $refMethod->invoke($command, $zipPath, ['non_existent_folder_path_xyz_123']);
        } finally {
            @unlink($zipPath);
        }
    }
}
