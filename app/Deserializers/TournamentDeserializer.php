<?php
declare(strict_types=1);

namespace App\Deserializers;

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

class TournamentDeserializer
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


    public function post( Tournament $tournamentSer, User $user ): Tournament
    {
        $competitionSer = $tournamentSer->getCompetition();

        $association = $this->createAssociationFromUserIdAndDateTime( $user->getId() );

        $leagueSer = $competitionSer->getLeague();
        $league = new League( $association, $leagueSer->getName() );
        $league->setSportDep( 'voetbal' ); // DEPRECATED

        $season = $this->seasonRepos->findOneBy( array('name' => '9999' ) );

        $ruleSet = $competitionSer->getRuleSet();
        $competitionService = new CompetitionService();
        $competition = $competitionService->create($league, $season, $ruleSet, $competitionSer->getStartDateTime() );

        // add serialized fields and referees to source-competition
        $sportConfigService = new SportConfigService();
        $createFieldsAndReferees = function($sportConfigsSer, $fieldsSer, $refereesSer) use( $competition, $sportConfigService ) {
            foreach( $sportConfigsSer as $sportConfigSer ) {
                $sport = $this->sportRepos->find( $sportConfigSer->getSportIdSer() );
                $sportConfigService->copy( $sportConfigSer, $competition, $sport );
            }
            foreach( $fieldsSer as $fieldSer ) {
                $field = new Field( $competition, $fieldSer->getNumber() );
                $field->setName( $fieldSer->getName() );
                $sport = $this->sportRepos->find( $fieldSer->getSportIdSer() );
                $field->setSport( $sport );
            }
            foreach( $refereesSer as $refereeSer ) {
                $referee = new Referee( $competition, $refereeSer->getRank() );
                $referee->setInitials( $refereeSer->getInitials() );
                $referee->setName( $refereeSer->getName() );
                $referee->setEmailaddress( $refereeSer->getEmailaddress() );
                $referee->setInfo( $refereeSer->getInfo() );
            }
        };
        $createFieldsAndReferees( $competitionSer->getSportConfigs(), $competitionSer->getFields(), $competitionSer->getReferees() );

        $tournament = new TournamentBase( $competition );
        $tournament->setBreakDuration( 0 );
        $public = $tournamentSer->getPublic() !== null ? $tournamentSer->getPublic() : true;
        $tournament->setPublic( $public );

        $roleService = new RoleService();
        $roleService->create( $tournament, $user, Role::ALL );

        return $tournament;
    }

    protected function createAssociationFromUserIdAndDateTime( $userId ): Association {
        $dateTime = new \DateTimeImmutable();
        return new Association($userId . '-' . $dateTime->getTimestamp());
    }
}
