<?php

use Tests\TestCase;

/*
| Feature tests boot the Laravel app (HTTP, database, Inertia).
| Unit tests run on plain PHP classes and don't boot the app, so they stay fast.
*/

pest()->extend(TestCase::class)->in('Feature');
