<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Symfony\Component\Yaml\Yaml;

require 'vendor/autoload.php';

$settings = include 'conf/settings.php';
$settings = $settings['settings']['doctrine'];

$config = \Doctrine\ORM\Tools\Setup::createConfiguration(
	$settings['meta']['auto_generate_proxies'],
	$settings['meta']['proxy_dir'],
	$settings['meta']['cache']
);
$config->setMetadataDriverImpl( new App\YamlDriver( $settings['meta']['entity_path'] ));

$em = \Doctrine\ORM\EntityManager::create($settings['connection'], $config);

return ConsoleRunner::createHelperSet($em);