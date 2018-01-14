<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:29
 */

namespace App\Action\Tournament;

use Slim\ServerRequestInterface;
use JMS\Serializer\Serializer;
use FCToernooi\Tournament\Role\Repository as TournamentRoleRepository;

final class Role
{
    /**
     * @var TournamentRoleRepository
     */
    private $tournamentRoleRepository;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var array
     */
    protected $settings;

    public function __construct(TournamentRoleRepository $tournamentRoleRepository, Serializer $serializer, $settings )
    {
        $this->tournamentRoleRepository = $tournamentRoleRepository;
        // $this->authService = new Auth\Service($userRepository);
        $this->serializer = $serializer;
        $this->settings = $settings;
    }

    public function fetch($request, $response, $args)
    {
        $users = $this->tournamentRoleRepository->findAll();
        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->write($this->serializer->serialize( $users, 'json'));
        ;
    }

    public function fetchOne($request, $response, $args)
    {
        $tournamentRole = $this->tournamentRoleRepository->find($args['id']);
        if ($tournamentRole) {
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize( $tournamentRole, 'json'));
            ;
        }
        return $response->withStatus(404, 'geen toernooirol met het opgegeven id gevonden');
    }
}