<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use HassanShahriar\GoogleDriveBackup\Services\TemporaryFileManager;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class TemporaryFileManagerTest extends TestCase
{
    public function test_creates_temporary_file(): void
    {
        $manager = new TemporaryFileManager();
        $path = $manager->create('test-', '.zip');

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.zip', $path);

        $manager->cleanup();
        $this->assertFileDoesNotExist($path);
    }

    public function test_with_temp_file_cleans_up_after_callback(): void
    {
        $manager = new TemporaryFileManager();
        $capturedPath = null;

        $manager->withTempFile(function (string $path) use (&$capturedPath) {
            $capturedPath = $path;
            $this->assertFileExists($path);
            file_put_contents($path, 'test data');
        });

        $this->assertNotNull($capturedPath);
        $this->assertFileDoesNotExist($capturedPath);
    }

    public function test_with_temp_file_cleans_up_even_on_exception(): void
    {
        $manager = new TemporaryFileManager();
        $capturedPath = null;

        try {
            $manager->withTempFile(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException) {
        }

        $this->assertNotNull($capturedPath);
        $this->assertFileDoesNotExist($capturedPath);
    }

    public function test_delete_specific_file(): void
    {
        $manager = new TemporaryFileManager();
        $path = $manager->create();

        $this->assertFileExists($path);
        $manager->delete($path);
        $this->assertFileDoesNotExist($path);
    }
}
