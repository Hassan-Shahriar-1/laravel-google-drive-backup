# Restoration

## Restoring Your Database

The `backup:google-drive:restore-db` command is dedicated to downloading and automatically restoring your database directly from Google Drive. (Alias: `backup:google-drive:restore`)

### 1. List Available Backups

```bash
# List all backups across root and all subfolders:
php artisan backup:google-drive:list

# Or filter to a specific subfolder (e.g. database):
php artisan backup:google-drive:list --subfolder=database
```

Note the **ID** of the backup you want to restore.

---

### 2. Restore the Database

Run the restore command with the backup ID:

```bash
php artisan backup:google-drive:restore-db FILE_ID
```

The command will:
1. Download the backup from Google Drive to a secure temporary location.
2. Validate that the archive contains a database dump.
3. Prompt for confirmation before overwriting your database.
4. Restore tables and data into your database using the appropriate driver CLI (`mysql`, `mariadb`, `pgsql`, `sqlite`, or `sqlsrv`).
5. Safely clean up all temporary files immediately.

#### Restore into a Specific Connection

If you want to restore into a specific database connection defined in `config/database.php`:

```bash
php artisan backup:google-drive:restore-db FILE_ID --connection=pgsql
```

#### Unattended Restore (CI/CD or Automated Scripts)

To skip the interactive confirmation prompt, use `--force`:

```bash
php artisan backup:google-drive:restore-db FILE_ID --force
```

---

### 3. Downloading Application Files

If you need to retrieve raw application files or custom path backups, use the dedicated download command:

```bash
php artisan backup:google-drive:download FILE_ID /path/to/local/backup.zip
```

You can then extract and inspect the archive locally without risking changes to your live codebase.

---

## Safety & Security Notes

> **Warning:** Database restoration is a destructive operation that overwrites existing tables and records. Always ensure you have a recent snapshot or test on staging first.

- The restore command validates archive integrity before running any restore queries.
- A confirmation prompt is displayed by default to prevent accidental overwrites.
- Temporary files are always removed upon completion or failure.
