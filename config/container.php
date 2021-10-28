<?php

declare(strict_types=1);

use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Psr\Container\ContainerInterface;
use Doctrine\ORM\EntityManager;

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;

use Slim\Views\Twig as TwigView;

use App\Mailer;
use FCToernooi\SerializationHandler\Subscriber as HandlerSubscriber;
use Sports\SerializationHandler\Round\Number as RoundNumberSerializationHandler;
use Sports\SerializationHandler\Structure as StructureSerializationHandler;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Sports\SerializationHandler\Round as RoundSerializationHandler;
use Selective\Config\Configuration;
use Slim\App;
use Slim\Factory\AppFactory;
use Sports\SerializationHandler\DummyCreator;
use FCToernooi\Auth\Settings as AuthSettings;

return [
    // Application settings
    Configuration::class => function (): Configuration {
        return new Configuration(require __DIR__ . '/settings.php');
    },
    App::class => function (ContainerInterface $container): App {
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        if ($config->getString("environment") === "production") {
            $routeCacheFile = $config->getString('router.cache_file');
            if ($routeCacheFile) {
                $app->getRouteCollector()->setCacheFile($routeCacheFile);
            }
        }
        return $app;
    },
    TwigView::class => function (ContainerInterface $container): TwigView {
        $cache = [];
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        if ($config->getString("environment") === "production") {
            $cache['cache'] = __DIR__ . '/../cache';
        }
        return TwigView::create(__DIR__ . '/../templates', $cache);
    },
    LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $loggerSettings = $config->getArray('logger');
        $name = "application";
        $logger = new Logger($name);

        $processor = new UidProcessor();
        $logger->pushProcessor($processor);

        $path = $config->getString(
            "environment"
        ) === "development" ? 'php://stdout' : ($loggerSettings['path'] . $name . '.log');

        $handler = new StreamHandler($path, $loggerSettings['level']);
        $logger->pushHandler($handler);

        return $logger;
    },
    EntityManager::class => function (ContainerInterface $container): EntityManager {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $appDoctrineConfig = $config->getArray('doctrine');
        $doctrineMetaConfig = $appDoctrineConfig['meta'];
        $doctrineConfig = Doctrine\ORM\Tools\Setup::createConfiguration(
            $doctrineMetaConfig['dev_mode'],
            $doctrineMetaConfig['proxy_dir'],
            $doctrineMetaConfig['cache']
        );
        $driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($doctrineMetaConfig['entity_path']);
        $doctrineConfig->setMetadataDriverImpl($driver);
        $em = EntityManager::create($appDoctrineConfig['connection'], $doctrineConfig);
        // $em->getConnection()->setAutoCommit(false);
        return $em;
    },
    SerializerInterface::class => function (ContainerInterface $container): SerializerInterface {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $env = $config->getString("environment");
        $serializerBuilder = SerializerBuilder::create()->setDebug($env === "development");
        if ($env === "production") {
            $serializerBuilder = $serializerBuilder->setCacheDir($config->getString('serializer.cache_dir'));
        }
        $serializerBuilder->setPropertyNamingStrategy(
            new \JMS\Serializer\Naming\SerializedNameAnnotationStrategy(
                new \JMS\Serializer\Naming\IdenticalPropertyNamingStrategy()
            )
        );
        $serializerBuilder->setSerializationContextFactory(
            function (): SerializationContext {
                return SerializationContext::create()->setGroups(['Default']);
            }
        );
        $serializerBuilder->setDeserializationContextFactory(
            function (): DeserializationContext {
                return DeserializationContext::create()->setGroups(['Default']);
            }
        );
        foreach ($config->getArray('serializer.yml_dir') as $ymlnamespace => $ymldir) {
            $serializerBuilder->addMetadataDir($ymldir, $ymlnamespace);
        }
        $dummyCreator = new DummyCreator();
        $serializerBuilder->configureHandlers(
            function (JMS\Serializer\Handler\HandlerRegistry $registry) use ($dummyCreator): void {
                (new HandlerSubscriber($dummyCreator))->subscribeHandlers($registry);
            }
        );
//            $serializerBuilder->configureListeners(function(JMS\Serializer\EventDispatcher\EventDispatcher $dispatcher) {
//                /*$dispatcher->addListener('serializer.pre_serialize',
//                    function(JMS\Serializer\EventDispatcher\PreSerializeEvent $event) {
//                        // do something
//                    }
//                );*/
//                //$dispatcher->addSubscriber(new RoundNumberEventSubscriber());
//                $dispatcher->addSubscriber(new RoundNumberEventSubscriber());
//            });
        $serializerBuilder->addDefaultHandlers();

        return $serializerBuilder->build();
    },
    Mailer::class => function (ContainerInterface $container): Mailer {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $smtpForDev = $config->getString("environment") === "development" ? $config->getArray("email.mailtrap") : null;
        return new Mailer(
            $container->get(LoggerInterface::class),
            $config->getString('email.from'),
            $config->getString('email.fromname'),
            $config->getString('email.admin'),
            $smtpForDev
        );
    },
    AuthSettings::class => function (ContainerInterface $container): AuthSettings {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $authSettings = $config->getArray('auth');
        return new AuthSettings(
            $authSettings['jwtsecret'],
            $authSettings['jwtalgorithm'],
            $authSettings['activationsecret']
        );
    }
];
