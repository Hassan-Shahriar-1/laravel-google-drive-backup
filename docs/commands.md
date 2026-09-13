# Artisan Commands

## `backup:google-drive:refresh-token`

Interactively generate a Google OAuth 2.0 refresh token and optionally save it to `.env`. (Alias: `backup:google-drive:token`)

```bash
php artisan backup:google-drive:refresh-token
```

| Option | Description |
|--------|-------------|
| `--client-id=` | Google OAuth Client ID |
| `--client-secret=` | Google OAuth Client Secret |
| `--redirect=` | Redirect URI (default: `urn:ietf:wg:oauth:2.0:oob`) |

---

## `backup:google-drive`

Create and upload a backup to Google Drive.

```bash
php artisan backup:google-drive [options]
```

| Option | Description |
|--------|-------------|
| `--type=` | Backup type: `database`, `files`, `full` (defaults to database) |
| `--db` | Shortcut flag for `--type=database` |
| `--connection=` | Database connection (`mysql`, `mariadb`, `pgsql`, `sqlite`, `sqlsrv`). Defaults to `DB_CONNECTION` in `.env` |
| `--policy=default` | Named policy to use |
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

Download and restore a backup (files and/or database).

```bash
php artisan backup:google-drive:restore FILE_ID [options]
```

| Option | Description |
|--------|-------------|
| `--db-restore` | Automatically restore the SQL dump into the database |
| `--connection=` | Database connection to restore into (defaults to `DB_CONNECTION`) |
| `--destination=` | Directory to extract application files to (default: `storage/app/restore`) |
| `--force` | Skip confirmation prompts |

> Database restore is a destructive operation. Always confirm before proceeding in production environments.

