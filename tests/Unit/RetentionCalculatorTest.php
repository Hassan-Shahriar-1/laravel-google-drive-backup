<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use DateTimeImmutable;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Policy\RetentionCalculator;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class RetentionCalculatorTest extends TestCase
{
    private RetentionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new RetentionCalculator();
    }

    private function artifact(string $id, string $dateStr): BackupArtifact
    {
        return new BackupArtifact(
            id: $id,
            filename: "{$id}.zip",
            createdAt: new DateTimeImmutable($dateStr, new \DateTimeZone('UTC')),
        );
    }

    public function test_keeps_daily_backups(): void
    {
        $backups = [
            $this->artifact('d1', '2026-09-06 02:00:00'),
            $this->artifact('d2', '2026-09-05 02:00:00'),
            $this->artifact('d3', '2026-09-04 02:00:00'),
        ];

        $result = $this->calculator->determineBackupsToDelete($backups, ['daily' => 3, 'weekly' => 0, 'monthly' => 0]);

        $this->assertCount(3, $result->kept);
        $this->assertCount(0, $result->toDelete);
    }

    public function test_deletes_old_dailies_beyond_limit(): void
    {
        $backups = [];
        for ($i = 1; $i <= 35; $i++) {
            $date     = (new DateTimeImmutable('2026-09-06'))->modify("-{$i} days")->format('Y-m-d');
            $backups[] = $this->artifact("d{$i}", $date . ' 02:00:00');
        }

        $result = $this->calculator->determineBackupsToDelete($backups, ['daily' => 30, 'weekly' => 0, 'monthly' => 0]);

        $this->assertCount(30, $result->kept);
        $this->assertCount(5, $result->toDelete);
    }

    public function test_weekly_retention_keeps_one_per_week(): void
    {
        // 14 daily backups across two weeks
        $backups = [];
        for ($i = 1; $i <= 14; $i++) {
            $date     = (new DateTimeImmutable('2026-09-06'))->modify("-{$i} days")->format('Y-m-d');
            $backups[] = $this->artifact("d{$i}", $date . ' 02:00:00');
        }

        $result = $this->calculator->determineBackupsToDelete($backups, ['daily' => 0, 'weekly' => 2, 'monthly' => 0]);

        $keptIds = array_map(fn($b) => $b->id, $result->kept);
        $this->assertCount(2, $result->kept, "Should keep exactly 2 weekly backups (one per week)");
    }

    public function test_monthly_retention_keeps_one_per_month(): void
    {
        // 3 months of backups (one backup per month)
        $backups = [
            $this->artifact('m1', '2026-09-01 02:00:00'),
            $this->artifact('m2', '2026-08-01 02:00:00'),
            $this->artifact('m3', '2026-07-01 02:00:00'),
            $this->artifact('m4', '2026-06-01 02:00:00'), // should be deleted
        ];

        $result = $this->calculator->determineBackupsToDelete($backups, ['daily' => 0, 'weekly' => 0, 'monthly' => 3]);

        $this->assertCount(3, $result->kept);
        $this->assertCount(1, $result->toDelete);
        $this->assertSame('m4', $result->toDelete[0]->id);
    }

    public function test_overlapping_backup_not_deleted_when_still_weekly(): void
    {
        // Backup d1 is both the most-recent daily AND the most-recent weekly
        // With daily=1, it's in the daily set; even with daily=0 it stays via weekly
        $backups = [
            $this->artifact('d1', '2026-09-06 02:00:00'),
            $this->artifact('d2', '2026-08-30 02:00:00'),
        ];

        // Keep 0 daily, but keep 1 weekly → d1 must be protected via weekly
        $result = $this->calculator->determineBackupsToDelete($backups, ['daily' => 0, 'weekly' => 1, 'monthly' => 0]);

        $keptIds = array_map(fn($b) => $b->id, $result->kept);
        $this->assertContains('d1', $keptIds, 'Most recent backup must be kept via weekly even when daily=0');
    }

    public function test_dry_run_returns_correct_partition_without_side_effects(): void
    {
        $backups = [
            $this->artifact('a', '2026-09-06 00:00:00'),
            $this->artifact('b', '2026-08-01 00:00:00'),
        ];

        $result = $this->calculator->determineBackupsToDelete($backups, ['daily' => 1, 'weekly' => 0, 'monthly' => 0]);

        // RetentionCalculator itself never deletes; deletion is caller's responsibility
        $this->assertSame(1, $result->countKept());
        $this->assertSame(1, $result->countToDelete());
        $this->assertSame('b', $result->toDelete[0]->id);
    }

    public function test_empty_backup_list_returns_empty_result(): void
    {
        $result = $this->calculator->determineBackupsToDelete([], ['daily' => 30, 'weekly' => 8, 'monthly' => 12]);

        $this->assertCount(0, $result->kept);
        $this->assertCount(0, $result->toDelete);
        $this->assertSame(0, $result->summary['total']);
    }
}
