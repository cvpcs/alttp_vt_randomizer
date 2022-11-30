<?php

return [
    'paths' => [
        realpath(base_path('resources/views')),
    ],

    'compiled' => realpath(env('STORAGE_FWK_VIEWS', storage_path('framework/views'))),
];
