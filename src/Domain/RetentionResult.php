<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Domain;

readonly class RetentionResult
{
    /**
     * @param array<BackupArtifact> $kept
     * @param array<BackupArtifact> $toDelete
     * @param array<string, mixed> $summary
     */
    public function __construct(
        public array $kept = [],
        public array $toDelete = [],
        public array $summary = []
    ) {}

    public function countKept(): int
    {
        return count($this->kept);
    }

    public function countToDelete(): int
    {
        return count($this->toDelete);
    }
}
