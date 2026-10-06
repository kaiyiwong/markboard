<?php

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

/*
| The Pipeline page, on a temp copy of demo/ (see TestCase): only Job search has a pipeline.md.
*/

it('shows one board per project with a pipeline.md, with its rows and etag', function () {
    $response = $this->get('/pipeline')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipeline/Index')
            ->has('boards', 1)
            ->where('boards.0.project', ['id' => 'job-search', 'name' => 'Job search'])
            ->where('boards.0.file.etag', hash_file('sha256', $this->hub.'/projects/job-search/pipeline.md'))
            ->where('boards.0.file.editable', true)
            ->where('stages', [
                'open' => ['applied', 'screening', 'interviewing', 'offer'],
                'closed' => ['accepted', 'rejected', 'withdrawn', 'closed'],
            ]));

    expect($response->inertiaProps('boards.0.rows'))->toBe([
        ['position' => 1, 'company' => 'Northwind', 'role' => 'Senior Engineer', 'stage' => 'interviewing', 'next_action' => 'prepare system design', 'date' => '2026-10-14'],
        ['position' => 2, 'company' => 'Tailspin', 'role' => 'Full-Stack Developer', 'stage' => 'screening', 'next_action' => 'wait for take-home feedback', 'date' => '2026-10-09'],
        ['position' => 3, 'company' => 'Contoso Labs', 'role' => 'Laravel Developer', 'stage' => 'applied', 'next_action' => 'follow up with the recruiter', 'date' => '2026-10-12'],
        ['position' => 4, 'company' => 'Fabrikam', 'role' => 'Lead Developer', 'stage' => 'offer', 'next_action' => 'compare the offer', 'date' => '2026-10-08'],
        ['position' => 5, 'company' => 'Litware', 'role' => 'Backend Engineer', 'stage' => 'rejected', 'next_action' => 'none', 'date' => null],
    ]);
});

it('adds a board for every project that gains a pipeline.md, read-only if it has errors', function () {
    File::put($this->hub.'/projects/lantern/pipeline.md', "| company | role | stage | next action | date |\n|---|---|---|---|---|\n| Acme | Tester | dreaming | none | |\n");

    $this->get('/pipeline')
        ->assertInertia(fn (Assert $page) => $page
            ->has('boards', 2)
            ->where('boards.0.project.id', 'job-search')
            ->where('boards.1.project.id', 'lantern')
            ->where('boards.1.file.editable', false)
            ->has('boards.1.file.errors', 1)
            ->where('boards.1.rows.0.stage', null));
});
