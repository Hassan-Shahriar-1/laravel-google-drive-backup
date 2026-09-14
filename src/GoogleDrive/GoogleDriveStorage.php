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

    public function put(BackupArtifact $backup, ?string $subfolder = null): StorageResult
    {
        try {
            $rootFolderId = $this->folderManager->resolveRootFolderId();

            if (!empty($subfolder)) {
                $segments = array_filter(explode('/', str_replace('\\', '/', $subfolder)));
                $targetFolderId = $this->folderManager->resolveSubfolderPath($rootFolderId, $segments);
            } elseif ((bool) ($this->config['subfolders'] ?? false)) {
                $env = sanitize_string_for_path($this->config['environment'] ?? 'production');
                $targetFolderId = $this->folderManager->resolveSubfolderPath($rootFolderId, [$env]);
            } else {
                $targetFolderId = $rootFolderId;
            }

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

    public function list(?string $folderId = null, ?string $subfolder = null): iterable
    {
        $rootFolderId = $folderId ?? $this->folderManager->resolveRootFolderId();

        if (!empty($subfolder)) {
            $segments = array_filter(explode('/', str_replace('\\', '/', $subfolder)));
            $targetFolderId = $this->folderManager->resolveSubfolderPath($rootFolderId, $segments);

            $files = $this->fileManager->listFiles($targetFolderId);
            foreach ($files as $file) {
                $artifact = $this->fileManager->driveFileToArtifact($file);
                $artifact->subfolder = $subfolder;
                yield $artifact;
            }
            return;
        }

        // 1. Files in root folder
        $files = $this->fileManager->listFiles($rootFolderId);
        foreach ($files as $file) {
            $artifact = $this->fileManager->driveFileToArtifact($file);
            $artifact->subfolder = null;
            yield $artifact;
        }

        // 2. Discover files in immediate subfolders under root
        $subfolders = $this->folderManager->listSubfolders($rootFolderId);
        foreach ($subfolders as $subName => $subId) {
            $subFiles = $this->fileManager->listFiles($subId);
            foreach ($subFiles as $file) {
                $artifact = $this->fileManager->driveFileToArtifact($file);
                $artifact->subfolder = $subName;
                yield $artifact;
            }
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
