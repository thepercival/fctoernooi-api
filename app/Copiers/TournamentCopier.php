<?php
declare(strict_types=1);

namespace App\Copiers;

use FCToernooi\LockerRoom;
use FCToernooi\Role;
use Voetbal\Association;
use Voetbal\Competitor;
use Voetbal\Sport;
use Voetbal\Sport\Repository as SportRepository;
use Voetbal\Season\Repository as SeasonRepository;
use Voetbal\League;
use FCToernooi\User;
use Voetbal\Competition\Service as CompetitionService;
use Voetbal\Field;
use Voetbal\Referee;
use Voetbal\Sport\Config\Service as SportConfigService;
use FCToernooi\Tournament as TournamentBase;
use FCToernooi\Role\Service as RoleService;
use FCToernooi\Tournament;
use FCToernooi\LockerRoom\Repository as LockerRoomRepository;
use FCToernooi\TournamentUser;

class TournamentCopier
{
    /**
     * @var SportRepository
     */
    protected $sportRepos;
    /**
     * @var SeasonRepository
     */
    protected $seasonRepos;
    /**
     * @var LockerRoomRepository
     */
    protected $lockerRoomRepos;

    public function __construct(
        SportRepository $sportRepos,
        SeasonRepository $seasonRepos,
        LockerRoomRepository $lockerRoomRepos
    ) {
        $this->sportRepos = $sportRepos;
        $this->seasonRepos = $seasonRepos;
        $this->lockerRoomRepos = $lockerRoomRepos;
    }


    public function copy(Tournament $tournament, \DateTimeImmutable $newStartDateTime, User $user): Tournament
    {
        $competition = $tournament->getCompetition();

        $association = $this->createAssociationFromUserIdAndDateTime($user->getId());

        $leagueSer = $competition->getLeague();
        $league = new League($association, $leagueSer->getName());

        $season = $this->seasonRepos->findOneBy(array('name' => '9999'));

        $ruleSet = $competition->getRuleSet();
        $competitionService = new CompetitionService();
        $newCompetition = $competitionService->create($league, $season, $ruleSet, $competition->getStartDateTime());

        // add serialized fields and referees to source-competition
        $sportConfigService = new SportConfigService();
        $createFieldsAndReferees = function ($sportConfigsSer, $refereesSer) use (
            $newCompetition,
            $sportConfigService
        ): void {
            foreach ($sportConfigsSer as $sportConfigSer) {
                /** @var Sport $sport */
                $sport = $this->sportRepos->findOneBy(["name" => $sportConfigSer->getSport()->getName()]);
                $sportConfig = $sportConfigService->copy($sportConfigSer, $newCompetition, $sport);
                /** @var Field $fieldSer */
                foreach ($sportConfigSer->getFields() as $fieldSer) {
                    $field = new Field($sportConfig, $fieldSer->getPriority());
                    $field->setName($fieldSer->getName());
                }
            }

            /** @var Referee $refereeSer */
            foreach ($refereesSer as $refereeSer) {
                $referee = new Referee($newCompetition, $refereeSer->getPriority());
                $referee->setInitials($refereeSer->getInitials());
                $referee->setName($refereeSer->getName());
                $referee->setEmailaddress($refereeSer->getEmailaddress());
                $referee->setInfo($refereeSer->getInfo());
            }
        };
        $createFieldsAndReferees(
            $competition->getSportConfigs(),
            $competition->getReferees()
        );

        $newTournament = new TournamentBase($newCompetition);
        $newTournament->getCompetition()->setStartDateTime($newStartDateTime);
        if ($tournament->getBreakStartDateTime() !== null) {
            $diffStart = $tournament->getCompetition()->getStartDateTime()->diff($tournament->getBreakStartDateTime());
            $newTournament->setBreakStartDateTime($newStartDateTime->add($diffStart));
            $diffEnd = $tournament->getCompetition()->getStartDateTime()->diff($tournament->getBreakEndDateTime());
            $newTournament->setBreakEndDateTime($newStartDateTime->add($diffEnd));
        }
        $public = $tournament->getPublic() !== null ? $tournament->getPublic() : true;
        $newTournament->setPublic($public);

        foreach ($tournament->getUsers() as $tournamentUser) {
            $tmp = new TournamentUser($newTournament, $tournamentUser->getUser(), $tournamentUser->getRoles());
        }

        return $newTournament;
    }

    protected function createAssociationFromUserIdAndDateTime($userId): Association
    {
        $dateTime = new \DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }

    /**
     * @param Tournament $sourceTournament
     * @param Tournament $newTournament
     * @param array|Competitor[] $newCompetitors
     */
    public function copyLockerRooms(Tournament $sourceTournament, Tournament $newTournament, array $newCompetitors)
    {
        foreach ($sourceTournament->getLockerRooms() as $sourceLockerRoom) {
            $newLocerRoom = new LockerRoom($newTournament, $sourceLockerRoom->getName());
            foreach ($sourceLockerRoom->getCompetitors() as $sourceCompetitor) {
                $newCompetitorsFound = array_filter(
                    $newCompetitors,
                    function ($newCompetitorIt) use ($sourceCompetitor): bool {
                        return $newCompetitorIt->getName() === $sourceCompetitor->getName();
                    }
                );
                if (count($newCompetitorsFound) !== 1) {
                    continue;
                }
                $newLocerRoom->getCompetitors()->add(reset($newCompetitorsFound));
            }
            $this->lockerRoomRepos->save($newLocerRoom);
        }
    }
}
