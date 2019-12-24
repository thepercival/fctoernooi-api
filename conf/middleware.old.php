<?php

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use App\Response\ForbiddenResponse as ForbiddenResponse;

use Gofabian\Negotiation\NegotiationMiddleware;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\CorsMiddleware;
use App\Response\UnauthorizedResponse;
use App\Middleware\AuthenticationMiddleware;
use JMS\Serializer\SerializerBuilder;
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
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$app->addRoutingMiddleware();

$jwtAuthentication = function ( ContainerInterface $container) {
    return new JwtAuthentication([
        "secret" => $container->get('settings')['auth']['jwtsecret'],
        "logger" => $container->get("logger"),
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
            return new UnauthorizedResponse($message, 401);
        },
        "before" => function ($request, $arguments) use ($container) {
            $container->get("token")->populate($arguments["decoded"]);
        }
    ]);
};

$corsMiddleware = function (ContainerInterface $container) {
    return new CorsMiddleware([
        "logger" => $container->get("logger"),
        "origin" => $container->get('settings')['www']['urls'],
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
        "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since","Content-Type","X-Api-Version"],
        "headers.expose" => ["Authorization", "Etag"],
        "credentials" => true,
        "cache" => 300,
        "error" => function ($request, $response, $arguments) {
            return new UnauthorizedResponse($arguments["message"], 401);
        }
    ]);
};

$myAuthentication = function ( ContainerInterface $container) {
    return new AuthenticationMiddleware(
        $container->get('token'),
        new FCToernooi\User\Repository($container->get('em'),$container->get('em')->getClassMetaData(FCToernooi\User::class)),
        new FCToernooi\Tournament\Repository($container->get('em'),$container->get('em')->getClassMetaData(FCToernooi\Tournament::class)),
        $container->get('toernooi'),
        $container->get('voetbal')
    );
};

$app->add( $myAuthentication ); // needs executed after jwtauth for userid
$app->add( $negotiationMiddleware );
$app->add( $corsMiddleware );
$app->add( $negotiationMiddleware );

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




