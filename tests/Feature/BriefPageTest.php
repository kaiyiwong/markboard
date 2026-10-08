<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Brief page, on a temp copy of demo/ (see TestCase): TODAY.md and two past briefs.
*/

it('renders today\'s brief with task references linked, and lists past briefs newest first', function () {
    $response = $this->get('/brief')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Brief/Show')
            ->where('date', null)
            ->where('dates', ['2026-10-06', '2026-10-05']));

    expect($response->inertiaProps('html'))
        ->toContain('<h1>Today, 2026-10-07</h1>')
        ->toContain('<a href="/projects/bramble-bakery/tasks/T4">[bramble-bakery] T4</a> is overdue')
        ->toContain('<a href="/projects/lantern/tasks/T9">[lantern] T9</a>');
});

it('renders a past brief by its date', function () {
    $html = $this->get('/brief/2026-10-05')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-05'))
        ->inertiaProps('html');

    expect($html)->toContain('<h1>Brief, 2026-10-05</h1>')
        ->toContain('<a href="/projects/lantern/tasks/T11">[lantern] T11</a>');
});

it('returns 404 for a date with no brief or that is not a date', function () {
    $this->get('/brief/2026-10-01')->assertNotFound();
    $this->get('/brief/yesterday')->assertNotFound();
    $this->get('/brief/..%2FTODAY')->assertNotFound();
});

it('shows raw HTML in a brief as text and drops unsafe links', function () {
    File::put($this->hub.'/TODAY.md', "# Today\n\n<script>alert(1)</script>\n\n[click](javascript:alert(1))\n");

    expect($this->get('/brief')->inertiaProps('html'))
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>')
        ->not->toContain('javascript:');
});

it('says there is no brief yet, and still lists past ones', function () {
    File::delete($this->hub.'/TODAY.md');

    $this->get('/brief')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('html', null)->has('dates', 2));
});

it('shares "hub not found" with every page when the hub has no projects.md', function () {
    File::delete($this->hub.'/projects.md');

    foreach (['/', '/pipeline', '/brief'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('hub', ['found' => false, 'path' => $this->hub]));
    }
});
