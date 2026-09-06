<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Events;

class BackupDeleted
{
    public function __construct(
        public readonly string $fileId,
        public readonly string $filename
    ) {
    }
}
