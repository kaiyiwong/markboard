<?php

namespace Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    /** A temp copy of demo/ for this test, so it can change files freely and never sees a real hub. */
    public string $hub;

    /** The app key for this test run, made fresh each run, so the repo holds no key and needs no environment file. */
    private static ?string $key = null;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('app.key', self::$key ??= 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc')));

        return $app;
    }

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
