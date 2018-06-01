<?php

use FCToernooi\Token;
// use Crell\ApiProblem\ApiProblem;
use Gofabian\Negotiation\NegotiationMiddleware;
// use Micheh\Cache\CacheUtil;
use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\CorsMiddleware;
use App\Response\Unauthorized;
use App\Middleware\Authentication;

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
            new Tuupola\Middleware\JwtAuthentication\RequestPathRule([
                "path" => "/",
                "ignore" => ["/auth/register", "/auth/login","/auth/passwordreset","/auth/passwordchange"]
            ]),
            new JwtAuthentication\RequestMethodRule([
                "path" => "/tournaments",
                "ignore" => ["GET"]
            ]),
            new Tuupola\Middleware\JwtAuthentication\RequestMethodRule([
                "ignore" => ["OPTIONS"]
            ])
        ],
        "error" => function ($response, $arguments) {
            return new Unauthorized($arguments["message"], 401);
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
        "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since","content-type"],
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

$container["cache"] = function ($container) {
    return new CacheUtil;
};

