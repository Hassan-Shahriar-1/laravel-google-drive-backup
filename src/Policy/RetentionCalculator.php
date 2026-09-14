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
     * - Group backups by type (database, files, full) so frequent database backups
     *   never push out or delete file backups.
     * - For each group:
     *   1. Recent: Keep ALL intra-day backups from the most recent day (e.g. every 4 hours).
     *   2. Daily: Keep 1 backup per calendar day (Y-m-d) for up to D distinct days.
     *   3. Weekly: Keep 1 backup per ISO week (oW) for up to W distinct weeks.
     *   4. Monthly: Keep 1 backup per calendar month (Y-m) for up to M distinct months.
     * - Union the protected sets and delete the rest.
     *
     * @param array<BackupArtifact> $backups
     * @param array{daily?: int, weekly?: int, monthly?: int} $rules
     */
    public function determineBackupsToDelete(array $backups, array $rules): RetentionResult
    {
        if (empty($backups)) {
            return new RetentionResult(kept: [], toDelete: [], summary: [
                'total' => 0,
                'kept' => 0,
                'to_delete' => 0,
            ]);
        }

        // Group backups by type so database backups and file backups don't compete
        $byType = [];
        foreach ($backups as $b) {
            $typeKey = $b->type ?? 'default';
            $byType[$typeKey][] = $b;
        }

        if (count($byType) <= 1) {
            return $this->evaluateSingleGroup($backups, $rules);
        }

        $allKept = [];
        $allToDelete = [];

        foreach ($byType as $typeBackups) {
            $result = $this->evaluateSingleGroup($typeBackups, $rules);
            $allKept = array_merge($allKept, $result->kept);
            $allToDelete = array_merge($allToDelete, $result->toDelete);
        }

        return new RetentionResult(
            kept: $allKept,
            toDelete: $allToDelete,
            summary: [
                'total' => count($backups),
                'kept' => count($allKept),
                'to_delete' => count($allToDelete),
                'rules' => $rules,
            ]
        );
    }

    /**
     * @param array<BackupArtifact> $backups
     * @param array{daily?: int, weekly?: int, monthly?: int} $rules
     */
    protected function evaluateSingleGroup(array $backups, array $rules): RetentionResult
    {
        $daily   = (int) ($rules['daily'] ?? 30);
        $weekly  = (int) ($rules['weekly'] ?? 8);
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

        // ── 1. Recent: Keep ALL intra-day backups from the latest day ─────────
        // (If daily backups are enabled and backups exist, keep all 4-hour snapshots of the latest day)
        if ($daily > 0 && !empty($backups) && $backups[0]->createdAt !== null) {
            $latestDay = $backups[0]->createdAt->format('Y-m-d');
            foreach ($backups as $b) {
                if ($b->createdAt !== null && $b->createdAt->format('Y-m-d') === $latestDay) {
                    $protected[$b->id] = $b;
                }
            }
        }

        // ── 2. Daily: Keep 1 backup per calendar day (Y-m-d) for up to $daily days
        $daysFound = [];
        foreach ($backups as $b) {
            if (count($daysFound) >= $daily) break;
            if ($b->createdAt === null) continue;
            $key = $b->createdAt->format('Y-m-d');
            if (!isset($daysFound[$key])) {
                $daysFound[$key] = true;
                $protected[$b->id] = $b;
            }
        }

        // ── 3. Weekly: Keep 1 backup per ISO week (oW) for up to $weekly weeks ──
        $weeksFound = [];
        foreach ($backups as $b) {
            if (count($weeksFound) >= $weekly) break;
            if ($b->createdAt === null) continue;
            $key = $b->createdAt->format('oW');
            if (!isset($weeksFound[$key])) {
                $weeksFound[$key] = true;
                $protected[$b->id] = $b;
            }
        }

        // ── 4. Monthly: Keep 1 backup per calendar month (Y-m) for up to $monthly months
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

        // ── Partition into kept and toDelete ─────────────────────────────────
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
