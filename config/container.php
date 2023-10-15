<?php

declare(strict_types=1);

use App\Mailer;
use App\UTCDateTimeType;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

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
        if ($config->getString("environment") !== "development") {
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
        if ($config->getString("environment") !== "development") {
            $cache['cache'] = __DIR__ . '/../cache';
        }
        return TwigView::create(__DIR__ . '/../templates', $cache);
    },
    LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $name = 'application';
        $logger = new Logger($name);
        if ($config->getString('environment') === 'development') {
            $path = 'php://stdout';
        } else {
            $processor = new UidProcessor();
            $logger->pushProcessor($processor);
            $loggerPath = $config->getString('logger.path') . $name . '.log';
            $path = $loggerPath;
        }
        $loggerSettings = $config->getArray('logger');
        $handler = new StreamHandler($path, $loggerSettings['level']);
        $logger->pushHandler($handler);
        return $logger;
    },
    Memcached::class => function (ContainerInterface $container): Memcached {
        $memcached = new Memcached();
        $memcached->addServer('127.0.0.1', 11211);
        return $memcached;
    },
    EntityManagerInterface::class => function (ContainerInterface $container): EntityManagerInterface {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $doctrineAppConfig = $config->getArray('doctrine');
        /** @var array<string, string|bool|null> $doctrineMetaConfig */
        $doctrineMetaConfig = $doctrineAppConfig['meta'];
        /** @var bool $devMode */
        $devMode = $doctrineMetaConfig['dev_mode'];

        $docConfig = new \Doctrine\ORM\Configuration();
        if (!$devMode) {
            /** @var Memcached $memcached */
            $memcached = $container->get(Memcached::class);
            $cache = new MemcachedAdapter($memcached, $config->getString('namespace'));
            $docConfig->setQueryCache($cache);

            $docConfig->setMetadataCache($cache);
        }
        /** @var string $proxyDir */
        $proxyDir = $doctrineMetaConfig['proxy_dir'];
        $docConfig->setProxyDir($proxyDir);
        $docConfig->setProxyNamespace($config->getString('namespace'));

        /** @var list<string> $entityPath */
        $entityPath = $doctrineMetaConfig['entity_path'];
        $driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($entityPath);
        $docConfig->setMetadataDriverImpl($driver);

        $connection = DriverManager::getConnection($doctrineAppConfig['connection'], $docConfig, new EventManager());
        $em = new Doctrine\ORM\EntityManager($connection, $docConfig);

        Type::addType('enum_SelfReferee', SportsHelpers\SelfRefereeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_SelfReferee');
        Type::addType('enum_GameMode', SportsHelpers\GameModeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_GameMode');
        Type::addType('enum_AgainstSide', SportsHelpers\Against\SideType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_AgainstSide');
        Type::addType('enum_EditMode', Sports\Planning\EditModeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_EditMode');
        Type::addType('enum_QualifyTarget', Sports\Qualify\TargetType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_QualifyTarget');
        Type::addType('enum_Distribution', Sports\Qualify\DistributionType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_Distribution');
        Type::addType('enum_AgainstRuleSet', Sports\Ranking\AgainstRuleSetType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_AgainstRuleSet');
        Type::addType('enum_PointsCalculation', Sports\Ranking\PointsCalculationType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_PointsCalculation');
        Type::addType('enum_PlanningState', SportsPlanning\Planning\StateType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_PlanningState');
        Type::addType('enum_PlanningTimeoutState', SportsPlanning\Planning\TimeoutStateType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_PlanningTimeoutState');
        Type::addType('enum_GameState', Sports\Game\StateType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_GameState');
        Type::addType('enum_CreditAction', FCToernooi\CreditAction\NameType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_CreditAction');
        Type::addType('enum_StartEditMode', FCToernooi\Tournament\StartEditModeType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_StartEditMode');
        Type::addType('enum_PaymentState', FCToernooi\Payment\StateType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_PaymentState');
        Type::addType('enum_RegistrationState', FCToernooi\Tournament\Registration\StateType::class);
        $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_RegistrationState');

        Type::overrideType('datetime_immutable', UTCDateTimeType::class);
        return $em;
    },
    SerializerInterface::class => function (ContainerInterface $container): SerializerInterface {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $env = $config->getString("environment");
        $builder = SerializerBuilder::create()->setDebug($env === "development");
        if ($env !== "development") {
            $builder = $builder->setCacheDir($config->getString('serializer.cache_dir'));
        }
        $builder->setPropertyNamingStrategy(
            new \JMS\Serializer\Naming\SerializedNameAnnotationStrategy(
                new \JMS\Serializer\Naming\IdenticalPropertyNamingStrategy()
            )
        );
        $builder->setSerializationContextFactory(
            function (): SerializationContext {
                return SerializationContext::create()->setGroups(['Default']);
            }
        );
        $builder->setDeserializationContextFactory(
            function (): DeserializationContext {
                return DeserializationContext::create()->setGroups(['Default']);
            }
        );
        /** @var array<string, string> $ymlDirs */
        $ymlDirs = $config->getArray('serializer.yml_dir');
        foreach ($ymlDirs as $ymlnamespace => $ymldir) {
            $builder->addMetadataDir($ymldir, $ymlnamespace);
        }
        $dummyCreator = new DummyCreator();
        $builder->configureHandlers(
            function (JMS\Serializer\Handler\HandlerRegistry $registry) use ($dummyCreator): void {
                (new HandlerSubscriber($dummyCreator))->subscribeHandlers($registry);
            }
        );
        $builder->enableEnumSupport();


        //   $builder->configureListeners(function(JMS\Serializer\EventDispatcher\EventDispatcher $dispatcher) {

//                /*$dispatcher->addListener('serializer.pre_serialize',
//                    function(JMS\Serializer\EventDispatcher\PreSerializeEvent $event) {
//                        // do something
//                    }
//                );*/
//                //$dispatcher->addSubscriber(new RoundNumberEventSubscriber());
//                $dispatcher->addSubscriber(new RoundNumberEventSubscriber());
   //     });
        $builder = $builder->addDefaultHandlers();

        return $builder->build();
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
        return new AuthSettings(
            $config->getString('auth.jwtsecret'),
            $config->getString('auth.jwtalgorithm'),
            $config->getString('auth.activationsecret')
        );
    }
];
