<?php

declare(strict_types=1);

use App\Mailer;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use FCToernooi\Auth\Settings as AuthSettings;
use FCToernooi\SerializationHandler\Subscriber as HandlerSubscriber;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig as TwigView;
use Sports\SerializationHandler\DummyCreator;

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
            try {
                $routeCacheFile = $config->getString('router.cache_file');
                $app->getRouteCollector()->setCacheFile($routeCacheFile);
            } catch (Exception $e) {
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
        $name = 'application';
        $logger = new Logger($name);

        $processor = new UidProcessor();
        $logger->pushProcessor($processor);

        $loggerPath = $config->getString('logger.path') . $name . '.log';
        $path = $config->getString('environment') === 'development' ? 'php://stdout' : $loggerPath;

        $handler = new StreamHandler($path, $loggerSettings['level']);
        $logger->pushHandler($handler);

        return $logger;
    },
    EntityManager::class => function (ContainerInterface $container): EntityManager {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $doctrineAppConfig = $config->getArray('doctrine');
        /** @var array<string, string|bool|null> $doctrineMetaConfig */
        $doctrineMetaConfig = $doctrineAppConfig['meta'];
        /** @var bool $devMode */
        $devMode = $doctrineMetaConfig['dev_mode'];
        /** @var string|null $proxyDir */
        $proxyDir = $doctrineMetaConfig['proxy_dir'];
        /** @var Cache|null $cache */
        $cache = $doctrineMetaConfig['cache'];
        $doctrineConfig = Doctrine\ORM\Tools\Setup::createConfiguration($devMode, $proxyDir, $cache);
        /** @var list<string> $entityPath */
        $entityPath = $doctrineMetaConfig['entity_path'];
        $driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($entityPath);
        $doctrineConfig->setMetadataDriverImpl($driver);
        /** @var array<string, mixed> $connectionParams */
        $connectionParams = $doctrineAppConfig['connection'];
        $em = Doctrine\ORM\EntityManager::create($connectionParams, $doctrineConfig);
        // $em->getConnection()->setAutoCommit(false);

        Type::addType('enum_SelfReferee', SportsHelpers\SelfRefereeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_SelfReferee');
        Type::addType('enum_GameMode', SportsHelpers\GameModeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_GameMode');
        Type::addType('enum_AgainstSide', SportsHelpers\Against\SideType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_AgainstSide');
        Type::addType('enum_GamePlaceStrategy', SportsPlanning\Combinations\GamePlaceStrategyType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_GamePlaceStrategy');
        Type::addType('enum_EditMode', Sports\Planning\EditModeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_EditMode');
        Type::addType('enum_QualifyTarget', Sports\Qualify\TargetType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_QualifyTarget');
        Type::addType('enum_AgainstRuleSet', Sports\Ranking\AgainstRuleSetType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_AgainstRuleSet');
        Type::addType('enum_PointsCalculation', Sports\Ranking\PointsCalculationType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_PointsCalculation');
        Type::addType('enum_PlanningState', SportsPlanning\Planning\StateType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_PlanningState');

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
        /** @var array<string, string> $ymlDirs */
        $ymlDirs = $config->getArray('serializer.yml_dir');
        foreach ($ymlDirs as $ymlnamespace => $ymldir) {
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
        /** @var LoggerInterface $logger */
        $logger = $container->get(LoggerInterface::class);
        /** @var array<string, string|int>|null|null $smtpForDev */
        $smtpForDev = $config->getString("environment") === "development" ? $config->getArray("email.mailtrap") : null;
        return new Mailer(
            $logger,
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
            $config->getString('auth.jwtsecret'),
            $config->getString('auth.jwtalgorithm'),
            $config->getString('auth.activationsecret')
        );
    }
];
