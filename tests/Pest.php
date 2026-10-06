<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel app (HTTP, database, Inertia), each on a fresh database and
| a temp copy of the demo hub (see TestCase).
| Unit tests run on plain PHP classes and don't boot the app, so they stay fast.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * The If-Match header for a file as it is on disk now: the etag a page would have shown.
 *
 * @return array{If-Match: string}
 */
function ifMatch(string $path): array
{
    return ['If-Match' => '"'.hash_file('sha256', $path).'"'];
}
