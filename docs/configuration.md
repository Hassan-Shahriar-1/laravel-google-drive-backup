# Configuration Reference

After publishing, edit `config/google-drive-backup.php`:

## `enabled`
Enable or disable all backup operations. Default: `true`.

## `application_name`
Used in backup filenames and Google Drive folder paths. Default: `env('APP_NAME')`.

## `environment`
Used to namespace backups (e.g. `production`, `staging`). Default: `env('APP_ENV')`.

## `google`

| Key | Description |
|-----|-------------|
| `client_id` | Google OAuth 2.0 Client ID |
| `client_secret` | Google OAuth 2.0 Client Secret |
| `refresh_token` | Long-lived refresh token |
| `folder_id` | Target Drive folder ID (auto-created if blank) |
| `folder_name` | Folder name to create if `folder_id` is blank. Default: `Laravel Backups` |
| `shared_drive_id` | Optional: Google Shared Drive ID |

## `filename.format`

Token-based filename pattern. Available tokens: `{app}`, `{env}`, `{type}`, `{date}`, `{time}`, `{uuid}`.

Default: `{app}-{env}-{type}-{date}-{time}-{uuid}.zip`

## `backup`

| Key | Default | Description |
|-----|---------|-------------|
| `database` | `true` | Include database dumps |
| `files` | `true` | Include application files |

## `compression`

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Compress backups as ZIP |
| `format` | `zip` | Archive format |

## `encryption`

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `false` | Enable AES-256-CBC encryption |
| `key` | — | Encryption key (never store in Git) |

## `verification`

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Verify after upload |
| `mode` | `metadata` | `none`, `metadata`, `checksum`, or `full-download` |
| `checksum` | `sha256` | Checksum algorithm |

## `retention`

| Key | Default | Description |
|-----|---------|-------------|
| `daily` | `30` | Number of daily backups to keep |
| `weekly` | `8` | Number of weekly backups to keep |
| `monthly` | `12` | Number of monthly backups to keep |

## `logging`

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Enable logging |
| `channel` | `stack` | Laravel log channel |

