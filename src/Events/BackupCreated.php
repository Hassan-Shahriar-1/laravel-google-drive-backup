<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Events;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;

class BackupCreated
{
    public function __construct(
        public readonly BackupArtifact $artifact
    ) {}
}
