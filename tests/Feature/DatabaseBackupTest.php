<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    #[Test]
    public function a_non_mysql_connection_is_refused(): void
    {
        config(['database.default' => 'sqlite']);

        $this->expectException(DomainException::class);

        app(DatabaseBackupService::class)->dump();
    }

    #[Test]
    public function the_command_fails_politely_instead_of_crashing(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('backup:database')->assertFailed();
    }

    #[Test]
    public function prune_removes_only_old_backups_of_our_own(): void
    {
        $dir = sys_get_temp_dir().'/pf-backup-'.uniqid();
        mkdir($dir, 0700, true);
        config(['services.backup.path' => $dir, 'services.backup.keep_days' => 14]);

        $old = $dir.'/pinoyfood-2026-01-01-020000.sql.gz';
        $new = $dir.'/pinoyfood-2026-10-08-020000.sql.gz';
        $other = $dir.'/notes.txt';
        foreach ([$old, $new, $other] as $f) {
            file_put_contents($f, 'x');
        }
        touch($old, now()->subDays(20)->getTimestamp());
        touch($other, now()->subDays(40)->getTimestamp());

        try {
            $this->assertSame(1, app(DatabaseBackupService::class)->prune());
            $this->assertFileDoesNotExist($old);
            $this->assertFileExists($new);
            $this->assertFileExists($other);
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
    }
}