<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Contracts;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\StorageResult;

interface BackupStorage
{
    /**
     * Upload or store a backup artifact.
     */
    public function put(BackupArtifact $backup): StorageResult;

    /**
     * List all remote backups matching optional folder / prefix.
     *
     * @return iterable<BackupArtifact>
     */
    public function list(?string $folderId = null): iterable;

    /**
     * Get a specific remote backup artifact by its remote ID.
     */
    public function get(string $id): ?BackupArtifact;

    /**
     * Stream download a backup artifact to a local destination file.
     */
    public function download(string $id, string $destination): void;

    /**
     * Delete a remote backup artifact by ID.
     */
    public function delete(string $id): bool;

    /**
     * Check if a remote backup artifact exists.
     */
    public function exists(string $id): bool;
}
