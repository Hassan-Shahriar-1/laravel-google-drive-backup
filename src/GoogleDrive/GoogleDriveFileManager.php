<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\GoogleDrive;

use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile;
use Google\Http\MediaFileUpload;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\BackupMetadata;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveUploadException;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDrivePermissionException;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class GoogleDriveFileManager
{
    /** Chunk size for resumable uploads: 5 MB */
    private const CHUNK_SIZE_BYTES = 5 * 1024 * 1024;

    /** Max page size for listing */
    private const PAGE_SIZE = 100;

    public function __construct(
        protected GoogleDriveClient $client
    ) {}

    protected function getService(): GoogleDriveService
    {
        return $this->client->getDriveService();
    }

    /**
     * Upload a local file to a Drive folder. Uses resumable chunked upload
     * to avoid loading the entire file into memory.
     *
     * @throws GoogleDriveUploadException
     */
    public function upload(string $localPath, string $filename, string $folderId, BackupMetadata $metadata): DriveFile
    {
        if (!file_exists($localPath)) {
            throw new GoogleDriveUploadException("Local backup file not found: {$localPath}");
        }

        try {
            $fileSize = filesize($localPath);
            $service = $this->getService();

            $fileMetadata = new DriveFile([
                'name' => $filename,
                'parents' => [$folderId],
                'description' => $metadata->toJson(),
                'appProperties' => [
                    'backup_type' => $metadata->type,
                    'backup_env' => $metadata->environment,
                    'backup_app' => $metadata->application,
                    'backup_policy' => $metadata->policy,
                    'created_at' => $metadata->createdAt->format(\DateTimeInterface::ATOM),
                ],
            ]);

            // Use resumable chunked upload for large files
            $service->getClient()->setDefer(true);

            $request = $service->files->create($fileMetadata, [
                'fields' => 'id, name, size, md5Checksum, createdTime',
                'supportsAllDrives' => true,
            ]);

            $media = new MediaFileUpload(
                $service->getClient(),
                $request,
                'application/zip',
                null,
                true, // resumable
                self::CHUNK_SIZE_BYTES
            );
            $media->setFileSize((int) $fileSize);

            $result = false;
            $handle = fopen($localPath, 'rb');
            if ($handle === false) {
                throw new GoogleDriveUploadException("Cannot open local backup file: {$localPath}");
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_SIZE_BYTES);
                    if ($chunk === false) {
                        break;
                    }
                    $result = $media->nextChunk($chunk);
                }
            } finally {
                fclose($handle);
            }

            $service->getClient()->setDefer(false);

            if ($result === false) {
                throw new GoogleDriveUploadException("Upload did not complete for file: {$filename}");
            }

            return $result;
        } catch (GoogleDriveUploadException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (isset($service)) {
                $service->getClient()->setDefer(false);
            }
            throw new GoogleDriveUploadException("Failed to upload [{$filename}] to Google Drive: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Download a file from Drive to a local destination path via streaming.
     *
     * @throws GoogleDriveBackupException
     */
    public function download(string $fileId, string $destinationPath): void
    {
        try {
            $service = $this->getService();

            $response = $service->files->get($fileId, [
                'alt' => 'media',
                'supportsAllDrives' => true,
            ]);

            $destHandle = fopen($destinationPath, 'wb');
            if ($destHandle === false) {
                throw new GoogleDriveBackupException("Cannot write to destination: {$destinationPath}");
            }

            try {
                $body = $response->getBody();
                while (!$body->eof()) {
                    $chunk = $body->read(self::CHUNK_SIZE_BYTES);
                    fwrite($destHandle, $chunk);
                }
            } finally {
                fclose($destHandle);
            }
        } catch (GoogleDriveBackupException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleDriveBackupException("Failed to download file [{$fileId}]: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * List all backup files in a Drive folder with full pagination support.
     *
     * @return array<DriveFile>
     */
    public function listFiles(string $folderId, string $query = ''): array
    {
        $files = [];
        $pageToken = null;
        $service = $this->getService();

        $baseQuery = "'{$folderId}' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'";
        if (!empty($query)) {
            $baseQuery .= " and {$query}";
        }

        do {
            $optParams = [
                'q' => $baseQuery,
                'spaces' => 'drive',
                'fields' => 'nextPageToken, files(id, name, size, md5Checksum, createdTime, description, appProperties)',
                'pageSize' => self::PAGE_SIZE,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'orderBy' => 'createdTime desc',
            ];

            if ($pageToken !== null) {
                $optParams['pageToken'] = $pageToken;
            }

            $result = $service->files->listFiles($optParams);
            foreach ($result->getFiles() as $file) {
                $files[] = $file;
            }
            $pageToken = $result->getNextPageToken();
        } while ($pageToken !== null);

        return $files;
    }

    /**
     * Get a single file's metadata from Drive.
     */
    public function getFile(string $fileId): ?DriveFile
    {
        try {
            return $this->getService()->files->get($fileId, [
                'fields' => 'id, name, size, md5Checksum, createdTime, description, appProperties, trashed',
                'supportsAllDrives' => true,
            ]);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Delete a file from Drive.
     *
     * @throws GoogleDrivePermissionException
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->getService()->files->delete($fileId, [
                'supportsAllDrives' => true,
            ]);
            return true;
        } catch (Throwable $e) {
            if ($e->getCode() === 403) {
                throw new GoogleDrivePermissionException("Permission denied deleting file [{$fileId}]: " . $e->getMessage(), 403, $e);
            }
            if ($e->getCode() === 404) {
                return false; // already deleted
            }
            throw new GoogleDrivePermissionException("Failed to delete file [{$fileId}]: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Convert a raw DriveFile into a BackupArtifact.
     */
    public function driveFileToArtifact(DriveFile $file): BackupArtifact
    {
        $appProps = $file->getAppProperties() ?? [];
        $description = $file->getDescription() ?? '';
        $metadata = null;

        if (!empty($description)) {
            try {
                $metadata = BackupMetadata::from($description);
            } catch (Throwable) {
                $metadata = null;
            }
        }

        $createdTime = $file->getCreatedTime();
        $createdAt = null;
        if ($createdTime !== null) {
            try {
                $createdAt = new DateTimeImmutable($createdTime, new DateTimeZone('UTC'));
            } catch (Throwable) {
                $createdAt = null;
            }
        }

        return new BackupArtifact(
            id: (string) $file->getId(),
            filename: (string) $file->getName(),
            path: null,
            type: (string) ($appProps['backup_type'] ?? 'full'),
            createdAt: $createdAt,
            size: (int) $file->getSize(),
            checksum: $file->getMd5Checksum() ?: null,
            storageLocation: (string) $file->getId(),
            metadata: $metadata,
        );
    }
}
