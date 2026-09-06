<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Services;

use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
use Throwable;

class TemporaryFileManager
{
    /** @var array<string> */
    private array $registered = [];

    /**
     * Create a new temporary file with an optional prefix and extension.
     * The file is automatically cleaned up when cleanup() or __destruct() is called.
     *
     * @throws GoogleDriveBackupException
     */
    public function create(string $prefix = 'gdrive-backup-', string $extension = '.zip'): string
    {
        $dir = sys_get_temp_dir();
        $path = tempnam($dir, $prefix);

        if ($path === false) {
            throw new GoogleDriveBackupException("Failed to create temporary file in [{$dir}].");
        }

        // Rename with proper extension if needed
        if ($extension !== '') {
            $newPath = $path . $extension;
            rename($path, $newPath);
            $path = $newPath;
        }

        $this->registered[] = $path;
        return $path;
    }

    /**
     * Delete a specific temporary file.
     */
    public function delete(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
        $this->registered = array_filter($this->registered, fn($p) => $p !== $path);
    }

    /**
     * Delete all registered temporary files.
     */
    public function cleanup(): void
    {
        foreach ($this->registered as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->registered = [];
    }

    /**
     * Run a callable with a temporary file that is always cleaned up afterward.
     *
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    public function withTempFile(callable $callback, string $prefix = 'gdrive-backup-', string $extension = '.zip'): mixed
    {
        $path = $this->create($prefix, $extension);
        try {
            return $callback($path);
        } finally {
            $this->delete($path);
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
