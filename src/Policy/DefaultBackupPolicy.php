<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Policy;

use HassanShahriar\GoogleDriveBackup\Contracts\BackupPolicy;

class DefaultBackupPolicy implements BackupPolicy
{
    public function __construct(
        protected string $policyName = 'default',
        protected string $backupType = 'full',
        protected array $rules = ['daily' => 30, 'weekly' => 8, 'monthly' => 12],
        protected bool $verification = true
    ) {}

    public function name(): string
    {
        return $this->policyName;
    }

    public function type(): string
    {
        return $this->backupType;
    }

    public function retentionRules(): array
    {
        return $this->rules;
    }

    public function requiresVerification(): bool
    {
        return $this->verification;
    }
}
