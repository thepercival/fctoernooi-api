<?php
declare(strict_types=1);

namespace App\Copiers;

use Voetbal\Association;
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
use FCToernooi\Role;

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

    public function __construct(
        SportRepository $sportRepos,
        SeasonRepository $seasonRepos )
    {
        $this->sportRepos = $sportRepos;
        $this->seasonRepos = $seasonRepos;
    }


    public function copy( Tournament $tournament, \DateTimeImmutable $newStartDateTime, User $user ): Tournament
    {
        $competition = $tournament->getCompetition();

        $association = $this->createAssociationFromUserIdAndDateTime( $user->getId() );

        $leagueSer = $competition->getLeague();
        $league = new League( $association, $leagueSer->getName() );

        $season = $this->seasonRepos->findOneBy( array('name' => '9999' ) );

        $ruleSet = $competition->getRuleSet();
        $competitionService = new CompetitionService();
        $newCompetition = $competitionService->create($league, $season, $ruleSet, $competition->getStartDateTime() );

        // add serialized fields and referees to source-competition
        $sportConfigService = new SportConfigService();
        $createFieldsAndReferees = function($sportConfigsSer, $fieldsSer, $refereesSer) use( $newCompetition, $sportConfigService ) {
            foreach( $sportConfigsSer as $sportConfigSer ) {
                $sport = $this->sportRepos->find( $sportConfigSer->getSportIdSer() );
                $sportConfigService->copy( $sportConfigSer, $newCompetition, $sport );
            }
            foreach( $fieldsSer as $fieldSer ) {
                $field = new Field( $newCompetition, $fieldSer->getNumber() );
                $field->setName( $fieldSer->getName() );
                $sport = $this->sportRepos->find( $fieldSer->getSportIdSer() );
                $field->setSport( $sport );
            }
            foreach( $refereesSer as $refereeSer ) {
                $referee = new Referee( $newCompetition, $refereeSer->getRank() );
                $referee->setInitials( $refereeSer->getInitials() );
                $referee->setName( $refereeSer->getName() );
                $referee->setEmailaddress( $refereeSer->getEmailaddress() );
                $referee->setInfo( $refereeSer->getInfo() );
            }
        };
        $createFieldsAndReferees( $competition->getSportConfigs(), $competition->getFields(), $competition->getReferees() );

        $newTournament = new TournamentBase( $newCompetition );
        $newTournament->getCompetition()->setStartDateTime( $newStartDateTime );
        if( $tournament->getBreakStartDateTime() !== null ) {
            $diff = $tournament->getBreakStartDateTime()->diff( $tournament->getCompetition()->getStartDateTime() );
            $newTournament->setBreakStartDateTime( $newStartDateTime->add( $diff ) );
            $newTournament->setBreakDuration( $tournament->getBreakDuration() );
        }
        $public = $tournament->getPublic() !== null ? $tournament->getPublic() : true;
        $newTournament->setPublic( $public );

        $roleService = new RoleService();
        $roleService->create( $newTournament, $user, Role::ALL );

        return $newTournament;
    }

    protected function createAssociationFromUserIdAndDateTime( $userId ): Association {
        $dateTime = new \DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }
}
