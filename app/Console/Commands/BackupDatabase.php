<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use DomainException;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump the MySQL database to a gzipped file outside public/ and delete old dumps (spec 8)';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $file = $backups->dump();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup written: {$file}");
        $this->info('Old backups removed: '.$backups->prune());

        return self::SUCCESS;
    }
}