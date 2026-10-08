<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hub folder
    |--------------------------------------------------------------------------
    |
    | The folder holding projects.md, priorities.md, TODAY.md and briefs/.
    | Unset, the app works on its copy of demo/ at demo_path, made on first
    | use, so editing the demo never changes tracked files. Replace the copy
    | with `php artisan markboard:demo --reset`.
    |
    */

    'hub_path' => env('MARKBOARD_HUB_PATH') ?: null,

    'demo_path' => storage_path('app/demo-hub'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone that defines "today": for the overdue and due-soon badges,
    | and for the dates the app writes into files.
    |
    */

    'timezone' => env('MARKBOARD_TIMEZONE') ?: 'UTC',

];
