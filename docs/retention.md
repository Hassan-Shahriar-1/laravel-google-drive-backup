# Retention Policy

The package uses a deterministic three-tier retention algorithm to decide which backups to keep and which to delete.

## How It Works

```
All backups on Google Drive
         │
         ▼
   Group by backup type (database, files, full)
   (Frequent database backups never delete file backups!)
         │
         ▼
   Sort newest → oldest
         │
         ├─ Recent:  keep ALL intra-day backups from the latest day (e.g. every 4 hours)
         ├─ Daily:   keep 1 backup per calendar day (Y-m-d), up to D distinct days
         ├─ Weekly:  keep 1 backup per ISO week (oW), up to W distinct weeks
         └─ Monthly: keep 1 backup per calendar month (Y-m), up to M distinct months
         │
         ▼
   Union of all protected sets = KEEP
   Everything outside = DELETE
```

## Intra-Day Schedules (e.g., Backing up every 4 hours)

If you back up your database every 4 hours (6 backups a day):
1. **Latest Day Protection**: All 6 backups from today are kept so you have granular checkpoints throughout the day (04:00, 08:00, 12:00, 16:00, 20:00).
2. **Distinct Calendar Days**: For older days, it keeps **1 snapshot per calendar day** for up to 30 days, rather than burning through the 30 count in only 5 days.

## Independent Evaluation by Backup Type

If you run database backups every 4 hours and application file backups once a week:
* Database backups and file backups are evaluated in **separate groups**.
* Taking 50 database backups will **never push out or delete your file backups**.
* If you organize backups into separate subfolders (e.g. `--subfolder=database`), you can also clean each subfolder independently:
  ```bash
  php artisan backup:google-drive:clean --subfolder=database
  ```

## Overlap Protection

A backup that satisfies **more than one** retention category is protected by **all** applicable categories.

**Example:**
- The Sunday backup might be both the most-recent daily **and** the most-recent weekly backup.
- Even after it ages out of the daily window, it is still protected by weekly retention.
- It is only eligible for deletion when it expires from **every** category it belongs to.

## Configuration

```php
// config/google-drive-backup.php
'retention' => [
    'daily'   => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_DAILY', 30),   // keep 30 distinct days of backups
    'weekly'  => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_WEEKLY', 8),   // keep 8 distinct weeks of backups
    'monthly' => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_MONTHLY', 12), // keep 12 distinct months of backups
],
```

Or configure directly in `.env`:
```env
GOOGLE_DRIVE_BACKUP_RETENTION_DAILY=30
GOOGLE_DRIVE_BACKUP_RETENTION_WEEKLY=8
GOOGLE_DRIVE_BACKUP_RETENTION_MONTHLY=12
```

## Running Cleanup

Just like Spatie's `backup:clean`, you **do not pass dates, types, or intervals** to the cleanup command. The cleanup engine automatically inspects the creation timestamps of your files on Google Drive and calculates which backups to keep based on your configured policy:

```bash
# Preview what would be deleted without deleting anything:
php artisan backup:google-drive:clean --dry-run

# Run cleanup on all backups in Google Drive:
php artisan backup:google-drive:clean

# If you use subfolders (e.g. database/), clean only that subfolder:
php artisan backup:google-drive:clean --subfolder=database --dry-run
```

## Recommended Scheduler Setup

Add the following to `routes/console.php` (Laravel 11, 12, 13+):

```php
use Illuminate\Support\Facades\Schedule;

// 1. Back up database frequently (e.g. every 4 hours)
Schedule::command('backup:google-drive --db')->everyFourHours();

// 2. Back up application files weekly
Schedule::command('backup:google-drive --files')->weeklyOn(0, '02:00');

// 3. Run retention cleanup automatically once a day at night
Schedule::command('backup:google-drive:clean')->dailyAt('03:00');
```

When `backup:google-drive:clean` runs every night at 03:00:
1. It looks at all database backups and keeps all intra-day backups for the newest day, plus 1 snapshot per day for the last 30 days, 1 per week for 8 weeks, and 1 per month for 12 months.
2. It looks at file backups separately and preserves your weekly file backups without letting frequent database backups push them out.
3. Only expired backups that fail all retention rules are safely removed from Google Drive.

