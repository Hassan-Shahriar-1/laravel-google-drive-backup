<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Events;

use Throwable;

class BackupFailed
{
    public function __construct(
        public readonly string $type,
        public readonly string $policy,
        public readonly Throwable $exception
    ) {}
}
