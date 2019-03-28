<?php
// DIC configuration

use \JMS\Serializer\SerializerBuilder;
use Voetbal\Structure\Repository as StructureRepository;

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

	$em = Doctrine\ORM\EntityManager::create($settings['connection'], $config);
    // $em->getConnection()->setAutoCommit(false);
    return $em;
};

// symfony serializer
$container['serializer'] = function( $c ) {
    // temporary, real one is set in middleware
    return SerializerBuilder::create()->build();
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
        $userRepos
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
        new StructureRepository($em),
        $c->get('voetbal')->getRepository(Voetbal\Game::class),
        $c->get('voetbal')->getService(Voetbal\Competitor::class),
        $c->get('serializer'),
        $c->get('token'),
        $em);
};
$container['App\Action\Tournament\Shell'] = function ($c) {
    $em = $c->get('em');
    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
    return new App\Action\Tournament\Shell(
        $tournamentRepos,
        $userRepository,
        $c->get('serializer'),
        $c->get('token'),
        $em);
};
$container['App\Action\Sponsor'] = function ($c) {
    $em = $c->get('em');
    $repos = new FCToernooi\Sponsor\Repository($em,$em->getClassMetaData(FCToernooi\Sponsor::class));
    $tournamentRepos = new FCToernooi\Tournament\Repository($em,$em->getClassMetaData(FCToernooi\Tournament::class));
    $userRepository = new FCToernooi\User\Repository($em,$em->getClassMetaData(FCToernooi\User::class));
    return new App\Action\Sponsor(
        $repos,
        $tournamentRepos,
        $userRepository,
        $c->get('serializer'),
        $c->get('token'));
};
