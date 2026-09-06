<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Contracts;

use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\RetentionResult;

interface RetentionManager
{
    /**
     * Determine which backups should be kept and which should be deleted
     * according to daily, weekly, and monthly retention policy rules.
     *
     * @param array<BackupArtifact> $backups
     * @param array{daily?: int, weekly?: int, monthly?: int} $rules
     */
    public function determineBackupsToDelete(array $backups, array $rules): RetentionResult;
}
