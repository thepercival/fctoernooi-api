<?php
declare(strict_types=1);

use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializerInterface;
use Psr\Container\ContainerInterface;
use FCToernooi\Token;
use Doctrine\ORM\EntityManager;

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;

use Voetbal\SerializationHandler\Round\NumberEvent as RoundNumberEventSubscriber;
use Voetbal\SerializationHandler\Round\Number as RoundNumberSerializationHandler;
use Voetbal\SerializationHandler\Structure as StructureSerializationHandler;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Voetbal\SerializationHandler\Round as RoundSerializationHandler;


return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        /*'renderer' => function ( ContainerInterface $container ) {
            $settings = $container->get('settings')['renderer'];
            return new Slim\Views\PhpRenderer($settings['template_path']);
        },*/
        LoggerInterface::class => function (ContainerInterface $container) {
            $settings = $container->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        EntityManager::class => function ( ContainerInterface $container ) {
            $settings = $container->get('settings')['doctrine'];

            $config = Doctrine\ORM\Tools\Setup::createConfiguration(
                $settings['meta']['dev_mode'],
                $settings['meta']['proxy_dir'],
                $settings['meta']['cache']
            );
            $driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver( $settings['meta']['entity_path'] );
            $config->setMetadataDriverImpl($driver);
            $em = Doctrine\ORM\EntityManager::create($settings['connection'], $config);
            // $em->getConnection()->setAutoCommit(false);
            return $em;
        },
        SerializerInterface::class => function( ContainerInterface $container ) {
            $settings = $container->get('settings');
            $serializerBuilder = SerializerBuilder::create()->setDebug($settings['displayErrorDetails']);
            if( $settings["environment"] === "production") {
                $serializerBuilder = $serializerBuilder->setCacheDir($settings['serializer']['cache_dir']);
            }
            $serializerBuilder->setPropertyNamingStrategy(new \JMS\Serializer\Naming\SerializedNameAnnotationStrategy(new \JMS\Serializer\Naming\IdenticalPropertyNamingStrategy()));

            $serializerBuilder->setSerializationContextFactory(function () {
                return SerializationContext::create()->setGroups(['Default']);
            });
            $serializerBuilder->setDeserializationContextFactory(function () {
                return DeserializationContext::create()->setGroups(['Default']);
            });
            foreach( $settings['serializer']['yml_dir'] as $ymlnamespace => $ymldir ){
                $serializerBuilder->addMetadataDir($ymldir,$ymlnamespace);
            }
            $serializerBuilder->configureHandlers(function(JMS\Serializer\Handler\HandlerRegistry $registry) {
                $registry->registerSubscribingHandler(new StructureSerializationHandler());
                $registry->registerSubscribingHandler(new RoundNumberSerializationHandler());
                $registry->registerSubscribingHandler(new RoundSerializationHandler());
//            $registry->registerSubscribingHandler(new QualifyGroupSerializationHandler());
            });
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
        Voetbal\Service::class => function( ContainerInterface $container ) {
            return new Voetbal\Service($container->get( EntityManager::class ));
        },
        'jwt' => function( ContainerInterface $container ) {
            return new \stdClass;
        },
        Token::class => function ( ContainerInterface $container ) {
            return new Token;
        }
    ]);
};

// should be loaded by callableresolver!!!

// actions
//'toernooi', function( ContainerInterface $container ) {
//    $em = $container->get('em');
//    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
//    $sportRepos = new Voetbal\Sport\Repository($em,$em->getClassMetaData(Voetbal\Sport::class));
//    $roleRepos = new FCToernooi\Role\Repository($em,$em->getClassMetaData(FCToernooi\Role::class));
//    $userRepos = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
//    return new FCToernooi\Tournament\Service(
//        $container->get('voetbal'),
//        $tournamentRepos,
//        $sportRepos,
//        $roleRepos,
//        $userRepos
//    );
//},

//$app->getContainer()->set('App\Action\Auth', function ( ContainerInterface $container ) {
//	$em = $container->get('em');
//    $userRepos = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
//    $roleRepos = new FCToernooi\Role\Repository($em,$em->getClassMetaData(FCToernooi\Role::class));
//    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
//    $service = new FCToernooi\Auth\Service(
//        $userRepos,
//        $roleRepos,
//        $tournamentRepos,
//        $em->getConnection()
//    );
//	return new App\Action\Auth($service, $userRepos,$container->get('serializer'),$container->get('settings'));
//});
//$app->getContainer()->set('App\Action\User', function ( ContainerInterface $container ) {
//	$em = $container->get('em');
//    $repos = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
//	return new App\Action\User($repos,$container->get('serializer'),$container->get('settings'));
//});
//$app->getContainer()->set('App\Action\Tournament', function ( ContainerInterface $container ) {
//    $em = $container->get('em');
//    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
//    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
//    return new App\Action\Tournament(
//        $container->get('toernooi'),
//        $tournamentRepos,
//        $userRepository,
//        new StructureRepository($em),
//        $container->get('voetbal')->getRepository(Voetbal\Game::class),
//        $container->get('serializer'),
//        $container->get('token'),
//        $em);
//});
//$app->getContainer()->set('App\Action\Tournament\Shell', function ( ContainerInterface $container ) {
//    $em = $container->get('em');
//    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
//    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
//    return new App\Action\Tournament\Shell(
//        $tournamentRepos,
//        $userRepository,
//        $container->get('serializer'),
//        $container->get('token'),
//        $em);
//});
//$app->getContainer()->set('App\Action\Sponsor', function ( ContainerInterface $container ) {
//    $em = $container->get('em');
//    $repos = new FCToernooi\Sponsor\Repository($em,$em->getClassMetaData(FCToernooi\Sponsor::class));
//    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
//    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
//    return new App\Action\Sponsor(
//        $repos,
//        $tournamentRepos,
//        $userRepository,
//        $container->get('serializer'),
//        $container->get('token'),
//        $container->get('settings'));
//});
//

