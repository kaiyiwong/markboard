<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /** A temp copy of demo/ for this test, so it can change files freely and never sees a real hub. */
    public string $hub;

    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without the built front end, so the PHP tests need no npm build.
        $this->withoutVite();

        $this->hub = sys_get_temp_dir().'/markboard-hub-'.bin2hex(random_bytes(6));
        File::copyDirectory(base_path('demo'), $this->hub);
        config(['markboard.hub_path' => $this->hub]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->hub);

        parent::tearDown();
    }
}
