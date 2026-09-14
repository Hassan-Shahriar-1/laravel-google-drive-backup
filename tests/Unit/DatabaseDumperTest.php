<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use HassanShahriar\GoogleDriveBackup\Exceptions\GoogleDriveBackupException;
use HassanShahriar\GoogleDriveBackup\Services\DatabaseDumper;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;
use ZipArchive;

class DatabaseDumperTest extends TestCase
{
    public function test_throws_exception_on_unsupported_driver(): void
    {
        $this->expectException(GoogleDriveBackupException::class);
        $this->expectExceptionMessage('Unsupported database driver [oracle]');

        config()->set('database.connections.oracle_test', [
            'driver' => 'oracle',
        ]);

        $dumper = new DatabaseDumper();
        $dumper->dumpToZip(sys_get_temp_dir() . '/test.zip', 'oracle_test');
    }

    public function test_throws_exception_when_no_connection_specified(): void
    {
        config()->set('database.default', null);

        $this->expectException(GoogleDriveBackupException::class);
        $this->expectExceptionMessage('No database connection specified');

        $dumper = new DatabaseDumper();
        $dumper->dumpToZip(sys_get_temp_dir() . '/test.zip', null);
    }

    public function test_throws_exception_when_connection_not_defined_in_config(): void
    {
        $this->expectException(GoogleDriveBackupException::class);
        $this->expectExceptionMessage('Database connection [unknown_db] is not defined in config/database.php');

        $dumper = new DatabaseDumper();
        $dumper->dumpToZip(sys_get_temp_dir() . '/test.zip', 'unknown_db');
    }

    public function test_dumps_sqlite_database_successfully(): void
    {
        $sqlitePath = tempnam(sys_get_temp_dir(), 'test_db_') . '.sqlite';
        touch($sqlitePath);

        config()->set('database.connections.sqlite_test', [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'test_dump_') . '.zip';

        try {
            $dumper = new DatabaseDumper();
            $dumper->dumpToZip($zipPath, 'sqlite_test');

            $this->assertFileExists($zipPath);

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($zipPath));
            $this->assertNotFalse($zip->locateName('database-sqlite_test.sql'));
            $zip->close();
        } finally {
            if (file_exists($sqlitePath)) {
                @unlink($sqlitePath);
            }
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }
    }
}
