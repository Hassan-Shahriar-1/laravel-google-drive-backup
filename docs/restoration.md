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

```bash
php artisan backup:google-drive:restore FILE_ID --destination=/var/www/restore
```

The command will:
1. Download the backup from Google Drive.
2. Validate that it is a well-formed ZIP archive.
3. Ask for confirmation before extracting.
4. Extract all files to the destination directory.

### 4. Restore Your Database Manually

Extract the SQL dump from the archive and import it:

```bash
mysql -u root -p your_database < /var/www/restore/db-dump.sql
```

### 5. Restore Application Files Manually

Copy extracted files to your application directory as needed.

> **Warning:** Never restore directly over a live production environment without first taking a fresh backup and verifying the restore in a staging environment.

## Security Notes

- Backups are validated before extraction.
- Confirmation is always required before extraction.
- Temporary files are cleaned up automatically.
