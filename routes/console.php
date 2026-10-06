<?php

use Illuminate\Support\Facades\Schedule;

// Keeps search current when no page is open; pages also sync on every request.
Schedule::command('markboard:sync')->everyMinute();
