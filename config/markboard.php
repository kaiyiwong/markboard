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

];
