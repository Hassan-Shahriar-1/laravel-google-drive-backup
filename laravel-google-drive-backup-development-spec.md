# Laravel Google Drive Backup Policy Package
## Complete Development, Testing, Documentation, Release, and Publishing Specification

> **Purpose:** This document is a complete implementation specification for developing a production-ready Laravel package that provides policy-based backups to Google Drive.
>
> **Primary implementation target:** Laravel 12 / PHP 8.3+
>
> **Important architectural decision:** This package is **not** intended to replace Spatie Laravel Backup. It should integrate with the Laravel backup ecosystem and focus on Google Drive storage, backup policy, retention, verification, scheduling, and operational tooling.

---

# 1. Product Definition

## 1.1 Package goal

Build a Laravel package that allows a Laravel application to:

1. Generate application/database backups using a proven backup engine.
2. Store backups in Google Drive.
3. Apply configurable backup policies.
4. Automatically manage daily/weekly/monthly retention.
5. Verify uploaded backups.
6. List and inspect remote backups.
7. Delete backups according to policy.
8. Restore/download a selected backup.
9. Run manually through Artisan commands.
10. Run automatically through Laravel's scheduler.
11. Provide useful logs and failure handling.
12. Be distributed as a normal Composer package.
13. Be installable into a fresh Laravel application with minimal configuration.
14. Be suitable for publication on Packagist/GitHub.

## 1.2 Non-goals for v1

Do NOT build these unless they are required by the chosen implementation:

- A completely new database dump engine.
- A completely new ZIP/archive implementation.
- A web dashboard.
- A custom cloud storage abstraction that duplicates Flysystem unnecessarily.
- A replacement for Laravel's scheduler.
- A replacement for an established backup engine.
- Support for every cloud provider in v1.
- Complex multi-server orchestration.

The first release should be focused and reliable.

---

# 2. Product Positioning

Recommended package name:

`vendor/laravel-google-drive-backup`

Possible package namespace:

`Vendor\GoogleDriveBackup`

Before publishing, replace `vendor` with the actual Packagist/GitHub vendor name.

The package should clearly communicate:

> Policy-based Laravel backup management with Google Drive as a backup destination.

Do not copy Spatie's branding, class names, documentation wording, or implementation.

---

# 3. Recommended Architecture

Use a layered architecture.

```text
Laravel Application
        |
        v
Google Drive Backup Policy Package
        |
        +----------------------+
        |                      |
        v                      v
Backup Engine             Policy Engine
        |                      |
        v                      v
Backup Artifact          Retention Rules
        |                      |
        +----------+-----------+
                   |
                   v
             Storage Layer
                   |
                   v
             Google Drive API
```

The package should keep the following concerns separate:

```text
Backup creation
      !=
Google Drive storage
      !=
Retention policy
      !=
Verification
      !=
Scheduling
      !=
CLI
```

This separation is important for maintainability and future extension.

---

# 4. Technology Requirements

## 4.1 Required

- PHP 8.3+
- Laravel 12+
- Composer
- PHPUnit or Pest
- Laravel Orchestra Testbench
- Google Drive API
- Google OAuth 2.0
- PSR-compatible logging
- Flysystem where appropriate
- Git
- GitHub
- Packagist

## 4.2 Development environment

The package must be developed independently from a specific Laravel application.

Recommended repository:

```text
laravel-google-drive-backup/
```

Do not develop the package only inside an application's `app/` directory.

---

# 5. Create the Package Project

Create the repository:

```bash
mkdir laravel-google-drive-backup
cd laravel-google-drive-backup
git init
```

Initialize Composer:

```bash
composer init
```

Use a package-oriented `composer.json`.

Recommended structure:

```text
laravel-google-drive-backup/
├── src/
├── config/
├── tests/
├── resources/
├── docs/
├── examples/
├── .github/
├── composer.json
├── phpunit.xml
├── README.md
├── LICENSE
├── CHANGELOG.md
├── CONTRIBUTING.md
├── SECURITY.md
└── .gitignore
```

---

# 6. Composer Configuration

The package must use PSR-4 autoloading.

Example:

```json
{
    "name": "vendor/laravel-google-drive-backup",
    "description": "Policy-based Laravel backups with Google Drive support.",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^10.0",
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "Vendor\\GoogleDriveBackup\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Vendor\\GoogleDriveBackup\\Tests\\": "tests/"
        }
    }
}
```

Use the current compatible Testbench/PHPUnit versions after verifying them during implementation.

Do not hard-code versions without checking Composer compatibility.

---

# 7. Google Drive Integration

## 7.1 API approach

Use Google's official Google Drive API/client libraries or a well-maintained compatible library.

The implementation must support:

- OAuth 2.0
- Refresh tokens
- Folder lookup
- Folder creation where configured
- File upload
- File listing
- File metadata
- File download
- File deletion

Do not store Google access tokens directly in source code.

---

# 8. Authentication Design

The preferred production authentication method is OAuth 2.0.

Configuration should support:

```env
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=
```

Optional:

```env
GOOGLE_DRIVE_SHARED_DRIVE_ID=
GOOGLE_DRIVE_ROOT_FOLDER=
```

Never log:

- Client secret
- Refresh token
- Access token
- Authorization code

Sensitive values must be redacted in exceptions/logs.

---

# 9. Google OAuth Setup Documentation

The package repository must contain a detailed guide:

```text
docs/google-drive-setup.md
```

It should explain:

1. Create/select Google Cloud project.
2. Enable Google Drive API.
3. Configure OAuth consent screen.
4. Configure OAuth client.
5. Obtain authorization.
6. Obtain refresh token.
7. Configure `.env`.
8. Configure the package.
9. Test connectivity.
10. Troubleshoot authentication.

Do not commit credentials.

Add examples using placeholders only.

---

# 10. Configuration File

Create:

```text
config/google-drive-backup.php
```

Recommended configuration:

```php
return [

    'enabled' => env('GOOGLE_DRIVE_BACKUP_ENABLED', true),

    'disk' => env('GOOGLE_DRIVE_BACKUP_DISK', 'google'),

    'folder_id' => env('GOOGLE_DRIVE_BACKUP_FOLDER_ID'),

    'folder_name' => env('GOOGLE_DRIVE_BACKUP_FOLDER_NAME', 'Laravel Backups'),

    'filename' => [
        'format' => '{app}-{type}-{date}-{time}-{uuid}.zip',
    ],

    'backup' => [
        'database' => true,
        'files' => true,
    ],

    'compression' => [
        'enabled' => true,
        'format' => 'zip',
    ],

    'encryption' => [
        'enabled' => false,
        'driver' => 'openssl',
    ],

    'verification' => [
        'enabled' => true,
        'checksum' => 'sha256',
    ],

    'retention' => [
        'daily' => 30,
        'weekly' => 8,
        'monthly' => 12,
    ],

    'logging' => [
        'enabled' => true,
        'channel' => env('GOOGLE_DRIVE_BACKUP_LOG_CHANNEL', 'stack'),
    ],

];
```

The final configuration should be reviewed during implementation and simplified where possible.

---

# 11. Package Service Provider

Create:

```text
src/GoogleDriveBackupServiceProvider.php
```

Responsibilities:

- Register configuration.
- Merge package config.
- Register package services.
- Register Artisan commands.
- Register storage integration.
- Publish configuration.
- Publish documentation only when appropriate.

Example behavior:

```php
$this->mergeConfigFrom(
    __DIR__.'/../config/google-drive-backup.php',
    'google-drive-backup'
);
```

Allow:

```bash
php artisan vendor:publish --tag=google-drive-backup-config
```

---

# 12. Core Interfaces

Use interfaces so implementation details remain replaceable.

Recommended:

```text
src/Contracts/
├── BackupCreator.php
├── BackupStorage.php
├── BackupVerifier.php
├── RetentionManager.php
└── BackupPolicy.php
```

Example:

```php
interface BackupStorage
{
    public function put(BackupArtifact $backup): StorageResult;

    public function list(): iterable;

    public function get(string $id): BackupArtifact;

    public function download(string $id, string $destination): void;

    public function delete(string $id): void;

    public function exists(string $id): bool;
}
```

The exact method signatures should be finalized after the domain model is designed.

---

# 13. Domain Objects

Create value/domain objects instead of passing arrays everywhere.

Recommended:

```text
src/Domain/
├── BackupArtifact.php
├── BackupMetadata.php
├── BackupPolicyDefinition.php
├── RetentionResult.php
├── StorageResult.php
└── VerificationResult.php
```

A backup artifact should contain information such as:

```text
id
filename
type
created_at
size
checksum
storage_location
backup_policy
metadata
```

---

# 14. Backup Types

Support at least:

```text
database
files
full
```

Example:

```text
production-database-2026-09-06.zip
production-files-2026-09-06.zip
production-full-2026-09-06.zip
```

Use UTC internally for timestamps.

Display local timezone only where explicitly configured.

---

# 15. Backup Creation

The package should integrate with the selected backup engine.

Preferred approach:

```text
Package
   |
   +--> invoke/consume backup engine
   |
   +--> receive generated backup artifact
   |
   +--> calculate metadata
   |
   +--> upload to Google Drive
```

Do not tightly couple the entire package to internal implementation details of another package if a stable integration mechanism exists.

If Spatie Laravel Backup is used, document it as a dependency/integration requirement and pin a compatible version range.

---

# 16. Google Drive Storage Service

Create:

```text
src/GoogleDrive/
├── GoogleDriveClient.php
├── GoogleDriveStorage.php
├── GoogleDriveFolderManager.php
└── GoogleDriveFileManager.php
```

Responsibilities:

### GoogleDriveClient

- Authenticate.
- Create/configure API client.
- Refresh access token.
- Return Drive service.

### GoogleDriveFolderManager

- Find folder.
- Create folder if configured.
- Validate access.
- Resolve nested backup folder.

### GoogleDriveFileManager

- Upload.
- Download.
- List.
- Delete.
- Retrieve metadata.

---

# 17. Folder Structure on Google Drive

Recommended:

```text
Laravel Backups/
│
├── production/
│   ├── daily/
│   ├── weekly/
│   └── monthly/
│
├── staging/
│   ├── daily/
│   ├── weekly/
│   └── monthly/
│
└── development/
    ├── daily/
    ├── weekly/
    └── monthly/
```

Allow the user to customize this.

Do not require multiple folders if the user prefers a single folder.

---

# 18. Backup Policy Engine

Create:

```text
src/Policy/
├── BackupPolicyManager.php
├── RetentionPolicy.php
├── RetentionCalculator.php
└── PolicyResolver.php
```

A policy should determine:

- When a backup is created.
- What is backed up.
- Where it is stored.
- How long it is retained.
- Whether verification is required.
- Whether encryption is required.

---

# 19. Retention Policy

Example:

```php
'retention' => [
    'daily' => 30,
    'weekly' => 8,
    'monthly' => 12,
],
```

Interpretation:

- Keep 30 daily backups.
- Keep 8 weekly backups.
- Keep 12 monthly backups.

The cleanup algorithm must avoid deleting a backup that is still required by another retention category.

Example:

```text
Backup A
  daily + weekly
       |
       +--> must not be deleted when daily expires
            if weekly retention still requires it
```

This must be covered by automated tests.

---

# 20. Retention Algorithm

Implement deterministic retention logic.

Suggested process:

```text
1. Fetch all managed backups.
2. Group by application/environment/policy.
3. Sort by creation time.
4. Determine daily candidates.
5. Determine weekly candidates.
6. Determine monthly candidates.
7. Build protected backup set.
8. Mark expired backups.
9. Delete only backups outside protected set.
10. Verify deletion result.
11. Return cleanup report.
```

Do not rely only on filename parsing when metadata can be stored reliably.

---

# 21. Backup Metadata

Each backup should have machine-readable metadata.

Recommended:

```json
{
    "package": "vendor/laravel-google-drive-backup",
    "package_version": "1.0.0",
    "application": "example",
    "environment": "production",
    "type": "full",
    "created_at": "2026-09-06T02:00:00Z",
    "size": 123456789,
    "checksum_algorithm": "sha256",
    "checksum": "...",
    "policy": "production",
    "encrypted": false
}
```

If Google Drive custom properties are used, document their limitations.

A local/sidecar manifest may also be used if necessary.

---

# 22. Backup Verification

Verification should happen after upload.

Minimum verification:

```text
1. Upload backup.
2. Confirm Google Drive file ID.
3. Confirm file size.
4. Confirm metadata.
5. Optionally download and checksum.
6. Return VerificationResult.
```

For normal daily operation, avoid downloading huge backups just to verify them unless explicitly configured.

Configuration:

```php
'verification' => [
    'enabled' => true,
    'mode' => 'metadata',
    'checksum' => 'sha256',
],
```

Possible modes:

```text
none
metadata
checksum
full-download
```

---

# 23. Encryption

Encryption should be optional.

If implemented:

```text
source backup
      |
      v
encrypt
      |
      v
compress OR compress then encrypt
      |
      v
Google Drive
```

The final order must be chosen deliberately and documented.

Never upload an encryption key to Google Drive beside the encrypted backup.

Keys must come from secure application/server configuration.

Do not log keys.

---

# 24. Filename Strategy

Use predictable but unique filenames.

Example:

```text
myapp-production-full-20260906-020000-550e8400.zip
```

Requirements:

- Application identifier.
- Environment.
- Backup type.
- UTC timestamp.
- Unique ID.

Avoid user-controlled arbitrary path traversal.

Sanitize application names before using them in filenames.

---

# 25. Artisan Commands

Implement:

```text
backup:google-drive
backup:google-drive:test
backup:google-drive:list
backup:google-drive:clean
backup:google-drive:verify
backup:google-drive:download
backup:google-drive:restore
```

The command naming can be simplified before release if a better CLI design emerges.

## 25.1 Run

```bash
php artisan backup:google-drive
```

Options:

```bash
--policy=
--type=
--force
--no-verify
```

## 25.2 Test

```bash
php artisan backup:google-drive:test
```

Should test:

```text
Google credentials
Drive API
Folder access
Upload permission
Optional test upload
```

## 25.3 List

```bash
php artisan backup:google-drive:list
```

Output:

```text
ID
Filename
Type
Size
Created
Policy
Verified
```

## 25.4 Clean

```bash
php artisan backup:google-drive:clean
```

Support:

```bash
--dry-run
--policy=
```

`--dry-run` is mandatory before deleting backups.

## 25.5 Verify

```bash
php artisan backup:google-drive:verify
```

Support:

```bash
--id=
--all
```

## 25.6 Download

```bash
php artisan backup:google-drive:download {id}
```

Require an explicit destination.

Do not overwrite files unexpectedly.

## 25.7 Restore

Restore must be implemented cautiously.

Recommended v1 behavior:

1. Download selected backup.
2. Validate it.
3. Extract to a temporary directory.
4. Require explicit confirmation.
5. Restore database/files.
6. Report result.

Do not make production restore destructive by default.

---

# 26. Scheduler Integration

The package should not create hidden schedules.

Allow the application developer to schedule commands explicitly.

Example:

```php
Schedule::command('backup:google-drive')
    ->dailyAt('02:00');
```

Cleanup:

```php
Schedule::command('backup:google-drive:clean')
    ->dailyAt('03:00');
```

Verification:

```php
Schedule::command('backup:google-drive:verify')
    ->dailyAt('04:00');
```

Document Laravel scheduler setup.

---

# 27. Queues

Large backups should support queued execution where practical.

Potential API:

```php
Backup::dispatch('production');
```

or a command option:

```bash
php artisan backup:google-drive --queue
```

Do not force queues in v1 if they complicate reliability.

If queue support is included:

- Jobs must be retryable.
- Uploads must be idempotent where possible.
- Duplicate uploads should be detectable.
- Failed jobs must not silently lose backups.

---

# 28. Failure Handling

Every external operation must have clear failure behavior.

Possible failures:

```text
OAuth failure
API unavailable
Permission denied
Folder not found
Quota exceeded
Network timeout
Upload interrupted
Backup creation failed
Checksum mismatch
Cleanup failure
Restore failure
```

Use specific exceptions:

```text
GoogleDriveAuthenticationException
GoogleDrivePermissionException
GoogleDriveUploadException
GoogleDriveDownloadException
BackupVerificationException
BackupPolicyException
```

Do not expose secrets in exception messages.

---

# 29. Retry Strategy

Transient Google API/network failures should support retries.

Recommended:

```text
Attempt 1
wait 1 second

Attempt 2
wait 2 seconds

Attempt 3
wait 4 seconds
```

Use bounded exponential backoff.

Do not retry permanent errors such as:

- Invalid credentials.
- Invalid folder ID.
- Permission denied.

---

# 30. Logging

Use Laravel's logging system.

Example:

```text
Backup started
Backup created
Upload started
Upload completed
Verification started
Verification completed
Cleanup started
Backup deleted
Backup failed
```

Do not log:

- Access tokens.
- Refresh tokens.
- Client secrets.
- Encryption keys.
- Database passwords.

---

# 31. Events

Optional but recommended.

Create events:

```text
BackupStarted
BackupCreated
BackupUploaded
BackupVerified
BackupFailed
BackupDeleted
CleanupCompleted
```

This lets applications integrate:

```php
Event::listen(BackupFailed::class, ...);
```

without modifying your package.

---

# 32. Notifications

Do not force notifications into the package.

Instead provide events.

The consuming Laravel application can connect events to:

- Mail
- Slack
- Teams
- Discord
- Custom notification systems

This keeps the package lightweight.

---

# 33. Security Requirements

Mandatory:

- No credentials in Git.
- No secrets in logs.
- OAuth refresh token stored only in secure environment/configuration.
- Validate Google Drive folder permissions.
- Sanitize filenames.
- Prevent path traversal.
- Use HTTPS APIs.
- Validate downloaded backup before restore.
- Use temporary directories securely.
- Remove temporary files after completion.
- Avoid leaving plaintext backup files unnecessarily.
- Encryption keys must be managed outside Google Drive.

---

# 34. Local Temporary Storage

The package may need temporary storage.

Use Laravel's filesystem/temp facilities.

Requirements:

```text
create temp file
write backup
upload
verify
delete temp file
```

Cleanup must happen even after exceptions.

Use `try/finally` or equivalent resource cleanup.

---

# 35. Testing Strategy

Use Orchestra Testbench to test the package as a Laravel package.

Test categories:

```text
Unit
Integration
Feature
Google Drive integration
Console commands
Configuration
Retention
Security
Failure handling
```

---

# 36. Unit Tests

Minimum tests:

### Policy

```text
creates correct policy
resolves default policy
rejects invalid retention
```

### Retention

```text
keeps daily backups
keeps weekly backups
keeps monthly backups
protects overlapping backups
deletes expired backups
dry-run does not delete
```

### Filename

```text
generates unique names
sanitizes application name
contains backup type
contains timestamp
```

### Metadata

```text
creates valid metadata
calculates checksum
handles missing metadata
```

---

# 37. Google Drive Integration Tests

Use mocks/fakes for normal CI.

Do not require a real Google account for every test.

Test:

```text
authentication success
authentication failure
folder found
folder missing
folder creation
upload success
upload failure
download success
delete success
permission denied
API timeout
```

Real Google Drive integration tests should be optional and run only when credentials are explicitly provided.

Never put real credentials in CI source.

---

# 38. Artisan Tests

Use Laravel console testing.

Test:

```text
backup command success
backup command failure
test command
list command
clean command
clean --dry-run
verify command
download command
invalid policy
invalid backup ID
```

---

# 39. Test Coverage

Target:

```text
>= 90% overall
```

Critical components should have near-complete coverage:

```text
Retention
GoogleDriveStorage
PolicyManager
Verification
Commands
```

Coverage should be measured in CI.

---

# 40. Static Analysis

Use a static analyzer such as PHPStan/Larastan.

Recommended target:

```text
PHPStan level 8
```

Do not ignore errors broadly.

Any suppression must be justified.

---

# 41. Code Style

Use Laravel/PHP conventions.

Recommended:

```text
PSR-12 / PHP-CS-Fixer
```

Add CI checks for:

```bash
composer test
composer analyse
composer format -- --dry-run
```

Use strict typing where appropriate:

```php
declare(strict_types=1);
```

---

# 42. Database Independence

The package should not require its own database for basic operation.

Backup metadata should preferably be derived from:

- backup files
- Google Drive metadata
- manifest metadata

If a local database is introduced later, make it optional.

---

# 43. Multi-Environment Support

Support:

```text
local
development
staging
production
```

Environment should be part of backup metadata.

Prevent staging cleanup from accidentally deleting production backups.

Use an explicit namespace/folder/policy boundary.

---

# 44. Multi-Application Google Drive

One Google Drive account may store backups for multiple Laravel applications.

Recommended folder structure:

```text
Laravel Backups/
    application-a/
        production/
        staging/

    application-b/
        production/
        staging/
```

The package should provide a configurable application identifier.

---

# 45. Idempotency

A failed command must not create uncontrolled duplicate backups.

Use a unique backup ID.

For uploads:

```text
backup ID
+
checksum
+
filename
```

can be used to identify an existing backup.

If an upload succeeds but the process crashes before recording success, the next execution should be able to detect the existing object.

---

# 46. Google Drive Quotas

Document Google Drive API/storage limitations.

The package should:

- Handle API rate limiting.
- Respect retry-after information where available.
- Avoid unnecessary API requests.
- Use pagination for file listing.
- Avoid loading thousands of files into memory unnecessarily.

---

# 47. Large Files

Design uploads for large backup files.

Do not load an entire backup into PHP memory.

Prefer:

```text
stream/file-based upload
```

over:

```text
file_get_contents()
```

for large files.

---

# 48. Pagination

Google Drive file listing must support pagination.

Never assume one API request returns every backup.

Example:

```text
page 1
  ↓
page 2
  ↓
page 3
  ↓
...
```

Stop only when the API indicates there are no more pages.

---

# 49. Documentation Structure

Repository:

```text
docs/
├── installation.md
├── configuration.md
├── google-drive-setup.md
├── authentication.md
├── policies.md
├── retention.md
├── commands.md
├── scheduling.md
├── restoration.md
├── troubleshooting.md
├── security.md
├── testing.md
└── upgrading.md
```

README should remain concise and link to detailed documentation.

---

# 50. README Requirements

README must include:

1. Package description.
2. Features.
3. Requirements.
4. Installation.
5. Google Drive setup.
6. Configuration.
7. First backup.
8. Scheduling.
9. Retention.
10. Testing.
11. Security.
12. Troubleshooting.
13. Contributing.
14. License.
15. Version compatibility.

Include badges where appropriate:

```text
Build
Tests
Coverage
PHP
Laravel
Packagist
License
```

Do not add badges that do not actually exist.

---

# 51. Installation Documentation

Expected user flow:

```bash
composer require vendor/laravel-google-drive-backup
```

Then:

```bash
php artisan vendor:publish \
    --tag=google-drive-backup-config
```

Then configure:

```env
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=
```

Then test:

```bash
php artisan backup:google-drive:test
```

Then run:

```bash
php artisan backup:google-drive
```

---

# 52. Versioning

Use Semantic Versioning:

```text
MAJOR.MINOR.PATCH
```

Example:

```text
1.0.0
```

Rules:

```text
MAJOR = breaking API changes
MINOR = backward-compatible features
PATCH = backward-compatible fixes
```

Do not publish random version numbers.

---

# 53. Laravel Compatibility Matrix

Maintain a compatibility table.

Example:

| Package | Laravel | PHP |
|---|---|---|
| 1.x | 12.x | 8.3+ |

Update this whenever support changes.

If Laravel 13 support is later added:

```text
2.x → Laravel 13
```

only if breaking changes justify it. Otherwise extend the compatibility range.

---

# 54. Git Branch Strategy

Recommended:

```text
main
develop
feature/*
fix/*
release/*
```

For a solo project, a simpler approach is acceptable:

```text
main
feature/*
fix/*
```

Never develop directly on an untested production release tag.

---

# 55. Commit Convention

Use clear commits:

```text
feat: add Google Drive upload service
feat: add retention policy
fix: prevent duplicate backup uploads
test: add retention overlap tests
docs: add Google OAuth setup
refactor: extract backup storage contract
chore: update dependencies
```

---

# 56. CI/CD

Create:

```text
.github/workflows/tests.yml
```

CI should run:

```text
composer validate
composer install
static analysis
code style
unit tests
feature tests
coverage
```

Test supported PHP/Laravel combinations.

Do not put Google credentials in normal CI.

---

# 57. Release Pipeline

Before release:

```text
1. Update version.
2. Update CHANGELOG.
3. Run tests.
4. Run static analysis.
5. Run formatter.
6. Verify README.
7. Verify package metadata.
8. Verify composer install from clean project.
9. Create Git tag.
10. Push tag.
11. Publish/update Packagist.
12. Create GitHub release.
13. Verify installation from Packagist.
```

---

# 58. GitHub Repository

Repository should contain:

```text
README.md
LICENSE
CHANGELOG.md
CONTRIBUTING.md
SECURITY.md
composer.json
phpunit.xml
src/
tests/
config/
docs/
.github/
```

Configure:

- Issues.
- Discussions if useful.
- Security policy.
- Dependabot if appropriate.
- GitHub Actions.
- Release tags.

---

# 59. Packagist Publishing

Requirements:

1. Public GitHub repository.
2. Valid `composer.json`.
3. Correct package name.
4. Stable Git tag.
5. License.
6. README.
7. Version metadata.

Connect GitHub repository to Packagist.

After publishing, test:

```bash
composer require vendor/laravel-google-drive-backup
```

in a completely fresh Laravel application.

---

# 60. Pre-Publication Checklist

## Code

```text
[ ] No TODOs in production paths
[ ] No debug statements
[ ] No credentials
[ ] No local paths
[ ] No hardcoded application names
[ ] No hardcoded Google folder IDs
[ ] No hardcoded API secrets
[ ] Strict typing where appropriate
[ ] Static analysis passes
[ ] Formatting passes
```

## Tests

```text
[ ] Unit tests pass
[ ] Integration tests pass
[ ] Console tests pass
[ ] Retention tests pass
[ ] Failure tests pass
[ ] Coverage target met
```

## Security

```text
[ ] Secrets never logged
[ ] OAuth tokens protected
[ ] Temporary files cleaned
[ ] Filename sanitization
[ ] Path traversal protection
[ ] Restore confirmation
[ ] Download validation
```

## Documentation

```text
[ ] Installation
[ ] OAuth setup
[ ] Configuration
[ ] Policies
[ ] Retention
[ ] Commands
[ ] Scheduling
[ ] Restore
[ ] Troubleshooting
[ ] Security
[ ] Upgrade guide
```

---

# 61. First Release Scope

Version `1.0.0` should ideally contain:

```text
✓ Laravel package integration
✓ Google Drive OAuth
✓ Google Drive folder management
✓ Backup upload
✓ Backup listing
✓ Backup download
✓ Backup deletion
✓ Policy configuration
✓ Daily retention
✓ Weekly retention
✓ Monthly retention
✓ Cleanup command
✓ Dry-run cleanup
✓ Backup verification
✓ Artisan commands
✓ Scheduler documentation
✓ Events
✓ Logging
✓ Tests
✓ CI
✓ Documentation
✓ Packagist publication
```

Avoid adding a dashboard to v1.

---

# 62. Suggested Development Milestones

## Milestone 1 — Package Skeleton

```text
Create repository
Create composer.json
Create service provider
Create config
Create Testbench setup
Create PHPUnit/Pest setup
Create CI
```

Acceptance:

```text
composer install works
tests pass
Laravel can load the package
```

---

## Milestone 2 — Google Drive Client

Implement:

```text
OAuth configuration
Google client
authentication
folder lookup
folder creation
```

Acceptance:

```bash
php artisan backup:google-drive:test
```

returns a useful success/failure report.

---

## Milestone 3 — Storage Layer

Implement:

```text
upload
list
get metadata
download
delete
pagination
retry
```

Acceptance:

```text
A test backup can be uploaded and retrieved from Google Drive.
```

---

## Milestone 4 — Backup Integration

Integrate the selected backup engine.

Acceptance:

```text
Laravel database/files backup
        ↓
generated artifact
        ↓
Google Drive
```

---

## Milestone 5 — Policy Engine

Implement:

```text
policy definition
backup type
environment
destination
retention
verification
```

Acceptance:

```text
A named policy can produce a backup according to its configuration.
```

---

## Milestone 6 — Retention

Implement:

```text
daily
weekly
monthly
overlap protection
dry-run
cleanup
```

Acceptance:

```text
Expired backups are removed without deleting protected backups.
```

---

## Milestone 7 — CLI

Implement:

```text
run
test
list
clean
verify
download
restore
```

Acceptance:

```text
All commands have clear help output and meaningful exit codes.
```

---

## Milestone 8 — Security

Review:

```text
credentials
logging
temporary files
restore
downloads
filenames
encryption
```

Acceptance:

```text
Security review passes.
```

---

## Milestone 9 — Documentation

Complete all docs.

Acceptance:

```text
A developer unfamiliar with the package can install and configure it using only the documentation.
```

---

## Milestone 10 — Release

Perform:

```text
fresh Laravel installation
composer require
Google configuration
test command
backup
list
verify
cleanup
```

Then publish.

---

# 63. Definition of Done

A feature is not complete until:

```text
[ ] Implementation complete
[ ] Unit tests added
[ ] Integration tests added where applicable
[ ] Error handling implemented
[ ] Logging reviewed
[ ] Security reviewed
[ ] Documentation updated
[ ] Static analysis passes
[ ] Formatting passes
[ ] CI passes
```

---

# 64. Antigravity Development Rules

When implementing this specification, Antigravity must follow these rules.

## Rule 1

Do not implement everything in one step.

Implement milestone by milestone.

## Rule 2

Before changing architecture, inspect the existing code.

Do not overwrite working implementations unnecessarily.

## Rule 3

Use interfaces for external services.

Google Drive API calls should not be scattered throughout the codebase.

## Rule 4

Do not hardcode credentials.

## Rule 5

Do not silently swallow exceptions.

Every failure must either:

- be handled intentionally, or
- be propagated as a meaningful package exception.

## Rule 6

Write tests before or together with complex logic.

Especially for:

```text
retention
duplicate detection
pagination
retry behavior
backup verification
```

## Rule 7

Do not claim a feature is implemented unless there is a test proving the important behavior.

## Rule 8

Do not introduce unnecessary dependencies.

Every new Composer dependency must have a documented reason.

## Rule 9

Prefer Laravel-native mechanisms where they are appropriate.

## Rule 10

Keep the public API small.

Internal classes can change; public contracts should remain stable.

---

# 65. Recommended Public API

Keep the public API intentionally small.

Potential facade:

```php
use Vendor\GoogleDriveBackup\Facades\GoogleDriveBackup;

GoogleDriveBackup::run();
```

Potential manager:

```php
app('google-drive-backup')->run();
```

Potential policy:

```php
GoogleDriveBackup::policy('production')->run();
```

Do not expose internal Google API objects as part of the public API.

---

# 66. Backward Compatibility

Once `1.0.0` is published:

- Avoid unnecessary breaking changes.
- Mark deprecated APIs before removal.
- Document migrations.
- Maintain an upgrade guide.
- Keep configuration changes backward compatible where possible.

---

# 67. Operational Recommendation

For production, recommend:

```text
Primary backup:
Google Drive

Secondary backup:
AWS S3 / another independent provider
```

The package should not claim Google Drive alone provides complete disaster recovery.

---

# 68. Final Repository Structure

Target structure:

```text
laravel-google-drive-backup/
│
├── .github/
│   └── workflows/
│       ├── tests.yml
│       └── release.yml
│
├── config/
│   └── google-drive-backup.php
│
├── docs/
│   ├── installation.md
│   ├── configuration.md
│   ├── google-drive-setup.md
│   ├── authentication.md
│   ├── policies.md
│   ├── retention.md
│   ├── commands.md
│   ├── scheduling.md
│   ├── restoration.md
│   ├── troubleshooting.md
│   ├── security.md
│   ├── testing.md
│   └── upgrading.md
│
├── examples/
│   └── ...
│
├── resources/
│   └── ...
│
├── src/
│   ├── Contracts/
│   ├── Commands/
│   ├── Console/
│   ├── Domain/
│   ├── Events/
│   ├── Exceptions/
│   ├── GoogleDrive/
│   ├── Policy/
│   ├── Services/
│   └── GoogleDriveBackupServiceProvider.php
│
├── tests/
│   ├── Feature/
│   ├── Unit/
│   ├── Fixtures/
│   ├── TestCase.php
│   └── CreatesApplication.php
│
├── .editorconfig
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
├── README.md
├── SECURITY.md
├── composer.json
└── phpunit.xml
```

---

# 69. Final Acceptance Test

Before publishing `1.0.0`, perform this exact workflow in a clean Laravel application:

```text
1. Create fresh Laravel 12 application.
2. Install package from Packagist or local repository.
3. Publish configuration.
4. Configure Google OAuth.
5. Run Google connection test.
6. Create test backup.
7. Confirm backup appears in Google Drive.
8. List backup.
9. Verify backup.
10. Download backup.
11. Validate downloaded artifact.
12. Run cleanup with --dry-run.
13. Confirm no files are deleted during dry-run.
14. Run real cleanup.
15. Confirm only expired backups are removed.
16. Test failed authentication.
17. Test invalid folder.
18. Test interrupted upload.
19. Test duplicate handling.
20. Run full test suite.
21. Run static analysis.
22. Run code formatter.
23. Build package from clean checkout.
24. Install package again.
25. Create Git tag.
26. Publish GitHub release.
27. Publish/sync with Packagist.
28. Install the published version into another clean Laravel project.
29. Repeat the basic backup workflow.
```

Only after this workflow succeeds should the package be considered production-ready.

---

# 70. Important Implementation Decision

The package should be treated as:

> **A Laravel Google Drive backup policy/integration package, not a complete replacement for Spatie Laravel Backup.**

The package's core value is:

```text
Laravel
   +
Reliable backup engine
   +
Google Drive
   +
Policy management
   +
Retention
   +
Verification
   +
CLI
   +
Operational safety
```

This gives the package a focused scope and makes it much easier to maintain, test, document, and publish.

---

# 71. Development Instruction for Antigravity

Start implementation from Milestone 1.

Do not jump directly to Google Drive upload.

The implementation order must be:

```text
Package skeleton
      ↓
Service provider
      ↓
Configuration
      ↓
Contracts
      ↓
Domain models
      ↓
Google authentication
      ↓
Google Drive client
      ↓
Google Drive storage
      ↓
Backup integration
      ↓
Policy engine
      ↓
Retention engine
      ↓
Verification
      ↓
Artisan commands
      ↓
Scheduler documentation
      ↓
Events/logging
      ↓
Security hardening
      ↓
Tests
      ↓
Documentation
      ↓
CI
      ↓
Release
      ↓
Packagist
```

At each stage:

```text
Implement
  ↓
Test
  ↓
Review
  ↓
Document
  ↓
Commit
  ↓
Proceed
```

Never move to the next milestone while the previous milestone has failing tests or unresolved critical errors.
