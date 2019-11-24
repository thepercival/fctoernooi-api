<?php

use App\Response\Forbidden as ForbiddenResponse;
use FCToernooi\Token;
use Gofabian\Negotiation\NegotiationMiddleware;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\CorsMiddleware;
use App\Response\Unauthorized;
use App\Middleware\Authentication;
use \JMS\Serializer\SerializerBuilder;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use FCToernooi\Auth\JWT\TournamentRule;
use Voetbal\SerializationHandler\Round as RoundSerializationHandler;
// use Voetbal\SerializationSubscriberEvent\Round\Number as RoundNumberEventSubscriber;
use Voetbal\SerializationHandler\Qualify\Group as QualifyGroupSerializationHandler;

use Voetbal\SerializationHandler\Round\NumberEvent as RoundNumberEventSubscriber;
use Voetbal\SerializationHandler\Round\Number as RoundNumberSerializationHandler;

// use Voetbal\SerializationHandler\Config as ConfigSerializationHandler;
use Voetbal\SerializationHandler\Structure as StructureSerializationHandler;

$container = $app->getContainer();
$container["token"] = function ($container) {
    return new Token;
};

$container["JwtAuthentication"] = function ($container) {
    return new JwtAuthentication([
        "secret" => $container->get('settings')['auth']['jwtsecret'],
        "logger" => $container["logger"],
        "attribute" => false,
        "rules" => [
            new JwtAuthentication\RequestPathRule([
                "path" => "/",
                "ignore" => [
                    "/auth/register", "/auth/login","/auth/passwordreset","/auth/passwordchange",
                    "/tournamentshells", "/tournamentspublic", "/tournaments/export",
                    "/voetbal/structures", "/voetbal/sports"
                ]
            ]),
            new JwtAuthentication\RequestMethodRule([
                "ignore" => ["OPTIONS"]
            ])
        ],
        "error" => function ($response, $arguments) {
            $message = $arguments["message"];
            if( $message === "Expired token" ) {
              $message = "token is niet meer geldig, log opnieuw in";
            }
            return new Unauthorized($message, 401);
        },
        "before" => function ($request, $arguments) use ($container) {
            $container["token"]->populate($arguments["decoded"]);
        }
    ]);
};

$container["CorsMiddleware"] = function ($container) {
    return new CorsMiddleware([
        "logger" => $container["logger"],
        "origin" => $container->get('settings')['www']['urls'],
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
        "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since","Content-Type","X-Api-Version"],
        "headers.expose" => ["Authorization", "Etag"],
        "credentials" => true,
        "cache" => 300,
        "error" => function ($request, $response, $arguments) {
            return new Unauthorized($arguments["message"], 401);
        }
    ]);
};
$container["NegotiationMiddleware"] = function ($container) {
    return new NegotiationMiddleware([
        "accept" => ["application/json"]
    ]);
};

$container["MyAuthentication"] = function ($container) {
    return new Authentication(
        $container->get('token'),
        new FCToernooi\User\Repository($container->get('em'),$container->get('em')->getClassMetaData(FCToernooi\User::class)),
        new FCToernooi\Tournament\Repository($container->get('em'),$container->get('em')->getClassMetaData(FCToernooi\Tournament::class)),
        $container->get('toernooi'),
        $container->get('voetbal')
    );
};

$app->add("MyAuthentication"); // needs executed after jwtauth for userid
$app->add("JwtAuthentication");
$app->add("CorsMiddleware");
$app->add("NegotiationMiddleware");

$app->add(function ( $request,  $response, callable $next) use ( $container ){
    $apiVersion = $request->getHeaderLine('HTTP_X_API_VERSION');
    if( ($request->getMethod() === "POST" && $request->getUri()->getPath() === "/validatetoken" )
    || ($request->getMethod() === "GET" && $request->getUri()->getPath() === "/tournamentshells") ) {
        if( $apiVersion !== "17" ) {
            // return $response->withStatus(422)->write( "de app/website moet vernieuwd worden, ververs de pagina");
            return new ForbiddenResponse("de app/website moet vernieuwd worden, ververs de pagina", 418);
        }
    }
    return $next($request, $response);
});

$app->add(function ( $request,  $response, callable $next) use ( $container ){
    $apiVersion = $request->getHeaderLine('HTTP_X_API_VERSION');
    if( strlen( $apiVersion ) === 0 ) {
        $apiVersion = "1";
    }

    $container['serializer'] = function() use ($container, $apiVersion) {
        $settings = $container['settings'];
        $serializerBuilder = SerializerBuilder::create()->setDebug($settings['displayErrorDetails']);
        if( $settings["environment"] === "production") {
            $serializerBuilder = $serializerBuilder->setCacheDir($settings['serializer']['cache_dir']);
        }
        $serializerBuilder->setPropertyNamingStrategy(new \JMS\Serializer\Naming\SerializedNameAnnotationStrategy(new \JMS\Serializer\Naming\IdenticalPropertyNamingStrategy()));

        $serializerBuilder->setSerializationContextFactory(function () use ($apiVersion) {
            return SerializationContext::create()
                ->setGroups(array('Default'))
                ->setVersion($apiVersion);
        });
        $serializerBuilder->setDeserializationContextFactory(function () use ($apiVersion) {
            return DeserializationContext::create()
                ->setGroups(array('Default'))
                ->setVersion($apiVersion);
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
        $serializerBuilder->configureListeners(function(JMS\Serializer\EventDispatcher\EventDispatcher $dispatcher) {
            /*$dispatcher->addListener('serializer.pre_serialize',
                function(JMS\Serializer\EventDispatcher\PreSerializeEvent $event) {
                    // do something
                }
            );*/
            //$dispatcher->addSubscriber(new RoundNumberEventSubscriber());
            $dispatcher->addSubscriber(new RoundNumberEventSubscriber());
        });
        $serializerBuilder->addDefaultHandlers();

        return $serializerBuilder->build();
    };

    $response = $next($request, $response);
    header_remove("X-Powered-By");
    return $response;
});

//$container["cache"] = function ($container) {
//    return new CacheUtil;
//};



