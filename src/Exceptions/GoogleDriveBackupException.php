<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Exceptions;

use RuntimeException;
use Throwable;

class GoogleDriveBackupException extends RuntimeException
{
    /**
     * Redact sensitive credentials from error messages.
     */
    protected static function sanitizeMessage(string $message): string
    {
        return preg_replace(
            ['/(client_secret=)[^&\s]+/i', '/(refresh_token=)[^&\s]+/i', '/(access_token=)[^&\s]+/i'],
            '$1[REDACTED]',
            $message
        ) ?? $message;
    }

    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(self::sanitizeMessage($message), $code, $previous);
    }
}
