# Troubleshooting

## Authentication Errors

### `Missing Google Drive OAuth credentials`
**Cause:** One or more of `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REFRESH_TOKEN` is missing from `.env`.
**Fix:** Add all three values. See [google-drive-setup.md](google-drive-setup.md).

### `invalid_grant`
**Cause:** The refresh token has expired or been revoked.
**Fix:** Re-run the token generation script and update `GOOGLE_DRIVE_REFRESH_TOKEN`.

### `403 Forbidden`
**Cause:** Google Drive API is not enabled, or your account lacks permission.
**Fix:** Enable the Google Drive API in Google Cloud Console.

---

## Folder Errors

### `Google Drive folder [ID] not found`
**Cause:** `GOOGLE_DRIVE_BACKUP_FOLDER_ID` points to a deleted or inaccessible folder.
**Fix:** Clear `GOOGLE_DRIVE_BACKUP_FOLDER_ID` to let the package auto-create the folder.

### `Google Drive folder [ID] is in trash`
**Cause:** The target folder was trashed.
**Fix:** Restore it from Google Drive trash or clear the config value.

---

## Upload Errors

### `Local backup file not found`
**Cause:** The backup engine did not produce an archive at the expected path.
**Fix:** Check your backup engine configuration and disk permissions.

### Upload never completes / times out
**Cause:** Large file + poor network connection.
**Fix:** The package uses resumable chunked uploads (5 MB chunks). Retries on transient errors are supported.

---

## Verification Errors

### `Size mismatch: local=X bytes, remote=Y bytes`
**Cause:** The upload was interrupted or the remote file is corrupt.
**Fix:** Delete the corrupt remote file and re-run the backup.

---

## Retention / Cleanup Errors

### Unexpected backups being deleted
**Fix:** Run with `--dry-run` first and review the output before executing real deletions.

---

## General Debugging

Enable verbose Laravel logging:
```env
LOG_LEVEL=debug
GOOGLE_DRIVE_BACKUP_LOG_CHANNEL=single
```

Run the test command:
```bash
php artisan backup:google-drive:test
```

