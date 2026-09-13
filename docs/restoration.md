# Restoration

## Step-by-Step Restore

### 1. List Available Backups

```bash
php artisan backup:google-drive:list
```

Note the **ID** of the backup you want to restore.

### 2. Download the Backup

```bash
php artisan backup:google-drive:download FILE_ID /tmp/restore.zip
```

### 3. Restore Using the Command

#### Option A: Automatic Database Restore (Recommended)

```bash
php artisan backup:google-drive:restore FILE_ID --db-restore
```

The command will:
1. Download the backup from Google Drive.
2. Validate that it is a valid ZIP archive and auto-detect the backup type.
3. Automatically restore the SQL dump into your database using the appropriate driver CLI (`mysql`, `mariadb`, `pgsql`, `sqlite`, or `sqlsrv`).
4. If the archive also includes application files, extracts them to `--destination`.

To restore into a specific database connection:
```bash
php artisan backup:google-drive:restore FILE_ID --db-restore --connection=pgsql
```

To run unattended without prompts (CI/CD / scripts):
```bash
php artisan backup:google-drive:restore FILE_ID --db-restore --force
```

#### Option B: Extract Files Only (Without Auto-Restoring Database)

```bash
php artisan backup:google-drive:restore FILE_ID --destination=/var/www/restore
```

You can then review extracted files and import the `.sql` dump manually if preferred.

> **Warning:** Database restore is a destructive operation that replaces table contents. Always verify backups before restoring in a production environment.

## Security Notes

- Backups are validated before extraction.
- Confirmation is always required before extraction.
- Temporary files are cleaned up automatically.

