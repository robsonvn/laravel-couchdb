<?php

return [

    'connections' => [

        'couchdb' => [
            'name'       => 'couchdb',
            'type'       => 'socket',
            'driver'     => 'couchdb',
            'host'       => '127.0.0.1',
            'dbname'     => 'unittest',
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => 'unittest',
            'username'  => 'travis',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
    ],

];
