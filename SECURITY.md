# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✓ Yes     |

## Reporting a Vulnerability

Please **do not** open a public GitHub issue for security vulnerabilities.

Report vulnerabilities privately by emailing the maintainer. You will receive a response within 72 hours.

## Security Design

- OAuth credentials are never logged or included in exception messages.
- Temporary backup files are always cleaned up after use.
- Filenames are sanitized to prevent path traversal.
- Encryption keys must be stored outside Google Drive.
- All API communication uses HTTPS.

