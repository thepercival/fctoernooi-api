<?php
// DIC configuration

use \JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializationContext;

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// Doctrine
$container['em'] = function ($c) {
    $settings = $c->get('settings')['doctrine'];
	class CustomYamlDriver extends Doctrine\ORM\Mapping\Driver\YamlDriver
	{
		protected function loadMappingFile($file)
		{
			return Symfony\Component\Yaml\Yaml::parse(file_get_contents($file), Symfony\Component\Yaml\Yaml::PARSE_CONSTANT);
		}
	}

	$config = Doctrine\ORM\Tools\Setup::createConfiguration(
		$settings['meta']['auto_generate_proxies'],
		$settings['meta']['proxy_dir'],
		$settings['meta']['cache']
	);
	$config->setMetadataDriverImpl( new CustomYamlDriver( $settings['meta']['entity_path'] ));

	return Doctrine\ORM\EntityManager::create($settings['connection'], $config);
};

// symfony serializer
$container['serializer'] = function( $c ) {
    $settings = $c->get('settings');

    $serializerBuilder = SerializerBuilder::create()
        ->setDebug($settings['displayErrorDetails'])
        ->setSerializationContextFactory(function () {
            return SerializationContext::create()
                ->setGroups(array('Default'));
        });
        if( $settings["environment"] === "production") {
            $serializerBuilder = $serializerBuilder->setCacheDir($settings['serializer']['cache_dir']);
        }
    ;

    foreach( $settings['serializer']['yml_dir'] as $ymlnamespace => $ymldir ){
        $serializerBuilder->addMetadataDir($ymldir,$ymlnamespace);
    }


    return $serializerBuilder->build();
};

// voetbalService
$container['voetbal'] = function( $c ) {
    return new Voetbal\Service($c->get('em'));
};

// toernooiService
$container['toernooi'] = function( $c ) {
    $em = $c->get('em');
    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
    $roleRepos = new FCToernooi\Role\Repository($em,$em->getClassMetaData(FCToernooi\Role::class));
    $userRepos = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
    return new FCToernooi\Tournament\Service(
        $c->get('voetbal'),
        $tournamentRepos,
        $roleRepos,
        $userRepos,
        $em->getConnection()
    );
};

// JWT
$container["jwt"] = function ( $c ) {
    return new \StdClass;
};

// actions
$container['App\Action\Auth'] = function ($c) {
	$em = $c->get('em');
    $userRepos = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
    $service = new FCToernooi\Auth\Service( $userRepos, $c->get('toernooi'), $em->getConnection() );
	return new App\Action\Auth($service, $userRepos,$c->get('serializer'),$c->get('settings'));
};
$container['App\Action\User'] = function ($c) {
	$em = $c->get('em');
    $repos = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
	return new App\Action\User($repos,$c->get('serializer'),$c->get('settings'));
};
$container['App\Action\Tournament'] = function ($c) {
    $em = $c->get('em');
    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
    return new App\Action\Tournament(
        $c->get('toernooi'),
        $tournamentRepos,
        $userRepository,
        $c->get('voetbal')->getService(Voetbal\Structure::class),
        $c->get('voetbal')->getService(Voetbal\Planning::class),
        $c->get('serializer'),
        $c->get('token'));
};

$container['App\Action\Sponsor'] = function ($c) {
    $em = $c->get('em');
    $repos = new FCToernooi\Sponsor\Repository($em,$em->getClassMetaData(FCToernooi\Sponsor::class));
    $service = new FCToernooi\Sponsor\Service( $repos, $em->getConnection() );
    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
    return new App\Action\Sponsor(
        $service,
        $repos,
        $tournamentRepos,
        $userRepository,
        $c->get('serializer'),
        $c->get('token'));
};
