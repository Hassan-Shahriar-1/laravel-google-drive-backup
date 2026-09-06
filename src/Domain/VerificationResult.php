<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Domain;

readonly class VerificationResult
{
    public function __construct(
        public bool $passed,
        public string $mode = 'metadata',
        public ?string $fileId = null,
        public array $details = [],
        public ?string $error = null
    ) {
    }

    public static function pass(string $mode, ?string $fileId = null, array $details = []): self
    {
        return new self(
            passed: true,
            mode: $mode,
            fileId: $fileId,
            details: $details
        );
    }

    public static function fail(string $mode, string $error, ?string $fileId = null, array $details = []): self
    {
        return new self(
            passed: false,
            mode: $mode,
            fileId: $fileId,
            details: $details,
            error: $error
        );
    }
}

