<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Services;

use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class DatabaseRestorer
{
    /**
     * Restore a database from a ZIP archive that was created by DatabaseDumper.
     * The ZIP must contain a file named `database-{connection}.sql`.
     *
     * @throws GoogleDriveBackupException
     */
    public function restoreFromZip(string $zipPath, ?string $connection = null): void
    {
        $connection = $connection ?? Config::get('database.default');
        $dbConfig   = (array) Config::get("database.connections.{$connection}", []);
        $driver     = (string) ($dbConfig['driver'] ?? 'mysql');

        if (!file_exists($zipPath)) {
            throw new GoogleDriveBackupException("Backup ZIP not found at [{$zipPath}].");
        }

        // ── Extract the SQL file from the ZIP ─────────────────────────────────
        $tmpDir  = sys_get_temp_dir() . '/gdrive-restore-' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new GoogleDriveBackupException("Cannot open ZIP archive at [{$zipPath}].");
            }

            $zip->extractTo($tmpDir);
            $zip->close();

            // Find the SQL file inside the extracted folder
            $sqlFile = $this->findSqlFile($tmpDir, $connection);

            if ($sqlFile === null) {
                throw new GoogleDriveBackupException(
                    "No SQL dump file found in the backup archive. " .
                        "Expected file matching: database-{$connection}.sql"
                );
            }

            // ── Restore based on driver ───────────────────────────────────────
            match ($driver) {
                'mysql', 'mariadb' => $this->restoreMysql($dbConfig, $sqlFile),
                'pgsql'            => $this->restorePgsql($dbConfig, $sqlFile),
                'sqlite'           => $this->restoreSqlite($dbConfig, $sqlFile),
                default            => throw new GoogleDriveBackupException("Unsupported driver [{$driver}].")
            };
        } finally {
            $this->deleteDirectory($tmpDir);
        }
    }

    // ─── MySQL / MariaDB ──────────────────────────────────────────────────────

    private function restoreMysql(array $config, string $sqlFile): void
    {
        $host     = escapeshellarg((string) ($config['host']     ?? '127.0.0.1'));
        $port     = (int)   ($config['port']     ?? 3306);
        $database = escapeshellarg((string) ($config['database'] ?? ''));
        $username = escapeshellarg((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $file     = escapeshellarg($sqlFile);

        $env     = !empty($password) ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';
        $command = "{$env}mysql --host={$host} --port={$port} --user={$username} {$database} < {$file} 2>&1";

        $this->runShellCommand($command, 'mysql');
    }

    // ─── PostgreSQL ───────────────────────────────────────────────────────────

    private function restorePgsql(array $config, string $sqlFile): void
    {
        $host     = escapeshellarg((string) ($config['host']     ?? '127.0.0.1'));
        $port     = (int)   ($config['port']     ?? 5432);
        $database = escapeshellarg((string) ($config['database'] ?? ''));
        $username = escapeshellarg((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $file     = escapeshellarg($sqlFile);

        $env     = !empty($password) ? 'PGPASSWORD=' . escapeshellarg($password) . ' ' : '';
        $command = "{$env}psql --host={$host} --port={$port} --username={$username} {$database} < {$file} 2>&1";

        $this->runShellCommand($command, 'psql');
    }

    // ─── SQLite ───────────────────────────────────────────────────────────────

    private function restoreSqlite(array $config, string $sqlFile): void
    {
        $dbPath = (string) ($config['database'] ?? '');

        if (empty($dbPath)) {
            throw new GoogleDriveBackupException('SQLite database path is not configured.');
        }

        // For SQLite: pipe the SQL dump back into the database file
        $db = escapeshellarg($dbPath);
        $f  = escapeshellarg($sqlFile);

        if ($this->commandExists('sqlite3')) {
            $this->runShellCommand("sqlite3 {$db} < {$f} 2>&1", 'sqlite3');
        } else {
            // Fallback: copy the file directly
            copy($sqlFile, $dbPath);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function findSqlFile(string $directory, string $connection): ?string
    {
        // Look for database-{connection}.sql first, then any .sql file
        $preferred = $directory . "/database-{$connection}.sql";
        if (file_exists($preferred)) {
            return $preferred;
        }

        foreach (glob($directory . '/*.sql') ?: [] as $file) {
            return $file;
        }

        return null;
    }

    private function runShellCommand(string $command, string $tool): void
    {
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new GoogleDriveBackupException(
                "Database restore failed using [{$tool}]. Exit code: {$exitCode}. " .
                    "Output: " . implode(' ', array_slice($output, 0, 5))
            );
        }
    }

    private function commandExists(string $command): bool
    {
        return !empty(trim((string) shell_exec("which {$command} 2>/dev/null")));
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        foreach ((array) scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
