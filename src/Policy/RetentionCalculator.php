<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Policy;

use DateTimeImmutable;
use DateTimeZone;
use HassanShahriar\GoogleDriveBackup\Contracts\RetentionManager;
use HassanShahriar\GoogleDriveBackup\Domain\BackupArtifact;
use HassanShahriar\GoogleDriveBackup\Domain\RetentionResult;

class RetentionCalculator implements RetentionManager
{
    /**
     * Determine which backups to keep and which to delete.
     *
     * Strategy:
     * - Sort backups newest→oldest.
     * - Keep the N most-recent backups for daily.
     * - Keep 1 backup per week (most recent) for up to W weeks, for weekly.
     * - Keep 1 backup per month (most recent) for up to M months, for monthly.
     * - Union the three protected sets.
     * - Everything outside the union is eligible for deletion.
     *
     * @param array<BackupArtifact> $backups
     * @param array{daily?: int, weekly?: int, monthly?: int} $rules
     */
    public function determineBackupsToDelete(array $backups, array $rules): RetentionResult
    {
        if (empty($backups)) {
            return new RetentionResult(kept: [], toDelete: [], summary: [
                'total' => 0, 'kept' => 0, 'to_delete' => 0,
            ]);
        }

        $daily = (int) ($rules['daily'] ?? 30);
        $weekly = (int) ($rules['weekly'] ?? 8);
        $monthly = (int) ($rules['monthly'] ?? 12);

        // Sort newest → oldest; backups without a date go last
        usort($backups, function (BackupArtifact $a, BackupArtifact $b): int {
            if ($a->createdAt === null && $b->createdAt === null) return 0;
            if ($a->createdAt === null) return 1;
            if ($b->createdAt === null) return -1;
            return $b->createdAt <=> $a->createdAt;
        });

        /** @var array<string, BackupArtifact> keyed by id */
        $protected = [];

        // ── Daily: keep the N most recent ────────────────────────────────
        foreach (array_slice($backups, 0, $daily) as $b) {
            $protected[$b->id] = $b;
        }

        // ── Weekly: keep the most-recent backup per ISO week ─────────────
        $weeksFound = [];
        foreach ($backups as $b) {
            if (count($weeksFound) >= $weekly) break;
            if ($b->createdAt === null) continue;
            $key = $b->createdAt->format('oW'); // ISO year + week
            if (!isset($weeksFound[$key])) {
                $weeksFound[$key] = true;
                $protected[$b->id] = $b;
            }
        }

        // ── Monthly: keep the most-recent backup per calendar month ───────
        $monthsFound = [];
        foreach ($backups as $b) {
            if (count($monthsFound) >= $monthly) break;
            if ($b->createdAt === null) continue;
            $key = $b->createdAt->format('Y-m');
            if (!isset($monthsFound[$key])) {
                $monthsFound[$key] = true;
                $protected[$b->id] = $b;
            }
        }

        // ── Partition ─────────────────────────────────────────────────────
        $kept = [];
        $toDelete = [];

        foreach ($backups as $b) {
            if (isset($protected[$b->id])) {
                $kept[] = $b;
            } else {
                $toDelete[] = $b;
            }
        }

        return new RetentionResult(
            kept: $kept,
            toDelete: $toDelete,
            summary: [
                'total' => count($backups),
                'kept' => count($kept),
                'to_delete' => count($toDelete),
                'rules' => ['daily' => $daily, 'weekly' => $weekly, 'monthly' => $monthly],
            ]
        );
    }
}
