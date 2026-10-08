<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\SplFileInfo;

/*
| With MARKBOARD_HUB_PATH unset, the app works on its own copy of demo/, made on first use, so
| editing the demo never changes tracked files. Here the copy goes to a temp folder instead of
| storage/app/demo-hub, and the config is unset the way an empty MARKBOARD_HUB_PATH leaves it.
*/

beforeEach(function () {
    $this->travelTo('2026-10-07 12:00:00');
    $this->demo = sys_get_temp_dir().'/markboard-demo-'.bin2hex(random_bytes(6));
    config(['markboard.hub_path' => null, 'markboard.demo_path' => $this->demo]);
});

afterEach(function () {
    File::deleteDirectory($this->demo);
});

/**
 * Every file in a folder, dotfiles included, by its path inside the folder.
 *
 * @return array<string, string>
 */
function filesIn(string $dir): array
{
    $files = collect(File::allFiles($dir, hidden: true))
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getRelativePathname() => $file->getContents()])
        ->all();
    ksort($files);

    return $files;
}

it('copies demo/ on first use and shows the demo projects', function () {
    expect(is_dir($this->demo))->toBeFalse();

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('hub.found', true)->has('projects', 7));

    expect(filesIn($this->demo))->toBe(filesIn(base_path('demo')))
        ->and(glob(sys_get_temp_dir().'/.markboard-demo-*.tmp') ?: [])->toBe([]);
});

it('writes edits to the copy, never to demo/', function () {
    $this->get('/')->assertOk();
    $copy = $this->demo.'/projects/lantern/TASKS.md';
    $tracked = (string) file_get_contents(base_path('demo/projects/lantern/TASKS.md'));

    $this->withHeaders(ifMatch($copy))->postJson('/api/v1/projects/lantern/tasks/T12/tick')->assertOk();

    expect(file_get_contents($copy))->toContain('- [x] T12 Export to CSV (due 2026-10-20, done 2026-10-07, from Up next)')
        ->and(file_get_contents(base_path('demo/projects/lantern/TASKS.md')))->toBe($tracked);
});

it('keeps an existing copy and its edits', function () {
    $this->artisan('markboard:demo')->expectsOutput("The demo hub is at {$this->demo}.")->assertSuccessful();
    file_put_contents($this->demo.'/TODAY.md', "# Edited\n");

    $this->artisan('markboard:demo')->assertSuccessful();
    $this->get('/')->assertOk();

    expect(file_get_contents($this->demo.'/TODAY.md'))->toBe("# Edited\n");
});

it('replaces the copy and every edit with --reset', function () {
    $this->artisan('markboard:demo')->assertSuccessful();
    file_put_contents($this->demo.'/TODAY.md', "# Edited\n");
    file_put_contents($this->demo.'/stray.md', "left behind\n");

    $this->artisan('markboard:demo --reset')->expectsOutput("Replaced the demo hub at {$this->demo}.")->assertSuccessful();

    expect(filesIn($this->demo))->toBe(filesIn(base_path('demo')));
});

it('says when MARKBOARD_HUB_PATH points the app at another hub', function () {
    config(['markboard.hub_path' => $this->hub]);

    $this->artisan('markboard:demo')
        ->expectsOutput("MARKBOARD_HUB_PATH is set, so the app uses {$this->hub}, not the demo hub.")
        ->assertSuccessful();
});
