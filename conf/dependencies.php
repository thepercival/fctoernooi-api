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

use FCToernooi\Auth\Settings as AuthSettings;
use App\Settings\Www as WwwSettings;
use App\Settings\Image as ImageSettings;
use App\Mailer;

use Voetbal\SerializationHandler\Round\NumberEvent as RoundNumberEventSubscriber;
use Voetbal\SerializationHandler\Round\Number as RoundNumberSerializationHandler;
use Voetbal\SerializationHandler\Structure as StructureSerializationHandler;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use Voetbal\SerializationHandler\Round as RoundSerializationHandler;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions(
        [
            /*'renderer' => function ( ContainerInterface $container ) {
                $settings = $container->get('settings')['renderer'];
                return new Slim\Views\PhpRenderer($settings['template_path']);
            },*/
            LoggerInterface::class => function (ContainerInterface $container) {
                $settings = $container->get('settings');

                $loggerSettings = $settings['logger'];
                $name = "application";
                $logger = new Logger($name);

                $processor = new UidProcessor();
                $logger->pushProcessor($processor);

                $path = $settings["environment"] === "development" ? 'php://stdout' : ($loggerSettings['path'] . $name . '.log');

                $handler = new StreamHandler($path, $loggerSettings['level']);
                $logger->pushHandler($handler);

                return $logger;
            },
            EntityManager::class => function (ContainerInterface $container) {
                $settings = $container->get('settings')['doctrine'];

                $config = Doctrine\ORM\Tools\Setup::createConfiguration(
                    $settings['meta']['dev_mode'],
                    $settings['meta']['proxy_dir'],
                    $settings['meta']['cache']
                );
                $driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($settings['meta']['entity_path']);
                $config->setMetadataDriverImpl($driver);
                $em = Doctrine\ORM\EntityManager::create($settings['connection'], $config);
                // $em->getConnection()->setAutoCommit(false);
                return $em;
            },
            SerializerInterface::class => function (ContainerInterface $container) {
                $settings = $container->get('settings');
                $serializerBuilder = SerializerBuilder::create()->setDebug($settings['displayErrorDetails']);
                if ($settings["environment"] === "production") {
                    $serializerBuilder = $serializerBuilder->setCacheDir($settings['serializer']['cache_dir']);
                }
                $serializerBuilder->setPropertyNamingStrategy(
                    new \JMS\Serializer\Naming\SerializedNameAnnotationStrategy(
                        new \JMS\Serializer\Naming\IdenticalPropertyNamingStrategy()
                    )
                );

                $serializerBuilder->setSerializationContextFactory(
                    function () {
                        return SerializationContext::create()->setGroups(['Default']);
                    }
                );
                $serializerBuilder->setDeserializationContextFactory(
                    function () {
                        return DeserializationContext::create()->setGroups(['Default']);
                    }
                );
                foreach ($settings['serializer']['yml_dir'] as $ymlnamespace => $ymldir) {
                    $serializerBuilder->addMetadataDir($ymldir, $ymlnamespace);
                }
                $serializerBuilder->configureHandlers(
                    function (JMS\Serializer\Handler\HandlerRegistry $registry) {
                        $registry->registerSubscribingHandler(new StructureSerializationHandler());
                        $registry->registerSubscribingHandler(new RoundNumberSerializationHandler());
                        $registry->registerSubscribingHandler(new RoundSerializationHandler());
//            $registry->registerSubscribingHandler(new QualifyGroupSerializationHandler());
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
            Mailer::class => function (ContainerInterface $container) {
                $mailSettings = $container->get('settings')['email'];
                return new Mailer(
                    $container->get(LoggerInterface::class),
                    $mailSettings['from'],
                    $mailSettings['fromname'],
                    $mailSettings['admin']
                );
            },
            AuthSettings::class => function (ContainerInterface $container) {
                $authSettings = $container->get('settings')['auth'];
                return new AuthSettings(
                    $authSettings['jwtsecret'],
                    $authSettings['jwtalgorithm'],
                    $authSettings['activationsecret']
                );
            },
            WwwSettings::class => function (ContainerInterface $container) {
                return new WwwSettings($container->get('settings')['www']);
            },
            ImageSettings::class => function (ContainerInterface $container) {
                return new ImageSettings($container->get('settings')['images']);
            }/*,
        'jwt' => function( ContainerInterface $container ) {
            return new \stdClass;
        }*/
        ]
    );
};

