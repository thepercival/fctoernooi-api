<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-1-19
 * Time: 9:35
 */

namespace FCToernooi\Auth\JWT;

use Psr\Http\Message\ServerRequestInterface;
use Tuupola\Middleware\JwtAuthentication\RuleInterface;

/**
 * RequestMethodRule
 *
 * Rule to decide by HTTP verb whether the request should be authenticated or not.
 */
final class TournamentRule implements RuleInterface
{
    /**
     * Stores all the options passed to the rule.
     */
    private $options = [
        "ignore" => ["OPTIONS"]
    ];
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }
    public function __invoke(ServerRequestInterface $request): bool
    {
        // als get all die mag
        return !in_array($request->getMethod(), $this->options["ignore"]);
    }
}