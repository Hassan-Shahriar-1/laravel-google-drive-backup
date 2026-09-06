<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Domain;

use DateTimeImmutable;
use DateTimeInterface;

class BackupArtifact
{
    public function __construct(
        public string $id,
        public string $filename,
        public ?string $path = null,
        public string $type = 'full',
        public ?DateTimeImmutable $createdAt = null,
        public int $size = 0,
        public ?string $checksum = null,
        public ?string $storageLocation = null, // e.g. Google Drive file ID
        public ?BackupMetadata $metadata = null
    ) {
        $this->createdAt ??= new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->metadata ??= new BackupMetadata(
            type: $this->type,
            createdAt: $this->createdAt,
            size: $this->size,
            checksum: $this->checksum
        );
    }

    /**
     * Determine whether the local backup file exists.
     */
    public function existsLocally(): bool
    {
        return $this->path !== null && file_exists($this->path);
    }

    /**
     * Calculate SHA-256 checksum from the local file if available.
     */
    public function calculateChecksum(string $algorithm = 'sha256'): string
    {
        if (!$this->existsLocally() || $this->path === null) {
            return '';
        }

        $this->checksum = hash_file($algorithm, $this->path) ?: '';
        return $this->checksum;
    }

    /**
     * Get the size in human readable format.
     */
    public function formattedSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
