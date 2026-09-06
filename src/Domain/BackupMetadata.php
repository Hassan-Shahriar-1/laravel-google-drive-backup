<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Domain;

use DateTimeImmutable;
use DateTimeInterface;

readonly class BackupMetadata
{
    public function __construct(
        public string $package = 'hassan-shahriar-1/laravel-google-drive-backup',
        public string $packageVersion = '1.0.0',
        public string $application = 'laravel',
        public string $environment = 'production',
        public string $type = 'full',
        public DateTimeImmutable $createdAt = new DateTimeImmutable('now', new \DateTimeZone('UTC')),
        public int $size = 0,
        public string $checksumAlgorithm = 'sha256',
        public ?string $checksum = null,
        public string $policy = 'default',
        public bool $encrypted = false,
        public array $extra = []
    ) {}

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'package' => $this->package,
            'package_version' => $this->packageVersion,
            'application' => $this->application,
            'environment' => $this->environment,
            'type' => $this->type,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'size' => $this->size,
            'checksum_algorithm' => $this->checksumAlgorithm,
            'checksum' => $this->checksum,
            'policy' => $this->policy,
            'encrypted' => $this->encrypted,
            'extra' => $this->extra,
        ];
    }

    /**
     * Serialize to JSON string for storage in Google Drive file description or properties.
     */
    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Reconstruct metadata from JSON or array.
     *
     * @param array<string, mixed>|string $data
     */
    public static function from(array|string $data): self
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (!is_array($decoded)) {
                $decoded = [];
            }
            $data = $decoded;
        }

        $createdAt = isset($data['created_at']) && is_string($data['created_at'])
            ? new DateTimeImmutable($data['created_at'])
            : new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            package: (string) ($data['package'] ?? 'hassan-shahriar-1/laravel-google-drive-backup'),
            packageVersion: (string) ($data['package_version'] ?? '1.0.0'),
            application: (string) ($data['application'] ?? 'laravel'),
            environment: (string) ($data['environment'] ?? 'production'),
            type: (string) ($data['type'] ?? 'full'),
            createdAt: $createdAt,
            size: (int) ($data['size'] ?? 0),
            checksumAlgorithm: (string) ($data['checksum_algorithm'] ?? 'sha256'),
            checksum: isset($data['checksum']) ? (string) $data['checksum'] : null,
            policy: (string) ($data['policy'] ?? 'default'),
            encrypted: (bool) ($data['encrypted'] ?? false),
            extra: is_array($data['extra'] ?? null) ? $data['extra'] : []
        );
    }
}
