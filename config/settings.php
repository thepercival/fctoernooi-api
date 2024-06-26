<?php

declare(strict_types=1);

use Monolog\Logger;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

return [
    'environment' => $_ENV['ENVIRONMENT'],
    'namespace' => 'fctoernooi' . $_ENV['ENVIRONMENT'],
    'displayErrorDetails' => $_ENV['ENVIRONMENT'] === 'development',
    'tournament' => [
        'nrOfMonthsBeforeRemoval' => $_ENV['NR_OF_MONTHS_BEFORE_REMOVAL'],
    ],
    // Renderer settings
    'renderer' => [
        'template_path' => __DIR__ . '/../templates/',
    ],
    // Serializer(JMS)
    'serializer' => [
        'cache_dir' => __DIR__ . '/../cache/serializer',
        'yml_dir' => [
            'SportsHelpers' => __DIR__ . '/../vendor/thepercival/php-sports-helpers/serialization/yml',
            'SportsPlanning' => __DIR__ . '/../vendor/thepercival/php-sports-planning/serialization/yml',
            'Sports' => __DIR__ . '/../vendor/thepercival/php-sports/serialization/yml',
            'FCToernooi' => __DIR__ . '/../serialization/yml'
        ],
    ],
    // Monolog settings
    'logger' => [
        'path' => __DIR__ . '/../logs/',
        'level' => ($_ENV['ENVIRONMENT'] === 'development' ? Logger::DEBUG : Logger::ERROR),
    ],
    'router' => [
        'cache_file' => __DIR__ . '/../cache/router',
    ],
    'payment' => [
        'webhookUrl' => $_ENV['ENVIRONMENT'] !== 'development'
            ? $_ENV['API_URL'] : 'https://59da-2001-1c06-1ecc-1400-a2c-79e4-aada-25d9.ngrok-free.app/'
            . 'public/paymentUpdate',
        'mollie' => [
            'apikey' => $_ENV['MOLLIE_APIKEY']
        ],
        'redirectUrl' =>  $_ENV['WWW_URL'] . 'user/paymentresult',
    ],
    // Doctrine settings
    'doctrine' => [
        'meta' => [
            'entity_path' => [
                __DIR__ . '/../vendor/thepercival/php-sports-helpers/db/doctrine-mappings',
                __DIR__ . '/../vendor/thepercival/php-sports/db/doctrine-mappings',
                __DIR__ . '/../db/doctrine-mappings'
            ],
            'dev_mode' => ($_ENV['ENVIRONMENT'] === 'development'),
            'proxy_dir' => __DIR__ . '/../cache/proxies',
            'cache' => null,
        ],
        'connection' => [
            'driver' => 'pdo_mysql',
            'host' => $_ENV['DB_HOST'],
            'dbname' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'charset' => 'utf8mb4',
            'driverOptions' => [
                1002 => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_general_ci'"
            ]
        ],
        'serializer' => [
            'enabled' => true
        ],
    ],
    'auth' => [
        'jwtsecret' => $_ENV['JWT_SECRET'],
        'jwtalgorithm' => $_ENV['JWT_ALGORITHM'],
        'activationsecret' => $_ENV['ACTIVATION_SECRET'],
    ],
    'www' => [
        'wwwurl' => $_ENV['WWW_URL'],
        'wwwurl-localpath' => realpath(__DIR__ . '/../../') . '/fctoernooi/dist/',
        'apiurl' => $_ENV['API_URL'],
        'apiurl-localpath' => realpath(__DIR__ . '/../public/') . '/',
    ],
    'email' => [
        'from' => 'info@fctoernooi.nl',
        'fromname' => 'FCToernooi',
        'admin' => 'fctoernooi2018@gmail.com',
        'mailtrap' => [
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_user' => $_ENV['MAILTRAP_USER'],
            'smtp_pass' => $_ENV['MAILTRAP_PASSWORD']
        ],
        'textTemplatesPath' => __DIR__ . '/../templates/registration/'
    ],
    'images' => [
        'backuppath' => (($_ENV['ENVIRONMENT'] === 'development') ? '/tmp' : '/var') . '/imgbackups'
    ],
    'queue' => [
        'host' => 'localhost',  // 'rattlesnake.rmq.cloudamqp.com'
        'port' => 5672,
        'vhost' => '/', // 'ctydjbyu'
        'user' => 'guest', // 'ctydjbyu'
        'pass' => 'guest', // $_ENV['CLOUD_AMQP_APIKEY2']
        'persisted' => false,
        'suffix' => 'fctoernooi' . '-' . $_ENV['ENVIRONMENT'],
        'planningSuffix' => 'sportsscheduler' . '-' . $_ENV['ENVIRONMENT']
    ],
//    'queue' => [
//        'host' => 'localhost',
//        'port' => 5672,
//        'vhost' => '/',
//        'user' => 'guest',
//        'pass' => 'guest',
//        'persisted' => false,
//        'suffix' => 'fctoernooi' . $_ENV['ENVIRONMENT']
//    ],
    'scheduler' => [
        'url' => $_ENV['SCHEDULER_URL'],
        'apikey' => $_ENV['SCHEDULER_APIKEY'],
    ]
];
