<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Contracts;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;

interface BackupCreator
{
    /**
     * Create a backup artifact for the given type ('full', 'database', 'files').
     */
    public function create(string $type = 'full', array $options = []): BackupArtifact;
}
