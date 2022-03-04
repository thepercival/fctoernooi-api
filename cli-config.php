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
Type::addType('enum_GameMode', SportsHelpers\GameModeType::class);
Type::addType('enum_AgainstSide', SportsHelpers\Against\SideType::class);
Type::addType('enum_EditMode', Sports\Planning\EditModeType::class);
Type::addType('enum_QualifyTarget', Sports\Qualify\TargetType::class);
Type::addType('enum_AgainstRuleSet', Sports\Ranking\AgainstRuleSetType::class);
Type::addType('enum_PointsCalculation', Sports\Ranking\PointsCalculationType::class);
Type::addType('enum_PlanningState', SportsPlanning\Planning\StateType::class);
Type::addType('enum_PlanningTimeoutState', SportsPlanning\Planning\TimeoutStateType::class);
Type::addType('enum_GameState', Sports\Game\StateType::class);

return ConsoleRunner::createHelperSet($em);
