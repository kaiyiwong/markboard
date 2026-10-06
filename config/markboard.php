<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hub folder
    |--------------------------------------------------------------------------
    |
    | The folder holding projects.md, priorities.md, TODAY.md and briefs/.
    | Unset, the app works on its copy of the demo hub, so editing the demo
    | never changes tracked files.
    |
    */

    'hub_path' => env('MARKBOARD_HUB_PATH') ?: storage_path('app/demo-hub'),

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
