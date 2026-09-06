<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\GoogleDrive;

use HassanShahriar\GoogleDriveBackup\Contracts\BackupStorage;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\StorageResult;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveUploadException;
use Throwable;

class GoogleDriveStorage implements BackupStorage
{
    public function __construct(
        protected GoogleDriveClient $client,
        protected GoogleDriveFolderManager $folderManager,
        protected GoogleDriveFileManager $fileManager,
        protected array $config = []
    ) {}

    public function put(BackupArtifact $backup): StorageResult
    {
        try {
            $rootFolderId = $this->folderManager->resolveRootFolderId();

            // Build subfolder path: app-name / environment
            $appName = sanitize_string_for_path($this->config['application_name'] ?? 'laravel');
            $env = sanitize_string_for_path($this->config['environment'] ?? 'production');

            $targetFolderId = $this->folderManager->resolveSubfolderPath($rootFolderId, [$appName, $env]);

            if ($backup->path === null || !file_exists($backup->path)) {
                return StorageResult::failure("Local backup file not found: {$backup->path}", $backup->filename);
            }

            $metadata = $backup->metadata;
            $driveFile = $this->fileManager->upload(
                $backup->path,
                $backup->filename,
                $targetFolderId,
                $metadata
            );

            return StorageResult::success(
                fileId: (string) $driveFile->getId(),
                filename: $backup->filename,
                size: (int) $driveFile->getSize(),
                checksum: $driveFile->getMd5Checksum() ?: $backup->checksum,
                raw: ['google_file' => $driveFile->toSimpleObject()]
            );
        } catch (Throwable $e) {
            return StorageResult::failure($e->getMessage(), $backup->filename);
        }
    }

    public function list(?string $folderId = null): iterable
    {
        $targetFolderId = $folderId ?? $this->folderManager->resolveRootFolderId();
        $files = $this->fileManager->listFiles($targetFolderId);

        foreach ($files as $file) {
            yield $this->fileManager->driveFileToArtifact($file);
        }
    }

    public function get(string $id): ?BackupArtifact
    {
        $file = $this->fileManager->getFile($id);
        if ($file === null) {
            return null;
        }
        return $this->fileManager->driveFileToArtifact($file);
    }

    public function download(string $id, string $destination): void
    {
        $this->fileManager->download($id, $destination);
    }

    public function delete(string $id): bool
    {
        return $this->fileManager->deleteFile($id);
    }

    public function exists(string $id): bool
    {
        $file = $this->fileManager->getFile($id);
        return $file !== null && !$file->getTrashed();
    }
}

/**
 * Sanitize a string for use as a Drive folder path segment.
 */
function sanitize_string_for_path(string $value): string
{
    $value = preg_replace('/[^\w\s\-]/', '', $value) ?? $value;
    return strtolower(trim(preg_replace('/\s+/', '-', $value) ?? $value));
}
