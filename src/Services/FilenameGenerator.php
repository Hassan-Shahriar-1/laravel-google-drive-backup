<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Services;

use DateTimeImmutable;
use DateTimeZone;

class FilenameGenerator
{
    /**
     * Default filename format tokens:
     * {app}  - sanitized application name
     * {env}  - environment (production, staging…)
     * {type} - backup type (full, database, files)
     * {date} - UTC date YYYYMMDD
     * {time} - UTC time HHmmss
     * {uuid} - first 8 chars of a UUID v4
     */
    public function generate(
        string $appName,
        string $environment,
        string $type,
        string $format = '{app}-{env}-{type}-{date}-{time}-{uuid}.zip'
    ): string {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $tokens = [
            '{app}'  => $this->sanitize($appName),
            '{env}'  => $this->sanitize($environment),
            '{type}' => $this->sanitize($type),
            '{date}' => $now->format('Ymd'),
            '{time}' => $now->format('His'),
            '{uuid}' => substr($this->generateUuid(), 0, 8),
        ];

        return str_replace(array_keys($tokens), array_values($tokens), $format);
    }

    /**
     * Sanitize a token value for safe use in a filename.
     * Allows alphanumeric characters, hyphens, and underscores only.
     */
    public function sanitize(string $value): string
    {
        // Lowercase, replace spaces/dots with hyphens, strip invalid chars
        $value = strtolower(trim($value));
        $value = preg_replace('/[\s.]+/', '-', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9\-_]/', '', $value) ?? $value;
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        return trim($value, '-');
    }

    /**
     * Generate a UUID v4.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
