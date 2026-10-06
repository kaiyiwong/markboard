<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel app (HTTP, database, Inertia), each on a fresh database and
| a temp copy of the demo hub (see TestCase).
| Unit tests run on plain PHP classes and don't boot the app, so they stay fast.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
