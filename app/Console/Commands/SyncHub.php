<?php

namespace App\Console\Commands;

use App\Sync\Hub;
use App\Sync\HubSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('markboard:sync {--fresh : Empty the index tables and rebuild them from the files}')]
#[Description('Sync the hub\'s files into the database')]
class SyncHub extends Command
{
    public function handle(Hub $hub, HubSync $sync): int
    {
        if (! $hub->exists()) {
            $this->error("Hub not found: no projects.md in {$hub->path}");

            return self::FAILURE;
        }

        $count = $sync->run(fresh: (bool) $this->option('fresh'));
        $this->info("Re-indexed {$count} ".($count === 1 ? 'file' : 'files').'.');

        return self::SUCCESS;
    }
}
