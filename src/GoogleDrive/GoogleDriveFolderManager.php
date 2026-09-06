<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\GoogleDrive;

use Google\Service\Drive\DriveFile;
use Google\Service\Drive as GoogleDriveService;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveFolderNotFoundException;
use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDrivePermissionException;
use Throwable;

class GoogleDriveFolderManager
{
    public const MIME_TYPE_FOLDER = 'application/vnd.google-apps.folder';

    public function __construct(
        protected GoogleDriveClient $client,
        protected array $config = []
    ) {
    }

    protected function getService(): GoogleDriveService
    {
        return $this->client->getDriveService();
    }

    /**
     * Resolve the target backup folder ID.
     * If folder_id is explicitly set in config, it validates access.
     * Otherwise, it searches by folder_name (default: "Laravel Backups") or creates it if not found.
     *
     * @throws GoogleDriveFolderNotFoundException
     * @throws GoogleDrivePermissionException
     */
    public function resolveRootFolderId(): string
    {
        $explicitId = $this->config['folder_id'] ?? null;
        if (!empty($explicitId)) {
            $this->validateFolderAccess((string) $explicitId);
            return (string) $explicitId;
        }

        $folderName = (string) ($this->config['folder_name'] ?? 'Laravel Backups');
        return $this->findOrCreateFolder($folderName);
    }

    /**
     * Validate that a folder ID exists and is accessible.
     *
     * @throws GoogleDriveFolderNotFoundException
     * @throws GoogleDrivePermissionException
     */
    public function validateFolderAccess(string $folderId): DriveFile
    {
        try {
            $optParams = [
                'fields' => 'id, name, mimeType, trashed, capabilities',
                'supportsAllDrives' => true,
            ];

            $file = $this->getService()->files->get($folderId, $optParams);

            if ($file->getTrashed()) {
                throw new GoogleDriveFolderNotFoundException("Google Drive folder [{$folderId}] is in trash.");
            }

            if ($file->getMimeType() !== self::MIME_TYPE_FOLDER) {
                throw new GoogleDriveFolderNotFoundException("Google Drive ID [{$folderId}] is not a folder.");
            }

            return $file;
        } catch (GoogleDriveFolderNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($e->getCode() === 404) {
                throw new GoogleDriveFolderNotFoundException("Google Drive folder [{$folderId}] not found.", 404, $e);
            }
            if ($e->getCode() === 403) {
                throw new GoogleDrivePermissionException("Permission denied accessing Google Drive folder [{$folderId}].", 403, $e);
            }
            throw new GoogleDriveFolderNotFoundException("Error validating folder [{$folderId}]: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Find a folder by name under an optional parent, or create it if missing.
     */
    public function findOrCreateFolder(string $folderName, ?string $parentId = null): string
    {
        $existingId = $this->findFolder($folderName, $parentId);
        if ($existingId !== null) {
            return $existingId;
        }

        return $this->createFolder($folderName, $parentId);
    }

    /**
     * Search for a folder by name under an optional parent folder.
     */
    public function findFolder(string $folderName, ?string $parentId = null): ?string
    {
        $escapedName = str_replace("'", "\\'", $folderName);
        $query = "mimeType = '" . self::MIME_TYPE_FOLDER . "' and name = '{$escapedName}' and trashed = false";

        if (!empty($parentId)) {
            $query .= " and '{$parentId}' in parents";
        }

        $optParams = [
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id, name)',
            'pageSize' => 1,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ];

        try {
            $results = $this->getService()->files->listFiles($optParams);
            $files = $results->getFiles();

            if (!empty($files) && count($files) > 0) {
                return (string) $files[0]->getId();
            }

            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Create a new folder on Google Drive.
     *
     * @throws GoogleDrivePermissionException
     */
    public function createFolder(string $folderName, ?string $parentId = null): string
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => self::MIME_TYPE_FOLDER,
            ]);

            if (!empty($parentId)) {
                $fileMetadata->setParents([$parentId]);
            }

            $optParams = [
                'fields' => 'id, name',
                'supportsAllDrives' => true,
            ];

            $folder = $this->getService()->files->create($fileMetadata, $optParams);
            return (string) $folder->getId();
        } catch (Throwable $e) {
            throw new GoogleDrivePermissionException("Failed to create Google Drive folder [{$folderName}]: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Resolve nested hierarchy, e.g. "application-name/production"
     */
    public function resolveSubfolderPath(string $rootFolderId, array $pathSegments): string
    {
        $currentParentId = $rootFolderId;

        foreach ($pathSegments as $segment) {
            $cleanSegment = trim($segment);
            if (empty($cleanSegment)) {
                continue;
            }

            $currentParentId = $this->findOrCreateFolder($cleanSegment, $currentParentId);
        }

        return $currentParentId;
    }
}
