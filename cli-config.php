<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\Console\ConsoleRunner;

require 'vendor/autoload.php';

$settings = include 'config/settings.php';
$settings = $settings['doctrine'];

$config = \Doctrine\ORM\Tools\Setup::createConfiguration(
    $settings['meta']['dev_mode'],
    $settings['meta']['proxy_dir'],
    $settings['meta']['cache']
);
$driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($settings['meta']['entity_path']);
$config->setMetadataDriverImpl($driver);

$em = \Doctrine\ORM\EntityManager::create($settings['connection'], $config);

Type::addType('enum_SelfReferee', SportsHelpers\SelfRefereeType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_SelfReferee');
Type::addType('enum_GameMode', SportsHelpers\GameModeType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_GameMode');
Type::addType('enum_AgainstSide', SportsHelpers\Against\SideType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_AgainstSide');
Type::addType('enum_GamePlaceStrategy', SportsPlanning\Combinations\GamePlaceStrategyType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_GamePlaceStrategy');
Type::addType('enum_EditMode', Sports\Planning\EditModeType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_EditMode');
Type::addType('enum_QualifyTarget', Sports\Qualify\TargetType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('string', 'enum_QualifyTarget');
Type::addType('enum_AgainstRuleSet', Sports\Ranking\AgainstRuleSetType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_AgainstRuleSet');
Type::addType('enum_PointsCalculation', Sports\Ranking\PointsCalculationType::class);
// $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('int', 'enum_PointsCalculation');

return ConsoleRunner::createHelperSet($em);
