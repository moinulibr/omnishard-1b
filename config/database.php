<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    #'default' => env('DB_CONNECTION', 'mysql'),
    'default' => env('DB_CONNECTION', 'metadata'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'connections' => [

            'metadata' => [
                'driver' => 'mysql',
                'host' => 'omnishard-metadata',
                'port' => '3306',
                'database' => 'metadata_db',
                'username' => 'root',
                'password' => 'root',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],

            'shard_1' => [
                'driver' => 'mysql',
                'read' => [
                    'host' => ['omnishard-shard-1-replica'],
                ],
                'write' => [
                    'host' => ['omnishard-shard-1'],
                ],
                'sticky' => true,
                'database' => 'shard_1_db',
                'username' => 'root',
                'password' => 'root',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],

            'shard_2' => [
                'driver' => 'mysql',
                'host' => 'omnishard-shard-2',
                'port' => '3306',
                'database' => 'shard_2_db',
                'username' => 'root',
                'password' => 'root',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],

            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'laravel'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ],

        'migrations' => [
            'table' => 'migrations',
            'update_date_on_publish' => true,
        ],

        'redis' => [
            'client' => env('REDIS_CLIENT', 'phpredis'),

            'options' => [
                'cluster' => env('REDIS_CLUSTER', 'redis'),
                'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel')) . '_database_'),
            ],

            'default' => [
                'url' => env('REDIS_URL'),
                'host' => env('REDIS_HOST', 'omnishard-redis'),
                'password' => env('REDIS_PASSWORD', null),
                'port' => env('REDIS_PORT', '6379'),
                'database' => env('REDIS_DB', '0'),
            ],

            'cache' => [
                'url' => env('REDIS_URL'),
                'host' => env('REDIS_HOST', 'omnishard-redis'),
                'password' => env('REDIS_PASSWORD', null),
                'port' => env('REDIS_PORT', '6379'),
                'database' => env('REDIS_CACHE_DB', '1'),
            ],
        ],

    ],

];
