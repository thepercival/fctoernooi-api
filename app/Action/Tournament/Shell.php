<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-1-19
 * Time: 11:46
 */

namespace App\Action\Tournament;

use Doctrine\ORM\EntityManager;
use JMS\Serializer\Serializer;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Role;
use FCToernooi\User;
use FCToernooi\Token;
use FCToernooi\Tournament\Shell as TournamentShell;

final class Shell
{
    /**
     * @var TournamentRepository
     */
    private $repos;

    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var Serializer
     */
    protected $serializer;
    /**
     * @var Token
     */
    protected $token;
    /**
     * @var EntityManager
     */
    protected $em;

    public function __construct(
        TournamentRepository $repos,
        UserRepository $userRepository,
        Serializer $serializer,
        Token $token,
        EntityManager $em
    ) {
        $this->repos = $repos;
        $this->userRepository = $userRepository;
        $this->serializer = $serializer;
        $this->token = $token;
        $this->em = $em;
    }

    public function fetch($request, $response, $args)
    {
        return $this->fetchHelper($request, $response, $args, true);
    }

    public function fetchWithRoles($request, $response, $args)
    {
        $user = null;
        if ( $this->token->isPopulated() ) {
            $user = $this->userRepository->find($this->token->getUserId());
        }
        return $this->fetchHelper($request, $response, $args, null, $user);
    }

    public function fetchHelper($request, $response, $args, bool $public = null, User $user = null)
    {
        $sErrorMessage = null;
        try {
            $name = null;
            if (strlen($request->getParam('name')) > 0) {
                $name = $request->getParam('name');
            }

            $startDateTime = null;
            if (strlen($request->getParam('startDate')) > 0) {
                $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z',
                    $request->getParam('startDate'));
            }
            if ($startDateTime === false) {
                $startDateTime = null;
            }

            $endDateTime = null;
            if (strlen($request->getParam('endDate')) > 0) {
                $endDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $request->getParam('endDate'));
            }
            if ($endDateTime === false) {
                $endDateTime = null;
            }

            $withRoles = null;
            if (strlen($request->getParam('withRoles')) > 0) {
                $withRoles = $request->getParam('withRoles') === 'true';
            }

            $shells = [];
            {
                if ($user !== null && $withRoles === true) {
                    $tournamentsByRole = $this->repos->findByPermissions($user, Role::ADMIN);
                    foreach ($tournamentsByRole as $tournament) {
                        $shells[] = new TournamentShell($tournament, $user);
                    }
                } else {
                    $tournamentsByDates = $this->repos->findByFilter($name, $startDateTime, $endDateTime, $public);
                    foreach ($tournamentsByDates as $tournament) {
                        $shells[] = new TournamentShell($tournament, $user);
                    }
                }
            }
            return $response
                ->withHeader('Content-Type', 'application/json;charset=utf-8')
                ->write($this->serializer->serialize($shells, 'json'));;
        } catch (\Exception $e) {
            $sErrorMessage = $e->getMessage();
        }
        return $response->withStatus(422)->write($sErrorMessage);
    }
}