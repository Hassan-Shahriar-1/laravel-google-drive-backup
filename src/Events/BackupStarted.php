<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Events;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;

class BackupStarted
{
    public function __construct(
        public readonly string $type,
        public readonly string $policy = 'default'
    ) {
    }
}
