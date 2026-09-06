<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Contracts;

interface BackupPolicy
{
    /**
     * Get the policy name.
     */
    public function name(): string;

    /**
     * Get target backup type ('full', 'database', 'files').
     */
    public function type(): string;

    /**
     * Get the retention rules for this policy.
     *
     * @return array{daily?: int, weekly?: int, monthly?: int}
     */
    public function retentionRules(): array;

    /**
     * Whether verification is enforced after upload.
     */
    public function requiresVerification(): bool;
}
