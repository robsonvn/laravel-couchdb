<?php

return [

    'connections' => [

        'couchdb' => [
            'name'       => 'couchdb',
            'type'       => 'socket',
            'driver'     => 'couchdb',
            'host'       => getenv('COUCHDB_HOST','localhost'),
            'dbname'     => getenv('COUCHDB_DB_NAME','test'),
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
