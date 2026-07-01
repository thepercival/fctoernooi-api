<?php

declare(strict_types=1);

use App\UTCDateTimeType;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

require 'vendor/autoload.php';

$settings = include 'config/settings.php';
$settings = $settings['doctrine'];

$config = new \Doctrine\ORM\Configuration();
/** @var list<string> $entityPath */
$entityPath = $settings['meta']['entity_path'];
$driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($entityPath);
$config->setMetadataDriverImpl($driver);

$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);

$cache = new MemcachedAdapter($memcached);
$config->setQueryCache($cache);

$config->setMetadataCache($cache);

/** @var string $proxyDir */
$proxyDir = $settings['meta']['proxy_dir'];
$config->setProxyDir($proxyDir);
$config->setProxyNamespace('superelf');

$connection = DriverManager::getConnection($settings['connection'], $config);
$em = new Doctrine\ORM\EntityManager($connection, $config);

Type::addType('enum_AgainstSide', SportsHelpers\DbEnums\AgainstSideType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_AgainstSide');
Type::addType('enum_AgainstResult', SportsHelpers\DbEnums\AgainstResultType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_AgainstResult');
Type::addType('enum_GameMode', SportsHelpers\GameModeType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_GameMode');
Type::addType('enum_Distribution', Sports\DbEnums\QualifyDistributionType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_Distribution');
Type::addType('enum_SelfReferee', SportsHelpers\DbEnums\SelfRefereeType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_SelfReferee');
Type::addType('enum_EditMode', Sports\DbEnums\PlanningEditModeType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_EditMode');
Type::addType('enum_QualifyTarget', Sports\DbEnums\QualifyTargetType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_QualifyTarget');
Type::addType('enum_AgainstRuleSet', Sports\DbEnums\RankingAgainstRuleSetType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_AgainstRuleSet');

Type::addType('enum_PointsCalculation', Sports\DbEnums\RankingPointsCalculationType::class);
$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_PointsCalculation');

Type::addType('enum_PlanningState', SportsPlanning\Planning\StateType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_PlanningState');
Type::addType('enum_PlanningTimeoutState', SportsPlanning\Planning\TimeoutStateType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_PlanningTimeoutState');
Type::addType('enum_GameState', Sports\DbEnums\GameStateType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_RegistrationState');
Type::addType('enum_RegistrationState', FCToernooi\Database\enums\RegistrationStateType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_StartEditMode');
Type::addType('enum_StartEditMode', FCToernooi\Database\enums\StartEditModeType::class);

Type::addType('enum_CreditAction', FCToernooi\Database\enums\CreditActionNameEnumDbType::class);
$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_CreditAction');

Type::addType('enum_PaymentState', FCToernooi\Database\enums\PaymentStateType::class);
$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_PaymentState');

Type::overrideType('datetime_immutable', UTCDateTimeType::class);

//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_GameState');
//Type::addType('enum_BadgeCategory', \SuperElf\DbEnums\BadgeCategoryType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_BadgeCategory');
//Type::addType('enum_FootballLine', Sports\DbEnums\FootballLineType::class);
//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_FootballLine');

//$em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_BadgeCategory');

$commands = [
    // If you want to add your own custom console commands,
    // you can do so here.
];
ConsoleRunner::run(
    new SingleManagerProvider($em),
    $commands
);








