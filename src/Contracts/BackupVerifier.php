<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Contracts;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\VerificationResult;

interface BackupVerifier
{
    /**
     * Verify the integrity of a stored backup artifact.
     */
    public function verify(BackupArtifact $artifact, ?string $remoteId = null): VerificationResult;
}
