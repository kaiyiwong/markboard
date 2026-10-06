<?php

namespace App\Console\Commands;

use App\Sync\DemoHub;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('markboard:demo {--reset : Replace the copy, and every edit made to it, with a fresh one from demo/}')]
#[Description('Copy demo/ to the demo hub the app uses when MARKBOARD_HUB_PATH is unset')]
class CopyDemoHub extends Command
{
    public function handle(DemoHub $demo): int
    {
        if ($this->option('reset')) {
            $demo->reset();
            $this->info("Replaced the demo hub at {$demo->path}.");
        } else {
            $demo->ensure();
            $this->info("The demo hub is at {$demo->path}.");
        }

        $hubPath = config('markboard.hub_path');
        if (is_string($hubPath)) {
            $this->warn("MARKBOARD_HUB_PATH is set, so the app uses {$hubPath}, not the demo hub.");
        }

        return self::SUCCESS;
    }
}
