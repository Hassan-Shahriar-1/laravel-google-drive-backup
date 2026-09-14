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
| `--db` | Backup database only (default behavior if no flag passed) |
| `--files` | Backup application files only |
| `--full` | Backup both database and application files |
| `--path=` | Specific directory or file path(s) to backup (relative to root). Comma-separated or repeatable |
| `--connection=` | Database connection (`mysql`, `mariadb`, `pgsql`, `sqlite`, `sqlsrv`). Defaults to `DB_CONNECTION` in `.env` |
| `--subfolder=` | Destination subfolder path (supports `{year}`, `{month}`, `{day}`, `{date}`, `{type}`). If omitted, uploads to root folder |
| `--by-type` | Automatically store in a subfolder named after the backup type (e.g. `database/`) |
| `--policy=default` | Named policy to use |
| `--force` | Run even if backups are disabled |
| `--no-verify` | Skip post-upload verification |

### Examples

```bash
# Back up database only (default — uploads to Google Drive root folder)
php artisan backup:google-drive --db

# Back up database into a specific Google Drive subfolder:
php artisan backup:google-drive --db --subfolder=database

# Back up all application files
php artisan backup:google-drive --files

# Back up a specific folder (e.g. uploaded images):
php artisan backup:google-drive --files --path="storage/app/public/images"

# Back up a specific folder directly into an "images" subfolder on Google Drive:
php artisan backup:google-drive --path="storage/app/public/images" --subfolder=images

# Back up into dynamic date-based subfolders on Google Drive (e.g. 2026/09):
php artisan backup:google-drive --db --subfolder="{year}/{month}"

# Auto-organize by type (creates "database/", "files/", or "full/" on Drive):
php artisan backup:google-drive --db --by-type

# Back up multiple folders:
php artisan backup:google-drive --path="storage/app/public,public/uploads"

# Back up both database and full files
php artisan backup:google-drive --full
```

---

## `backup:google-drive:test`

Test Google Drive credentials, API access, and folder permissions.

```bash
php artisan backup:google-drive:test
```

---

## `backup:google-drive:list`

List backups currently stored on Google Drive. By default, it automatically lists backups from both the **root folder and any subfolders** (e.g. `database/`, `images/`), displaying the folder location in a `Folder` column.

```bash
# List all backups across root and subfolders:
php artisan backup:google-drive:list

# Filter and list backups only from a specific subfolder:
php artisan backup:google-drive:list --subfolder=database
php artisan backup:google-drive:list --subfolder=images
```

| Option | Description |
|--------|-------------|
| `--folder=` | Override the target Google Drive root folder ID |
| `--subfolder=` | Filter to only list backups from a specific subfolder (e.g. `database`, `images`) |

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
| `--subfolder=` | Clean backups inside a specific subfolder (e.g. `database`, `others`) |

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

