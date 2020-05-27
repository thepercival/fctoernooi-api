<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 1-10-17
 * Time: 12:10
 */

namespace FCToernooi\TournamentUser;

use Doctrine\Common\Collections\ArrayCollection;
use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Invitation as TournamentInvitation;
use FCToernooi\User;
use FCToernooi\TournamentUser;
use FCToernooi\User\Repository as UserRepository;

class Repository extends \Voetbal\Repository
{
//    public function syncRefereeRoles(Tournament $tournament): array
//    {
//        // @TODO kijk waar in de frontend dit wordt aangeroepen!!
//        // naar een andere plek hoogst waarschijnlijk
//        $conn = $this->_em->getConnection();
//        $conn->beginTransaction();
//        try {
//            // remove referee roles
//            {
//                $params = ['value' => Role::REFEREE, 'tournament' => $tournament];
//                $refereeRoles = $this->findBy($params);
//                foreach ($refereeRoles as $refereeRole) {
//                    $this->_em->remove($refereeRole);
//                }
//            }
//            $this->_em->flush();
//
//            // add referee roles
//            $userRepos = new UserRepository($this->_em, $this->_em->getClassMetadata(User::class));
//            $referees = $tournament->getCompetition()->getReferees();
//            foreach ($referees as $referee) {
//                if (strlen($referee->getEmailaddress()) === 0) {
//                    continue;
//                }
//                /** @var User|null $user */
//                $user = $userRepos->findOneBy(['emailaddress' => $referee->getEmailaddress()]);
//                if ($user === null) {
//                    continue;
//                }
//                $refereeRole = new TournamentUser($tournament, $user, Role::REFEREE);
//                $this->_em->persist($refereeRole);
//            }
//            $tournamentUsers = array_values($tournament->getUsers()->toArray());
//
//            $this->_em->flush();
//            $conn->commit();
//            return $tournamentUsers;
//        } catch (\Exception $e) {
//            $conn->rollBack();
//            throw $e;
//        }
//    }

    /**
     * @param array | TournamentInvitation[] $invitations
     * @return array | TournamentUser[]
     */
    public function processInvitations(array $invitations): array
    {
        // @TODO
        return [];
    }
}
