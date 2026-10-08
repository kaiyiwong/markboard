<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Brief page, on a temp copy of demo/ (see TestCase): TODAY.md and two past briefs.
*/

it('splits today\'s brief into sections with project tags linked, and lists past briefs newest first', function () {
    $brief = $this->get('/brief')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Brief/Show')
            ->where('date', null)
            ->where('dates', ['2026-10-06', '2026-10-05']))
        ->inertiaProps('brief');

    expect($brief['title'])->toBe('Today, 2026-10-07')
        ->and(array_column($brief['sections'], 'items', 'title'))->toBe(['Focus' => 2, 'Waiting' => 1, 'Notes' => 0])
        ->and($brief['sections'][0]['html'])
        ->toContain('<a href="/projects/bramble-bakery">Bramble Bakery</a> <a href="/projects/bramble-bakery/tasks/T4">T4</a> is overdue')
        ->and($brief['sections'][1]['html'])->toContain('<a href="/projects/lantern/tasks/T9">T9</a>');
});

it('renders a past brief by its date', function () {
    $brief = $this->get('/brief/2026-10-05')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-05'))
        ->inertiaProps('brief');

    expect($brief['title'])->toBe('Brief, 2026-10-05')
        ->and($brief['sections'])->toBe([])
        ->and($brief['intro'])->toContain('<a href="/projects/lantern">Lantern</a> <a href="/projects/lantern/tasks/T11">T11</a>');
});

it('returns 404 for a date with no brief or that is not a date', function () {
    $this->get('/brief/2026-10-01')->assertNotFound();
    $this->get('/brief/yesterday')->assertNotFound();
    $this->get('/brief/..%2FTODAY')->assertNotFound();
});

it('shows raw HTML in a brief as text and drops unsafe links', function () {
    File::put($this->hub.'/TODAY.md', "# Today\n\n## Notes\n\n<script>alert(1)</script>\n\n[click](javascript:alert(1))\n");

    expect($this->get('/brief')->inertiaProps('brief.sections.0.html'))
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>')
        ->not->toContain('javascript:');
});

it('says there is no brief yet, and still lists past ones', function () {
    File::delete($this->hub.'/TODAY.md');

    $this->get('/brief')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('brief', null)->has('dates', 2));
});

it('shares "hub not found" with every page when the hub has no projects.md', function () {
    File::delete($this->hub.'/projects.md');

    foreach (['/', '/pipeline', '/brief'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('hub', ['found' => false, 'path' => $this->hub]));
    }
});
