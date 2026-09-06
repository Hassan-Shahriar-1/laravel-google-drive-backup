# Artisan Commands

## `backup:google-drive`

Create and upload a backup to Google Drive.

```bash
php artisan backup:google-drive [options]
```

| Option | Description |
|--------|-------------|
| `--policy=default` | Named policy to use |
| `--type=full` | Backup type: `full`, `database`, `files` |
| `--force` | Run even if backups are disabled |
| `--no-verify` | Skip post-upload verification |

---

## `backup:google-drive:test`

Test Google Drive credentials, API access, and folder permissions.

```bash
php artisan backup:google-drive:test
```

---

## `backup:google-drive:list`

List all backups currently stored on Google Drive.

```bash
php artisan backup:google-drive:list [--folder=FOLDER_ID]
```

---

## `backup:google-drive:clean`

Remove expired backups according to the configured retention policy.

```bash
php artisan backup:google-drive:clean [options]
```

| Option | Description |
|--------|-------------|
| `--dry-run` | Preview deletions without executing |
| `--policy=default` | Named policy to use for retention rules |

> Always run with `--dry-run` first before executing a real cleanup.

---

## `backup:google-drive:verify`

Verify the integrity of one or all remote backups.

```bash
php artisan backup:google-drive:verify [options]
```

| Option | Description |
|--------|-------------|
| `--id=FILE_ID` | Verify a specific backup by Drive file ID |
| `--all` | Verify all backups |

---

## `backup:google-drive:download {id} {destination}`

Download a backup from Google Drive to a local file.

```bash
php artisan backup:google-drive:download FILE_ID /path/to/save.zip
```

---

## `backup:google-drive:restore {id}`

Download and extract a backup for restoration.

```bash
php artisan backup:google-drive:restore FILE_ID [--destination=/path/to/extract]
```

> This is a destructive operation. Always confirm before proceeding.

