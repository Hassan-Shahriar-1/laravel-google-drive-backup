# Changelog

All notable changes to `hassan-shahriar-1/laravel-google-drive-backup` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.1] - 2026-09-16

### Fixed
- Fixed ID truncation in `backup:google-drive:list` so the full 33+ character Google Drive file ID is displayed.
- Added smart file resolution across `backup:google-drive:restore-db` and `backup:google-drive:download` to support targeting backups by filename, truncated ID prefix, or full ID.

## [2.2.0] - 2026-09-14

### Added
- Dedicated `backup:google-drive:restore-db` command for automated database restoration directly from Google Drive (alias: `restore`).
- Dynamic path targeting via `--path` option to back up specific directories or files.
- Subfolder discovery in `backup:google-drive:list` displaying backups across root and child subfolders with a `Folder` column.
- Connection validation with clear error messages when database driver is unconfigured.

### Changed
- Removed redundant `--policy` CLI option; retention rules are directly read from `.env`.
- Cleaned up list table output.
- Made upgrading documentation version-agnostic.

### Added
- Expanded framework support to include all Laravel versions `>=10.0` (Laravel 10, 11, 12, and 13+).
- Added `backup:google-drive:refresh-token` interactive command (and `token` alias) to generate and auto-save OAuth refresh tokens into `.env`.
- Added support for all major Laravel database drivers: `mysql`, `mariadb`, `pgsql`, `sqlite`, and `sqlsrv`.
- Automatic database connection resolution using `DB_CONNECTION` from `.env` or `config('database.default')`, with dynamic CLI override via `--connection=<driver>`.
- Added `--db` shortcut option to `backup:google-drive` command.
- Added dedicated `backup:google-drive:restore-db` command for automated database restoration directly from Google Drive.
- Added `--subfolder=` and `--by-type` options across backup commands, allowing custom subfolder destinations (e.g. `database/`, `others/`) while defaulting to root folder when omitted.
- Added dynamic path targeting via `--path` option to back up specific directories or files.

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

