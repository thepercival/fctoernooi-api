<?php
declare(strict_types=1);

namespace App\Copiers;

use DateTimeImmutable;
use Exception;
use FCToernooi\LockerRoom;
use Sports\Association;
use FCToernooi\Competitor;
use Sports\Sport;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competition\Sport\Service as CompetitionSportService;
use Sports\Sport\Repository as SportRepository;
use Sports\Season\Repository as SeasonRepository;
use Sports\League;
use FCToernooi\User;
use Sports\Competition\Service as CompetitionService;
use Sports\Competition\Field;
use Sports\Competition\Referee;
use FCToernooi\Tournament as TournamentBase;
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


    public function copy(Tournament $tournament, DateTimeImmutable $newStartDateTime, User $user): Tournament
    {
        $competition = $tournament->getCompetition();

        $association = $this->createAssociationFromUserIdAndDateTime($user->getId());

        $leagueSer = $competition->getLeague();
        $league = new League($association, $leagueSer->getName());

        $season = $this->seasonRepos->findOneBy(array('name' => '9999'));

        $ruleSet = $competition->getRankingRuleSet();
        $competitionService = new CompetitionService();
        $newCompetition = $competitionService->create($league, $season, $ruleSet, $competition->getStartDateTime());

        // add serialized fields and referees to source-competition
        $competitionSportService = new CompetitionSportService();
        /**
         * @param array|CompetitionSport[] $competitionSportsSer
         * @param array|Referee[] $refereesSer
         */
        $createFieldsAndReferees = function (array $competitionSportsSer, array $refereesSer) use (
            $newCompetition,
            $competitionSportService
        ): void {
            foreach ($competitionSportsSer as $competitionSportSer) {
                /** @var Sport $sport */
                $sport = $this->sportRepos->findOneBy(["name" => $competitionSportSer->getSport()->getName()]);
                $newCompetitionSport = $competitionSportService->copy($newCompetition, $sport);
                /** @var Field $fieldSer */
                foreach ($competitionSportSer->getFields() as $fieldSer) {
                    $field = new Field($newCompetitionSport, $fieldSer->getPriority());
                    $field->setName($fieldSer->getName());
                }
            }
            foreach ($refereesSer as $refereeSer) {
                $referee = new Referee($newCompetition, $refereeSer->getPriority());
                $referee->setInitials($refereeSer->getInitials());
                $referee->setName($refereeSer->getName());
                $referee->setEmailaddress($refereeSer->getEmailaddress());
                $referee->setInfo($refereeSer->getInfo());
            }
        };
        $createFieldsAndReferees(
            $competition->getSports()->toArray(),
            $competition->getReferees()->toArray()
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
            new TournamentUser($newTournament, $tournamentUser->getUser(), $tournamentUser->getRoles());
        }

        return $newTournament;
    }

    protected function createAssociationFromUserIdAndDateTime($userId): Association
    {
        $dateTime = new DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }

    /**
     * @param Tournament $sourceTournament
     * @param Tournament $newTournament
     * @param array|Competitor[] $newCompetitors
     * @throws Exception
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
