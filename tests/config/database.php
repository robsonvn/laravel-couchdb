<?php

return [

    'connections' => [

        'couchdb' => [
            'name'       => 'couchdb',
            'type'       => 'socket',
            'driver'     => 'couchdb',
            'host'       => '10.0.0.66',
            'dbname'     => 'unittest',
            'user'       => 'admin2',
            'password'   => 'secret',
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
