<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use Symfony\Component\Process\Process;

/** Nightly dump kept outside public/ (spec 8). The password goes through the environment, never the command line. */
class DatabaseBackupService
{
    private const PREFIX = 'pinoyfood-';

    public function directory(): string
    {
        return rtrim((string) config('services.backup.path'), '/');
    }

    /** @return string path of the .sql.gz file */
    public function dump(): string
    {
        $c = (array) config('database.connections.'.config('database.default'));

        if (($c['driver'] ?? '') !== 'mysql') {
            throw new DomainException('Backups need a MySQL connection.');
        }

        $dir = $this->directory();
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new DomainException("Cannot create the backup folder {$dir}.");
        }

        $sql = $dir.'/'.self::PREFIX.now()->format('Y-m-d-His').'.sql';

        $process = new Process([
            (string) config('services.backup.mysqldump'),
            '--single-transaction', '--quick', '--no-tablespaces',
            '--host='.($c['host'] ?? '127.0.0.1'),
            '--port='.($c['port'] ?? '3306'),
            '--user='.($c['username'] ?? ''),
            '--result-file='.$sql,
            (string) ($c['database'] ?? ''),
        ], null, ['MYSQL_PWD' => (string) ($c['password'] ?? '')]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($sql)) {
            @unlink($sql);

            throw new DomainException('mysqldump failed: '.trim($process->getErrorOutput() ?: 'no output'));
        }

        $gz = $sql.'.gz';
        file_put_contents($gz, gzencode((string) file_get_contents($sql), 6));
        @unlink($sql);
        @chmod($gz, 0600);

        return $gz;
    }

    /** Deletes our own dumps older than keep_days. Other files in the folder are never touched. */
    public function prune(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('services.backup.keep_days')))->getTimestamp();
        $removed = 0;

        foreach (glob($this->directory().'/'.self::PREFIX.'*.sql.gz') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}