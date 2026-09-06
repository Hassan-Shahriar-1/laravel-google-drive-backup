<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Policy;

use HassanShahriar\GoogleDriveBackup\Contracts\BackupPolicy;
use HassanShahriar\GoogleDriveBackup\Exceptions\BackupPolicyException;

class BackupPolicyManager
{
    /** @var array<string, BackupPolicy> */
    protected array $policies = [];

    public function __construct(protected array $config = [])
    {
        $this->registerDefaultPolicy();
    }

    protected function registerDefaultPolicy(): void
    {
        $retention = $this->config['retention'] ?? ['daily' => 30, 'weekly' => 8, 'monthly' => 12];

        $this->policies['default'] = new DefaultBackupPolicy(
            policyName: 'default',
            backupType: 'full',
            rules: $retention,
            verification: (bool) ($this->config['verification']['enabled'] ?? true),
        );
    }

    /**
     * Register a custom policy.
     */
    public function register(BackupPolicy $policy): self
    {
        $this->policies[$policy->name()] = $policy;
        return $this;
    }

    /**
     * Resolve a policy by name, falling back to 'default'.
     *
     * @throws BackupPolicyException
     */
    public function resolve(string $name = 'default'): BackupPolicy
    {
        if (isset($this->policies[$name])) {
            return $this->policies[$name];
        }

        throw new BackupPolicyException("Backup policy [{$name}] is not registered. Available policies: " . implode(', ', array_keys($this->policies)));
    }

    /**
     * Get all registered policies.
     *
     * @return array<string, BackupPolicy>
     */
    public function all(): array
    {
        return $this->policies;
    }
}
