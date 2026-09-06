<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Events;

use HassanShahriar\GoogleDriveBackup\Domain\RetentionResult;

class CleanupCompleted
{
    public function __construct(
        public readonly RetentionResult $result,
        public readonly bool $dryRun = false
    ) {}
}
