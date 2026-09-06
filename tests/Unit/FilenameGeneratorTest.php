<?php

declare(strict_types=1);

namespace HassanShahriar\GoogleDriveBackup\Tests\Unit;

use HassanShahriar\GoogleDriveBackup\Services\FilenameGenerator;
use HassanShahriar\GoogleDriveBackup\Tests\TestCase;

class FilenameGeneratorTest extends TestCase
{
    private FilenameGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new FilenameGenerator();
    }

    public function test_generates_filename_with_correct_structure(): void
    {
        $filename = $this->generator->generate('my-app', 'production', 'full');

        // Should match: myapp-production-full-YYYYMMDD-HHmmss-XXXXXXXX.zip
        $this->assertMatchesRegularExpression(
            '/^my-app-production-full-\d{8}-\d{6}-[a-f0-9]{8}\.zip$/',
            $filename
        );
    }

    public function test_sanitizes_special_characters_in_app_name(): void
    {
        $filename = $this->generator->generate('My Cool App!', 'production', 'database');

        $this->assertStringStartsWith('my-cool-app-production-database-', $filename);
        $this->assertStringNotContainsString('!', $filename);
        $this->assertStringNotContainsString(' ', $filename);
    }

    public function test_generates_unique_filenames(): void
    {
        $a = $this->generator->generate('app', 'production', 'full');
        $b = $this->generator->generate('app', 'production', 'full');

        // UUID suffix makes them unique
        $this->assertNotSame($a, $b);
    }

    public function test_filename_contains_backup_type(): void
    {
        foreach (['full', 'database', 'files'] as $type) {
            $filename = $this->generator->generate('myapp', 'production', $type);
            $this->assertStringContainsString($type, $filename);
        }
    }

    public function test_sanitize_handles_edge_cases(): void
    {
        $this->assertSame('my-app', $this->generator->sanitize('My App'));
        $this->assertSame('my-app', $this->generator->sanitize('my.app'));
        $this->assertSame('myapp', $this->generator->sanitize('myapp'));
        $this->assertSame('my-app', $this->generator->sanitize('---my-app---'));
        $this->assertSame('', $this->generator->sanitize('!!!'));
    }

    public function test_custom_format_token_replacement(): void
    {
        $filename = $this->generator->generate('app', 'staging', 'database', '{app}_{type}.zip');

        $this->assertStringStartsWith('app_database.zip', $filename);
    }
}
