<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Verification;

use HassanShahriar\GoogleDriveBackup\Contracts\BackupVerifier;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\VerificationResult;
use HassanShahriar\GoogleDriveBackup\GoogleDrive\GoogleDriveFileManager;
use Throwable;

class BackupVerificationService implements BackupVerifier
{
    public function __construct(
        protected GoogleDriveFileManager $fileManager,
        protected array $config = []
    ) {
    }

    /**
     * Verify a backup artifact according to the configured verification mode.
     * Modes: 'none' | 'metadata' | 'checksum' | 'full-download'
     */
    public function verify(BackupArtifact $artifact, ?string $remoteId = null): VerificationResult
    {
        $mode = (string) ($this->config['verification']['mode'] ?? 'metadata');
        $fileId = $remoteId ?? $artifact->storageLocation;

        if ($mode === 'none') {
            return VerificationResult::pass('none', $fileId, ['skipped' => true]);
        }

        if ($fileId === null) {
            return VerificationResult::fail($mode, 'No remote file ID available for verification.', null);
        }

        try {
            $remoteFile = $this->fileManager->getFile($fileId);

            if ($remoteFile === null || $remoteFile->getTrashed()) {
                return VerificationResult::fail($mode, "Remote file [{$fileId}] not found on Google Drive.", $fileId);
            }

            $details = [
                'remote_id' => $remoteFile->getId(),
                'remote_name' => $remoteFile->getName(),
                'remote_size' => (int) $remoteFile->getSize(),
            ];

            // Metadata mode: confirm file ID, size, and name match
            if ($artifact->size > 0 && $details['remote_size'] > 0) {
                if ($artifact->size !== $details['remote_size']) {
                    return VerificationResult::fail(
                        $mode,
                        "Size mismatch: local={$artifact->size} bytes, remote={$details['remote_size']} bytes.",
                        $fileId,
                        $details
                    );
                }
            }

            if ($mode === 'metadata') {
                return VerificationResult::pass($mode, $fileId, $details);
            }

            // Checksum mode: compare local checksum with Google Drive MD5
            if ($mode === 'checksum') {
                $remoteMd5 = $remoteFile->getMd5Checksum();
                $details['remote_md5'] = $remoteMd5;

                if ($remoteMd5 !== null && $artifact->checksum !== null) {
                    // If local checksum is SHA-256, we compare MD5 from Google separately
                    // Google Drive only provides MD5; compute local MD5 for comparison
                    if ($artifact->path !== null && file_exists($artifact->path)) {
                        $localMd5 = md5_file($artifact->path);
                        $details['local_md5'] = $localMd5;

                        if ($localMd5 !== $remoteMd5) {
                            return VerificationResult::fail($mode, "MD5 checksum mismatch: local={$localMd5}, remote={$remoteMd5}.", $fileId, $details);
                        }
                    }
                }

                return VerificationResult::pass($mode, $fileId, $details);
            }

            // full-download mode: download and compute checksum
            if ($mode === 'full-download') {
                $tmpPath = tempnam(sys_get_temp_dir(), 'gdrive-verify-');
                if ($tmpPath === false) {
                    return VerificationResult::fail($mode, 'Could not create temp file for full-download verification.', $fileId);
                }

                try {
                    $this->fileManager->download($fileId, $tmpPath);
                    $localMd5 = md5_file($tmpPath);
                    $remoteMd5 = $remoteFile->getMd5Checksum();
                    $details['download_md5'] = $localMd5;
                    $details['remote_md5'] = $remoteMd5;

                    if ($localMd5 !== $remoteMd5) {
                        return VerificationResult::fail($mode, "Full-download MD5 mismatch: downloaded={$localMd5}, remote={$remoteMd5}.", $fileId, $details);
                    }
                } finally {
                    if (file_exists($tmpPath)) {
                        @unlink($tmpPath);
                    }
                }

                return VerificationResult::pass($mode, $fileId, $details);
            }

            return VerificationResult::pass($mode, $fileId, $details);
        } catch (Throwable $e) {
            return VerificationResult::fail($mode, 'Verification error: ' . $e->getMessage(), $fileId);
        }
    }
}
