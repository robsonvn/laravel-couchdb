<?php

return [

    'default' => 'database',

    'connections' => [

        'database' => [
            'driver' => 'couchdb',
            'table'  => 'jobs',
            'queue'  => 'default',
            'expire' => 60,
        ],

    ],

    'failed' => [
        'database' => 'couchdb',
        'table'    => 'failed_jobs',
    ],

];
