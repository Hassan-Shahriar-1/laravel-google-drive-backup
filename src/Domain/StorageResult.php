<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Domain;

readonly class StorageResult
{
    public function __construct(
        public bool $success,
        public ?string $fileId = null,
        public string $filename = '',
        public int $size = 0,
        public ?string $checksum = null,
        public ?string $error = null,
        public array $raw = []
    ) {
    }

    public static function success(
        string $fileId,
        string $filename,
        int $size,
        ?string $checksum = null,
        array $raw = []
    ): self {
        return new self(
            success: true,
            fileId: $fileId,
            filename: $filename,
            size: $size,
            checksum: $checksum,
            raw: $raw
        );
    }

    public static function failure(string $error, string $filename = ''): self
    {
        return new self(
            success: false,
            filename: $filename,
            error: $error
        );
    }
}

