# Retention Policy

The package uses a deterministic three-tier retention algorithm to decide which backups to keep and which to delete.

## How It Works

```
All backups on Google Drive
         │
         ▼
   Sort newest → oldest
         │
         ├─ Daily:   keep the N most-recent backups
         ├─ Weekly:  keep the most-recent backup per ISO week, up to W weeks
         └─ Monthly: keep the most-recent backup per calendar month, up to M months
         │
         ▼
   Union of all three protected sets = KEEP
   Everything outside = DELETE
```

## Overlap Protection

A backup that satisfies **more than one** retention category is protected by **all** applicable categories.

**Example:**
- The Sunday backup might be both the most-recent daily **and** the most-recent weekly backup.
- Even after it ages out of the daily window, it is still protected by weekly retention.
- It is only eligible for deletion when it expires from **every** category it belongs to.

## Configuration

```php
'retention' => [
    'daily'   => 30,   // keep 30 individual daily backups
    'weekly'  => 8,    // keep 8 weeks of backups
    'monthly' => 12,   // keep 12 months of backups
],
```

## Running Cleanup

Preview (safe — no files deleted):
```bash
php artisan backup:google-drive:clean --dry-run
```

Execute:
```bash
php artisan backup:google-drive:clean
```

Schedule automatically:
```php
Schedule::command('backup:google-drive:clean')->dailyAt('03:00');
```
