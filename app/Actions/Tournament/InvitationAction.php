<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 10-1-19
 * Time: 11:46
 */

namespace App\Actions\Tournament;

use Doctrine\ORM\EntityManager;
use Exception;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\Tournament;
use FCToernooi\TournamentUser;
use JMS\Serializer\SerializerInterface;
use App\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\Invitation\Repository as TournamentInvitationRepository;
use App\Actions\Action;
use Psr\Log\LoggerInterface;

final class InvitationAction extends Action
{
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var TournamentInvitationRepository
     */
    private $invitationRepos;
    /**
     * @var EntityManager
     */
    protected $em;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentRepository $tournamentRepos,
        TournamentInvitationRepository $invitationRepos,
        EntityManager $em
    ) {
        parent::__construct($logger, $serializer);
        $this->tournamentRepos = $tournamentRepos;
        $this->invitationRepos = $invitationRepos;
        $this->serializer = $serializer;
        $this->em = $em;
    }

    public function fetch(Request $request, Response $response, $args): Response
    {
        try {
            $tournament = $request->getAttribute("tournament");

            $invitations = $this->invitationRepos->findBy(["tournament" => $tournament]);

            $json = $this->serializer->serialize($invitations, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var TournamentInvitation $invitation */
            $invitationSer = $this->serializer->deserialize(
                $this->getRawData(),
                'FCToernooi\Tournament\Invitation',
                'json'
            );

            $tournamentUser = $this->findTournamentUserByEmailaddress($tournament, $invitationSer->getEmailaddress());
            if ($tournamentUser !== null) {
                throw new Exception("dit emailadres heeft al een gebruiker voor dit toernooi", E_ERROR);
            }

            $invitation = $this->invitationRepos->findOneBy(
                [
                    "tournament" => $tournament,
                    "emailaddress" => $invitationSer->getEmailaddress()
                ]
            );
            if ($invitation !== null) {
                $invitation->setRoles($invitationSer->getRoles());
            } else {
                $invitation = new TournamentInvitation(
                    $tournament,
                    $invitationSer->getEmailaddress(),
                    $invitationSer->getRoles()
                );
            }
            $this->invitationRepos->save($invitation);
            // @TODO SEND EMAIL

            $json = $this->serializer->serialize($invitation, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function findTournamentUserByEmailaddress(Tournament $tournament, string $emailaddress): ?TournamentUser
    {
        foreach ($tournament->getUsers() as $tournamentUser) {
            if ($tournamentUser->getUser()->getEmailaddress() === $emailaddress) {
                return $tournamentUser;
            }
        }
        return null;
    }
}
