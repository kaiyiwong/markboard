<?php

use App\Night\NightGit;
use App\Sync\Hub;

it("puts a task's worktree in the hidden night folder beside the hub, as run-night.py does", function () {
    $git = new NightGit('/Users/sam/projects/clients/lantern', (new Hub('/Users/sam/projects/hub'))->nightWorktreesPath());

    expect($git->worktreePath('T20'))->toBe('/Users/sam/projects/.night/lantern-T20');
});
