# Changelog

All notable changes to `hassan-shahriar-1/laravel-google-drive-backup` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-06

### Added
- Laravel package skeleton with PSR-4 autoloading
- Google OAuth 2.0 authentication via `google/apiclient`
- `GoogleDriveClient` — token refresh and API connection management
- `GoogleDriveFolderManager` — folder lookup, creation, and hierarchy resolution
- `GoogleDriveFileManager` — chunked resumable upload, streaming download, paginated listing, file deletion
- `GoogleDriveStorage` — implements `BackupStorage` contract
- `BackupArtifact`, `BackupMetadata`, `StorageResult`, `VerificationResult`, `RetentionResult` domain objects
- Core contracts: `BackupStorage`, `BackupCreator`, `BackupVerifier`, `RetentionManager`, `BackupPolicy`
- Exception hierarchy with automatic credential redaction
- `FilenameGenerator` service with sanitization and UTC timestamps
- `TemporaryFileManager` with `try/finally` cleanup and `withTempFile()` helper
- `BackupPolicyManager` with pluggable policy registration
- `RetentionCalculator` — deterministic daily/weekly/monthly retention with overlap protection
- `BackupVerificationService` — metadata, checksum, and full-download verification modes
- Events: `BackupStarted`, `BackupCreated`, `BackupUploaded`, `BackupVerified`, `BackupFailed`, `BackupDeleted`, `CleanupCompleted`
- Artisan commands: `backup:google-drive`, `backup:google-drive:test`, `backup:google-drive:list`, `backup:google-drive:clean`, `backup:google-drive:verify`, `backup:google-drive:download`, `backup:google-drive:restore`
- GitHub Actions CI workflow (PHP 8.3/8.4, Laravel 11/12 matrix)
- Comprehensive unit and feature test suite (PHPUnit 11, Orchestra Testbench 10)
- Full documentation suite (`docs/`)
