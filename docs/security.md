# Security

## Credential Handling

- OAuth credentials (`client_secret`, `refresh_token`, `access_token`) are **never written to logs**.
- All package exceptions automatically redact credentials from error messages.
- Store credentials only in `.env` files that are excluded from version control.

## Temporary Files

- Backup archives are written to a temporary location and always deleted after upload, even when an exception occurs (`try/finally`).

## Filename Safety

- Application names are sanitized before use in filenames (only `a-z`, `0-9`, `-`, `_` are permitted).
- This prevents path traversal attacks.

## Encryption at Rest & in Transit

- **At Rest:** Google Drive automatically encrypts all uploaded data at rest using Google's AES-256 standard encryption on their secure cloud infrastructure.
- **In Transit:** All API communication with Google Drive is encrypted using TLS/HTTPS. No plaintext transfers occur.

## Restore Safety

- The restore command always validates the downloaded archive before extraction.
- Explicit user confirmation is required before any files are extracted.

## Network Security

- All Google Drive API communication uses HTTPS.
- No custom certificate validation is bypassed.

## Reporting Vulnerabilities

See [SECURITY.md](../SECURITY.md).

