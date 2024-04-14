<?php

declare(strict_types=1);

namespace App\Copiers;

use App\ImageService;
use DateTimeImmutable;
use Exception;
use FCToernooi\Competitor;
use FCToernooi\LockerRoom;
use FCToernooi\LockerRoom\Repository as LockerRoomRepository;
use FCToernooi\Recess;
use FCToernooi\Role;
use FCToernooi\Sponsor;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament;
use FCToernooi\Tournament\RegistrationSettings;
use FCToernooi\Tournament\RegistrationSettings\Repository as TournamentRegistrationSettingsRepository;
use FCToernooi\Competitor\Repository as TournamentCompetitorRepository;
use FCToernooi\TournamentUser;
use FCToernooi\User;
use League\Period\Period;
use Sports\Association;
use Sports\Competition;
use Sports\Competition\Field;
use Sports\Competition\Referee;
use Sports\Competition\Service as CompetitionService;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competitor\StartLocation;
use Sports\Competitor\StartLocationMap;
use Sports\League;
use Sports\Season\Repository as SeasonRepository;
use Sports\Sport;
use Sports\Sport\Repository as SportRepository;
use FCToernooi\Tournament\Rule as TournamentRule;
use FCToernooi\Tournament\Rule\Repository as TournamentRuleRepository;

use Sports\Structure;

class TournamentCopier
{
    public function __construct(
        private SportRepository $sportRepos,
        private SeasonRepository $seasonRepos,
        private LockerRoomRepository $lockerRoomRepos,
        private SponsorRepository $sponsorRepos,
        private TournamentRegistrationSettingsRepository $settingsRepos,
        private TournamentCompetitorRepository $competitorRepos,
        private TournamentRuleRepository $ruleRepos
    ) {
    }

    public function copy(Tournament $fromTournament, string|null $name, DateTimeImmutable $newStartDateTime, User $user): Tournament
    {
        $association = $this->createAssociationFromUserIdAndDateTime((int)$user->getId());

        $fromCompetition = $fromTournament->getCompetition();
        $leagueName = $name === null ? $fromCompetition->getLeague()->getName() : $name;
        $league = new League($association, $leagueName);

        $season = $this->seasonRepos->findOneBy(['name' => '9999']);
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
        $newTournament->setLocation($fromTournament->getLocation());

        if( $fromTournament->getExample() ) {
            new TournamentUser($newTournament, $user, Role::ALL - Role::REFEREE);
        } else {
            foreach ($fromTournament->getUsers() as $fromTournamentUser) {
                new TournamentUser($newTournament, $fromTournamentUser->getUser(), $fromTournamentUser->getRoles());
            }
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
                $competitionSportSer->getDefaultPointsCalculation(),
                $competitionSportSer->getDefaultWinPoints(),
                $competitionSportSer->getDefaultDrawPoints(),
                $competitionSportSer->getDefaultWinPointsExt(),
                $competitionSportSer->getDefaultDrawPointsExt(),
                $competitionSportSer->getDefaultLosePointsExt(),
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
        $newTournament = new Tournament($fromTournament->getIntro(), $newCompetition);
        $newTournament->getCompetition()->setStartDateTime($newStartDateTime);

        foreach( $fromTournament->getRecesses() as $fromRecess) {
            $start = $fromRecess->getStartDateTime();
            $diffStart = $fromTournament->getCompetition()->getStartDateTime()->diff($start);
            $end = $fromRecess->getEndDateTime();
            $diffEnd = $fromTournament->getCompetition()->getStartDateTime()->diff($end);
            $period = new Period(
                $newStartDateTime->add($diffStart), $newStartDateTime->add($diffEnd)
            );
            new Recess($newTournament, $fromRecess->getName(), $period);
        }

        $newTournament->setPublic($fromTournament->getPublic());

        return $newTournament;
    }

    public function copyAndSaveSettings(Tournament $fromTournament, Tournament $newTournament): void {
        $fromSettings = $this->settingsRepos->findOneBy(['tournament' => $fromTournament]);
        if ($fromSettings === null) {
            return;
        }

        $newSettings = new RegistrationSettings(
            $newTournament,
            $fromSettings->getEnabled(),
            $this->calculateNewEndDateTime($fromSettings, $newTournament),
            $fromSettings->getMailAlert(),
            $fromSettings->getRemark()
        );
        $this->settingsRepos->save($newSettings, true);
    }

    private function calculateNewEndDateTime(RegistrationSettings $fromSettings, Tournament $newTournament): DateTimeImmutable {
        $fromTournament = $fromSettings->getTournament();

        $fromTournamentStartDateTIme = $fromTournament->getCompetition()->getStartDateTime();
        $fromBetweenPeriod = new Period(
            $fromSettings->getEndDateTime(), $fromTournamentStartDateTIme
        );

        $newTournamentStartDateTIme = $newTournament->getCompetition()->getStartDateTime();

        return $newTournamentStartDateTIme->sub($fromBetweenPeriod->dateInterval());
    }


    public function copyAndSaveLockerRooms(Tournament $sourceTournament, Tournament $newTournament): void
    {
        $newStartLocationMap = new StartLocationMap( array_values($newTournament->getCompetitors()->toArray() ) );

        foreach ($sourceTournament->getLockerRooms() as $sourceLockerRoom) {
            $newLocerRoom = new LockerRoom($newTournament, $sourceLockerRoom->getName());
            foreach ($sourceLockerRoom->getCompetitors() as $sourceCompetitor) {

                $newCompetitor = $newStartLocationMap->getCompetitor($sourceCompetitor);
                if( !($newCompetitor instanceof Competitor)) {
                    continue;
                }
                $newLocerRoom->getCompetitors()->add($newCompetitor);
            }
            $this->lockerRoomRepos->save($newLocerRoom);
        }
    }

    public function copyAndSaveCompetitors(
        Tournament $fromTournament,
        Tournament $newTournament,
        Structure $newStructure,
        ImageService $imageService
    ): void
    {
        foreach ($fromTournament->getCompetitors() as $fromCompetitor) {
            if( !$newStructure->locationExists( $fromCompetitor ) ) {
                continue;
            }
            $newCompetitor = new Competitor(
                $newTournament, $fromCompetitor, $fromCompetitor->getName()
            );
            $newCompetitor->setEmailaddress($fromCompetitor->getEmailaddress());
            $newCompetitor->setTelephone($fromCompetitor->getTelephone());
            $newCompetitor->setInfo($fromCompetitor->getInfo());
            $newCompetitor->setLogoExtension($fromCompetitor->getLogoExtension());
            $this->competitorRepos->save($newCompetitor, true);
            if ($fromCompetitor->getLogoExtension() !== null) {
                $imageService->copyImages($fromCompetitor, $newCompetitor);
                // copy file
            }
        }
    }

    public function copyAndSaveSponsors(
        Tournament $fromTournament,
        Tournament $newTournament,
        ImageService $imageService
    ): void
    {
        foreach ($fromTournament->getSponsors() as $fromSponsor) {
            $newSponsor = new Sponsor(
                $newTournament, $fromSponsor->getName()
            );
            $newSponsor->setUrl($fromSponsor->getUrl());
            $newSponsor->setLogoExtension($fromSponsor->getLogoExtension());
            $newSponsor->setScreenNr($fromSponsor->getScreenNr());
            $this->sponsorRepos->save($newSponsor, true);
            if ($fromSponsor->getLogoExtension() !== null) {
                $imageService->copyImages($fromSponsor, $newSponsor);
                // copy file
            }
        }
    }

    public function copyAndSaveRules(Tournament $fromTournament, Tournament $newTournament): void
    {
        $fromRules = $this->ruleRepos->findBy(['tournament' => $fromTournament]);
        foreach( $fromRules as $fromRule) {
            $newRule = new TournamentRule($newTournament, $fromRule->getText() );
            $this->ruleRepos->save($newRule, true);
        }
    }

}
