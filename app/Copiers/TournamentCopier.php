<?php
declare(strict_types=1);

namespace App\Copiers;

use DateTimeImmutable;
use Exception;
use FCToernooi\LockerRoom;
use Sports\Association;
use FCToernooi\Competitor;
use Sports\Competition;
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
    public function __construct(
        private SportRepository $sportRepos,
        private SeasonRepository $seasonRepos,
        private LockerRoomRepository $lockerRoomRepos
    ) {
    }

    public function copy(Tournament $fromTournament, DateTimeImmutable $newStartDateTime, User $user): Tournament
    {
        $association = $this->createAssociationFromUserIdAndDateTime((int)$user->getId());

        $fromCompetition = $fromTournament->getCompetition();
        $fromLeague = $fromCompetition->getLeague();
        $league = new League($association, $fromLeague->getName());

        $season = $this->seasonRepos->findOneBy(array('name' => '9999'));
        if ($season === null) {
            throw new \Exception('season 9999 not found', E_ERROR);
        }

        $newCompetition = (new CompetitionService())->create(
            $league,
            $season,
            $fromCompetition->getAgainstRuleSet(),
            $fromCompetition->getStartDateTime()
        );

        $this->copyFieldsAndReferees(
            $newCompetition,
            $fromCompetition->getSports()->toArray(),
            $fromCompetition->getReferees()->toArray()
        );

        $newTournament = $this->createTournament($fromTournament, $newCompetition, $newStartDateTime);

        foreach ($fromTournament->getUsers() as $fromTournamentUser) {
            new TournamentUser($newTournament, $fromTournamentUser->getUser(), $fromTournamentUser->getRoles());
        }

        return $newTournament;
    }

    protected function createAssociationFromUserIdAndDateTime(string|int $userId): Association
    {
        $dateTime = new DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }

    // add serialized fields and referees to source-competition

    /**
     * @param Competition $newCompetition
     * @param array<CompetitionSport> $compSportsSer
     * @param array<Referee> $refereesSer
     */
    protected function copyFieldsAndReferees(
        Competition $newCompetition,
        array $compSportsSer,
        array $refereesSer
    ): void {
        foreach ($compSportsSer as $competitionSportSer) {
            /** @var Sport $sport */
            $sport = $this->sportRepos->findOneBy(["name" => $competitionSportSer->getSport()->getName()]);
            $newCompetitionSport = new CompetitionSport(
                $sport,
                $newCompetition,
                $competitionSportSer
            );
            /** @var Field $fieldSer */
            foreach ($competitionSportSer->getFields() as $fieldSer) {
                $field = new Field($newCompetitionSport, $fieldSer->getPriority());
                $field->setName($fieldSer->getName());
            }
        }
        foreach ($refereesSer as $refereeSer) {
            $referee = new Referee($newCompetition, $refereeSer->getInitials(), $refereeSer->getPriority());
            $referee->setName($refereeSer->getName());
            $referee->setEmailaddress($refereeSer->getEmailaddress());
            $referee->setInfo($refereeSer->getInfo());
        }
    }

    protected function createTournament(
        Tournament $fromTournament,
        Competition $newCompetition,
        DateTimeImmutable $newStartDateTime
    ): Tournament {
        $newTournament = new TournamentBase($newCompetition);
        $newTournament->getCompetition()->setStartDateTime($newStartDateTime);
        $breakStart = $fromTournament->getBreakStartDateTime();
        $breakEnd = $fromTournament->getBreakEndDateTime();
        if ($breakStart !== null && $breakEnd !== null) {
            $diffStart = $fromTournament->getCompetition()->getStartDateTime()->diff($breakStart);
            $newTournament->setBreakStartDateTime($newStartDateTime->add($diffStart));
            $diffEnd = $fromTournament->getCompetition()->getStartDateTime()->diff($breakEnd);
            $newTournament->setBreakEndDateTime($newStartDateTime->add($diffEnd));
        }
        $newTournament->setPublic($fromTournament->getPublic());
        return $newTournament;
    }

    /**
     * @param Tournament $sourceTournament
     * @param Tournament $newTournament
     * @param list<Competitor> $newCompetitors
     * @throws Exception
     */
    public function copyLockerRooms(Tournament $sourceTournament, Tournament $newTournament, array $newCompetitors): void
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
