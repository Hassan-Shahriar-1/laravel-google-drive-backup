<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Events;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\VerificationResult;

class BackupVerified
{
    public function __construct(
        public readonly BackupArtifact $artifact,
        public readonly VerificationResult $result
    ) {}
}
