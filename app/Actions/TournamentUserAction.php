<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:23
 */

namespace App\Actions;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use Selective\Config\Configuration;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use FCToernooi\TournamentUser\Repository as TournamentUserRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\TournamentUser;
use FCToernooi\Tournament;
use FCToernooi\User;

final class TournamentUserAction extends Action
{
    /**
     * @var TournamentUserRepository
     */
    private $tournamentUserRepos;
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentUserRepository $tournamentUserRepos,
        TournamentRepository $tournamentRepos,
        Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->tournamentUserRepos = $tournamentUserRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->config = $config;
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var TournamentUser $tournamentUserSer */
            $tournamentUserSer = $this->serializer->deserialize(
                $this->getRawData(),
                'FCToernooi\TournamentUser',
                'json'
            );

            $tournamentUser = $this->tournamentUserRepos->find((int)$args['tournamentUserId']);
            if ($tournamentUser === null) {
                throw new \Exception("geen gebruiker met het opgegeven id gevonden", E_ERROR);
            }
            if ($tournamentUser->getTournament() !== $tournament) {
                throw new \Exception(
                    "je hebt geen rechten om een gebruiker van een ander toernooi aan te passen",
                    E_ERROR
                );
            }
            $tournamentUser->setRoles($tournamentUserSer->getRoles());
            $this->tournamentUserRepos->save($tournamentUser);

            $json = $this->serializer->serialize($tournamentUser, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
    {
        try {
            /** @var \FCToernooi\Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $tournamentUser = $this->tournamentUserRepos->find((int)$args['tournamentUserId']);
            if ($tournamentUser === null) {
                throw new \Exception("geen gebruiker met het opgegeven id gevonden", E_ERROR);
            }
            if ($tournamentUser->getTournament() !== $tournament) {
                throw new \Exception(
                    "je hebt geen rechten om een gebruiker van een ander toernooi te verwijderen",
                    E_ERROR
                );
            }

            $this->tournamentUserRepos->remove($tournamentUser);

            return $response->withStatus(200);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}
