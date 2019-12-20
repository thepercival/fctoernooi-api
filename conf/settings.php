<?php

$dotenv = new \Dotenv\Dotenv(__DIR__ . '/../');
$dotenv->load();

return [
    'settings' => [
        'environment' => getenv('ENVIRONMENT'),
        'displayErrorDetails' => ( getenv('ENVIRONMENT') === "development" ),
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
	    'determineRouteBeforeAppMiddleware' => true,

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],
        // Serializer(JMS)
        'serializer' => [
            'cache_dir' =>  __DIR__.'/../cache/serializer',
            'yml_dir' => [
                "Voetbal" => __DIR__ . '/../vendor/thepercival/voetbal/serialization/yml',
                "FCToernooi" => __DIR__ . '/../serialization/yml'
            ],
        ],
        // Monolog settings
        'logger' => [
            'name' => 'cronjob',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/application.log',
            'level' => ( getenv('ENVIRONMENT') === "development" ? \Monolog\Logger::DEBUG : \Monolog\Logger::ERROR),
            'cronjobpath' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/cronjob_',
        ],
        // Doctrine settings
        'doctrine' => [
            'meta' => [
                'entity_path' => [
                    __DIR__ . '/../vendor/thepercival/voetbal/db/doctrine-mappings',
                    __DIR__ . '/../db/doctrine-mappings'
                ],
                'dev_mode' => ( getenv('ENVIRONMENT') === "development" ),
                'proxy_dir' => __DIR__ . '/../cache/proxies',
                'cache' => null,
            ],
            'connection' => [
                'driver'   => 'pdo_mysql',
                'host'     => getenv('DB_HOST'),
                'dbname'   => getenv('DB_NAME'),
                'user'     => getenv('DB_USERNAME'),
                'password' => getenv('DB_PASSWORD'),
                'charset'  => 'utf8mb4',
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
            'urls' => explode(",", getenv('WWW_URLS') ),
            'apiurl' => getenv('API_URL'),
            "apiurl-localpath" =>  realpath( __DIR__ . '/../public/' ) . '/',
        ],
        'email' => [
            'from' => "noreply@fctoernooi.nl",
            'fromname' => "FCToernooi"
        ],
        'images' => [
            'sponsors' => [
                'pathpostfix' => 'images/sponsors/',
                'backuppath' => '/var/www/backup/fctoernooi/',
            ]
        ],
    ],
];
