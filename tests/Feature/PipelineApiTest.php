<?php

use App\Models\Conflict;

/*
| The pipeline endpoints of /api/v1 on a temp copy of demo/ (see TestCase): Job search's
| pipeline.md has five rows. A row is its 1-based position among the table's rows.
*/

beforeEach(function () {
    $this->pipeline = $this->hub.'/projects/job-search/pipeline.md';
    $this->original = (string) file_get_contents($this->pipeline);
});

describe('200: written', function () {
    it('edits only the given fields of one row, and returns it with the new ETag', function () {
        $response = $this->withHeaders(ifMatch($this->pipeline))
            ->patchJson('/api/v1/projects/job-search/pipeline/rows/2', ['stage' => 'interviewing', 'date' => ''])
            ->assertOk()
            ->assertJsonPath('data', ['position' => 2, 'company' => 'Tailspin', 'role' => 'Full-Stack Developer', 'stage' => 'interviewing', 'next_action' => 'wait for take-home feedback', 'date' => null]);

        expect(file_get_contents($this->pipeline))->toBe(str_replace(
            '| Tailspin | Full-Stack Developer | screening | wait for take-home feedback | 2026-10-09 |',
            '| Tailspin | Full-Stack Developer | interviewing | wait for take-home feedback |  |',
            $this->original,
        ))
            ->and($response->headers->get('ETag'))->toBe('"'.hash_file('sha256', $this->pipeline).'"');
    });

    it('adds a row after the last one, with an optional date', function () {
        $this->withHeaders(ifMatch($this->pipeline))
            ->postJson('/api/v1/projects/job-search/pipeline/rows', ['company' => 'Adatum', 'role' => 'Vue Developer', 'stage' => 'applied', 'next_action' => 'send portfolio'])
            ->assertOk()
            ->assertJsonPath('data.position', 6)
            ->assertJsonPath('data.date', null);

        expect(file_get_contents($this->pipeline))->toBe($this->original.'| Adatum | Vue Developer | applied | send portfolio |  |'."\n");
    });
});

describe('the other status codes', function () {
    it('returns 404 for a row that is not in the table', function () {
        $this->withHeaders(ifMatch($this->pipeline))->patchJson('/api/v1/projects/job-search/pipeline/rows/6', ['stage' => 'offer'])->assertNotFound();
        $this->withHeaders(ifMatch($this->pipeline))->patchJson('/api/v1/projects/job-search/pipeline/rows/0', ['stage' => 'offer'])->assertNotFound();
    });

    it('returns 409 for a project with no pipeline.md, or one with no table', function () {
        $this->withHeader('If-Match', '"x"')->patchJson('/api/v1/projects/lantern/pipeline/rows/1', ['stage' => 'offer'])->assertConflict();

        file_put_contents($this->pipeline, "# Pipeline\n\nNothing yet.\n");
        $this->withHeaders(ifMatch($this->pipeline))
            ->postJson('/api/v1/projects/job-search/pipeline/rows', ['company' => 'Adatum', 'role' => 'Dev', 'stage' => 'applied', 'next_action' => 'apply'])
            ->assertConflict();
        expect(file_get_contents($this->pipeline))->toBe("# Pipeline\n\nNothing yet.\n");
    });

    it('returns 412 and records a conflict when the file changed', function () {
        $this->get('/pipeline');
        $seen = ifMatch($this->pipeline);
        file_put_contents($this->pipeline, str_replace('compare the offer', 'accept the offer', $this->original));

        $this->withHeaders($seen)
            ->patchJson('/api/v1/projects/job-search/pipeline/rows/1', ['stage' => 'offer'])
            ->assertStatus(412)
            ->assertJsonPath('conflict.summary', 'Edit row 1')
            ->assertJsonPath('conflict.applicable', true)
            ->assertJsonPath('conflict.current.company', 'Northwind');

        // toEqual, not toBe: MySQL's JSON type sorts keys, and replay passes them as named arguments.
        expect(Conflict::sole()->parameters)->toEqual(['position' => 1, 'changes' => ['stage' => 'offer']]);
    });

    it('refuses bad fields with 422', function (array $body, string $field) {
        $this->withHeaders(ifMatch($this->pipeline))
            ->patchJson('/api/v1/projects/job-search/pipeline/rows/1', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(file_get_contents($this->pipeline))->toBe($this->original);
    })->with([
        'an unknown stage' => [['stage' => 'dreaming'], 'stage'],
        'a | in a cell' => [['company' => 'North|wind'], 'company'],
        'an empty company' => [['company' => ''], 'company'],
        'a cell over 200 characters' => [['role' => str_repeat('a', 201)], 'role'],
        'a date that is not real' => [['date' => '2026-13-01'], 'date'],
    ]);

    it('needs every field but the date to add a row', function () {
        $this->withHeaders(ifMatch($this->pipeline))
            ->postJson('/api/v1/projects/job-search/pipeline/rows', ['company' => 'Adatum'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'stage', 'next_action'])
            ->assertJsonMissingValidationErrors('date');
    });

    it('returns 428 without If-Match', function () {
        $this->patchJson('/api/v1/projects/job-search/pipeline/rows/1', ['stage' => 'offer'])->assertStatus(428);
    });
});
