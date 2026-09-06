# Scheduling

The package does **not** register any hidden schedules. Add scheduling explicitly in your application.

## Laravel 11+ (`routes/console.php`)

```php
use Illuminate\Support\Facades\Schedule;

// 1. Create and upload a backup every day at 02:00 UTC
Schedule::command('backup:google-drive')->dailyAt('02:00');

// 2. Apply retention policy every day at 03:00 UTC
Schedule::command('backup:google-drive:clean')->dailyAt('03:00');

// 3. Verify the most-recent backup every day at 04:00 UTC
Schedule::command('backup:google-drive:verify --all')->dailyAt('04:00');
```

## Laravel 10 (`app/Console/Kernel.php`)

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('backup:google-drive')->dailyAt('02:00');
    $schedule->command('backup:google-drive:clean')->dailyAt('03:00');
    $schedule->command('backup:google-drive:verify --all')->dailyAt('04:00');
}
```

## Running the Scheduler

Ensure the Laravel scheduler cron entry is registered on your server:

```cron
* * * * * cd /path/to/your-app && php artisan schedule:run >> /dev/null 2>&1
```
