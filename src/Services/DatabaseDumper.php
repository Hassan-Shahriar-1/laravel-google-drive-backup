<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Services;

use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
use Illuminate\Support\Facades\Config;
use ZipArchive;

class DatabaseDumper
{
    /**
     * Dump the default (or specified) database connection to a ZIP archive.
     * The ZIP contains a plain SQL dump file.
     *
     * @throws GoogleDriveBackupException
     */
    public function dumpToZip(string $zipPath, ?string $connection = null): void
    {
        $connection = $connection ?? Config::get('database.default');
        $dbConfig   = (array) Config::get("database.connections.{$connection}", []);
        $driver     = (string) ($dbConfig['driver'] ?? 'mysql');

        $sqlDumpPath = $zipPath . '.sql';

        try {
            match ($driver) {
                'mysql', 'mariadb' => $this->dumpMysql($dbConfig, $sqlDumpPath),
                'pgsql'            => $this->dumpPgsql($dbConfig, $sqlDumpPath),
                'sqlite'           => $this->dumpSqlite($dbConfig, $sqlDumpPath),
                default            => throw new GoogleDriveBackupException("Unsupported database driver [{$driver}]. Supported: mysql, pgsql, sqlite.")
            };

            // Wrap the SQL dump in a ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new GoogleDriveBackupException("Cannot create ZIP archive at [{$zipPath}].");
            }
            $zip->addFile($sqlDumpPath, "database-{$connection}.sql");
            $zip->close();
        } finally {
            if (file_exists($sqlDumpPath)) {
                @unlink($sqlDumpPath);
            }
        }
    }

    // ─── MySQL / MariaDB ──────────────────────────────────────────────────────

    private function dumpMysql(array $config, string $outputPath): void
    {
        $host     = escapeshellarg((string) ($config['host'] ?? '127.0.0.1'));
        $port     = (int)   ($config['port']     ?? 3306);
        $database = escapeshellarg((string) ($config['database'] ?? ''));
        $username = escapeshellarg((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $output   = escapeshellarg($outputPath);

        // Pass password via MYSQL_PWD env variable to avoid it appearing in process list
        $env = !empty($password) ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';

        $command = "{$env}mysqldump --no-tablespaces --single-transaction "
            . "--host={$host} --port={$port} --user={$username} {$database} > {$output} 2>&1";

        $this->runShellCommand($command, 'mysqldump');
    }

    // ─── PostgreSQL ───────────────────────────────────────────────────────────

    private function dumpPgsql(array $config, string $outputPath): void
    {
        $host     = escapeshellarg((string) ($config['host']     ?? '127.0.0.1'));
        $port     = (int)   ($config['port']     ?? 5432);
        $database = escapeshellarg((string) ($config['database'] ?? ''));
        $username = escapeshellarg((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $output   = escapeshellarg($outputPath);

        // PGPASSWORD env var avoids interactive password prompt
        $env = !empty($password) ? 'PGPASSWORD=' . escapeshellarg($password) . ' ' : '';

        $command = "{$env}pg_dump --no-password "
            . "--host={$host} --port={$port} --username={$username} {$database} > {$output} 2>&1";

        $this->runShellCommand($command, 'pg_dump');
    }

    // ─── SQLite ───────────────────────────────────────────────────────────────

    private function dumpSqlite(array $config, string $outputPath): void
    {
        $databasePath = (string) ($config['database'] ?? '');

        if (!file_exists($databasePath)) {
            throw new GoogleDriveBackupException("SQLite database file not found at [{$databasePath}].");
        }

        // For SQLite, dump the schema + data using the sqlite3 CLI
        $dbPath = escapeshellarg($databasePath);
        $output = escapeshellarg($outputPath);

        if ($this->commandExists('sqlite3')) {
            $command = "sqlite3 {$dbPath} .dump > {$output} 2>&1";
            $this->runShellCommand($command, 'sqlite3');
        } else {
            // Fallback: copy the raw database file as SQL placeholder
            copy($databasePath, $outputPath);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function runShellCommand(string $command, string $tool): void
    {
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new GoogleDriveBackupException(
                "Database dump failed using [{$tool}]. Exit code: {$exitCode}. " .
                    "Output: " . implode(' ', array_slice($output, 0, 3))
            );
        }
    }

    private function commandExists(string $command): bool
    {
        $result = shell_exec("which {$command} 2>/dev/null");
        return !empty(trim((string) $result));
    }
}
