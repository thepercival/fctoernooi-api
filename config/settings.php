<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

return [
    'environment' => getenv('ENVIRONMENT'),
    'displayErrorDetails' => (getenv('ENVIRONMENT') === "development"),
    // Renderer settings
    'renderer' => [
        'template_path' => __DIR__ . '/../templates/',
    ],
    // Serializer(JMS)
    'serializer' => [
        'cache_dir' => __DIR__ . '/../cache/serializer',
        'yml_dir' => [
            "Sports" => __DIR__ . '/../vendor/thepercival/php-sports/serialization/yml',
            "FCToernooi" => __DIR__ . '/../serialization/yml'
        ],
    ],
    // Monolog settings
    'logger' => [
        'path' => __DIR__ . '/../logs/',
        'level' => (getenv('ENVIRONMENT') === "development" ? \Monolog\Logger::DEBUG : \Monolog\Logger::ERROR),
    ],
    'router' => [
        'cache_file' => __DIR__ . '/../cache/router',
    ],
    // Doctrine settings
    'doctrine' => [
        'meta' => [
            'entity_path' => [
                __DIR__ . '/../vendor/thepercival/php-sports-planning/db/doctrine-mappings',
                __DIR__ . '/../vendor/thepercival/php-sports/db/doctrine-mappings',
                __DIR__ . '/../db/doctrine-mappings'
            ],
            'dev_mode' => (getenv('ENVIRONMENT') === "development"),
            'proxy_dir' => __DIR__ . '/../cache/proxies',
            'cache' => null,
        ],
        'connection' => [
            'driver' => 'pdo_mysql',
            'host' => getenv('DB_HOST'),
            'dbname' => getenv('DB_NAME'),
            'user' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'driverOptions' => array(
                1002 => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_general_ci'"
            )
        ],
        'serializer' => array(
            'enabled' => true
        ),
    ],
    'auth' => [
        'jwtsecret' => getenv('JWT_SECRET'),
        'jwtalgorithm' => getenv('JWT_ALGORITHM'),
        'activationsecret' => getenv('ACTIVATION_SECRET'),
    ],
    'www' => [
        'wwwurl' => getenv('WWW_URL'),
        'wwwurl-localpath' => realpath(__DIR__ . "/../../") . "/fctoernooi/dist/",
        'apiurl' => getenv('API_URL'),
        "apiurl-localpath" => realpath(__DIR__ . '/../public/') . '/',
    ],
    'email' => [
        'from' => "info@fctoernooi.nl",
        'fromname' => "FCToernooi",
        'admin' => "fctoernooi2018@gmail.com",
        'mailtrap' => [
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_user' => getenv('MAILTRAP_USER'),
            'smtp_pass' => getenv('MAILTRAP_PASSWORD')
        ]
    ],
    'images' => [
        'sponsors' => [
            'pathpostfix' => 'images/sponsors/',
            'backuppath' => '/var/sponsorbackups/',
        ]
    ],
    'queue' => [
        'host' => 'localhost',
        'port' => 5672,
        'vhost' => '/',
        'user' => 'guest',
        'pass' => 'guest',
        'persisted' => false,
        'queueSuffix' => getenv('QUEUE_NAME_SUFFIX')
    ]
];
