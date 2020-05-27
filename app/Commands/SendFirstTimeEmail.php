<?php

namespace App\Commands;

use FCToernooi\Role;
use FCToernooi\TournamentUser;
use FCToernooi\Tournament;
use FCToernooi\User;
use Voetbal\Structure;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use App\Command;
use Selective\Config\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Mailer;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\User\Repository as UserRepository;
use Voetbal\Structure\Repository as StructureRepository;
use Voetbal\Structure\Service as StructureService;

class SendFirstTimeEmail extends Command
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var UserRepository
     */
    protected $userRepos;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->userRepos = $container->get(UserRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        parent::__construct($container->get(Configuration::class));
    }

    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:send-firsttime-email')
            // the short description shown while running "php bin/console list"
            ->setDescription('Sends the first-time-email')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('Sends the first-time-email');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initLogger($input, 'cron-send-firsttime-email');
        $this->initMailer($this->logger);
        try {
            $users = $this->userRepos->findAll();
            foreach ($users as $user) {
                if ($user->getHelpSent() === true) {
                    continue;
                }
                $tournaments = $this->tournamentRepos->findByRoles($user, Role::ADMIN);
                if (count($tournaments) === 0) {
                    continue;
                }

                $this->sendMail($user, reset($tournaments));
                $user->setHelpSent(true);
                $this->userRepos->save($user);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return 0;
    }

    protected function sendMail(User $user, Tournament $tournament)
    {
        $subject = $tournament->getCompetition()->getLeague()->getName();
        $url = $this->config->getString("www.wwwurl");
        $body = <<<EOT
<p>Hallo,</p>
<p>            
Als beheerder van <a href="$url">$url</a> zag ik dat je een toernooi hebt aangemaakt op onze website. 
Mocht je vragen hebben dan horen we dat graag. Beantwoord dan deze email of bel me.        
</p>
<p>            
Veel plezier met het gebruik van onze website! De handleiding kun je <a href="https://drive.google.com/open?id=1HLwhbH4YXEbV7osGmFUt24gk_zxGjnVilTG0MpkkPUI">hier</a> vinden.
</p>
<p>
met vriendelijke groet,
<br>
Coen Dunnink<br>
06-14363514<br>
FCToernooi
</p>
EOT;
        $this->mailer->send($subject, $body, $user->getEmailaddress());

        $adminSubject = $subject . " (" . $tournament->getCompetition()->getFirstSportConfig()->getSport()->getName(
            ) . ")";
        $prepend = "email: " . $user->getEmailaddress() . "<br><br>link: " . $this->config->getString(
                "www.wwwurl"
            ) . $tournament->getId() . "<br>";
        $prepend .= "publiek: " . ($tournament->getPublic() ? "ja" : "nee") . "<br>";
        $prepend .= "start: " . ($tournament->getCompetition()->getStartDateTime()->format("Y-m-d")) . "<br>";
        $structure = $this->structureRepos->getStructure($tournament->getCompetition());
        if ($structure instanceof Structure) {
            $prepend .= "structuur poules->plekken: " . count($structure->getFirstRoundNumber()->getPoules()) . "->";
            $prepend .= $structure->getFirstRoundNumber()->getNrOfPlaces() . "<br>";
            $nrOfCompetitors = count($structure->getFirstRoundNumber()->getCompetitors());
            $prepend .= "aantal deelnemers: " . $nrOfCompetitors . "<br>";
        } else {
            $prepend .= "structuur gewijzigd: geen geldige structuur" . "<br>";
        }

        $this->mailer->sendToAdmin($adminSubject, $prepend . $body);
    }

    protected function hasChanged(Structure $structure): bool
    {
        $firstRoundNumber = $structure->getFirstRoundNumber();
        if ($firstRoundNumber->hasNext()) {
            return true;
        }
        $rootRound = $structure->getRootRound();
        if ($rootRound->getPoules()->count() !== 1) {
            return true;
        }
        $poule = $rootRound->getPoule(1);
        if ($poule === null || $poule->getPlaces()->count() !== StructureService::DEFAULTNROFPLACES) {
            return true;
        }

        return false;
    }

    protected function initMailer(LoggerInterface $logger)
    {
        $emailSettings = $this->config->getArray('email');
        $this->mailer = new Mailer(
            $logger,
            $emailSettings['from'],
            $emailSettings['fromname'],
            $emailSettings['admin']
        );
    }
}
