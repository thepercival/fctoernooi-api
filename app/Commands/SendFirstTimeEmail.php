<?php

namespace App\Commands;

use FCToernooi\Role;
use FCToernooi\Tournament;
use FCToernooi\User;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use App\Settings\Www as WwwSettings;
use App\Mailer;
use FCToernooi\Tournament\Repository as TournamentRepository;

use function App\Cronjob\mailHelp;

class SendFirstTimeEmail extends Command
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Mailer
     */
    protected $mailer;
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var WwwSettings
     */
    protected $wwwSettings;
    /**
     * @var string
     */
    protected $env;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->mailer = $container->get(Mailer::class);

        $this->wwwSettings = $container->get(WwwSettings::class);
        $this->env = $container->get('settings')['environment'];
        parent::__construct();
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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $users = $this->userRepos->findAll();
            foreach ($users as $user) {
                if ($user->getHelpSent() === true) {
                    continue;
                }
                $tournaments = $this->tournamentRepos->findByPermissions($user, Role::ADMIN);
                if (count($tournaments) === 0) {
                    continue;
                }

                $tournament = reset($tournaments);
                $this->sendMail($user, reset($tournaments));
                $user->setHelpSent(true);
                $this->userRepos->save($user);
            }
        } catch (\Exception $e) {
            if ($this->env === 'production') {
                $this->mailer->send("error sending firsttime-mail", $e->getMessage());
                $this->logger->addError("GENERAL ERROR: " . $e->getMessage());
            } else {
                echo $e->getMessage() . PHP_EOL;
            }
        }

        return 0;
    }

    protected function sendMail(User $user, Tournament $tournament)
    {
        $subject = $tournament->getCompetition()->getLeague()->getName();
        $body = '
        <p>Hallo,</p>
        <p>            
        Als beheerder van <a href="https://www.fctoernooi.nl/">https://www.fctoernooi.nl/</a> zag ik dat je een toernooi hebt aangemaakt op onze website. 
        Mocht je vragen hebben of dan horen we dat graag. Beantwoord dan gewoon deze email of bel me.        
        </p>
        <p>            
        Veel plezier met het gebruik van onze website! De handleiding kun je <a href="https://docs.google.com/document/d/1SYeUJa5yvHZzvacMyJ_Xy4MpHWTWRgAh1LYkEA2CFnM/edit?usp=sharing">hier</a> vinden.
        </p>
        <p>
        met vriendelijke groet,
        <br>
        Coen Dunnink<br>
        06-14363514
        </p>';

        $this->mailer->send($subject, $body, $user->getEmailaddress());

        $prepend = "email: " . $user->getEmailaddress(
            ) . "<br><br>link: https://www.fctoernooi.nl/toernooi/view/" . $tournament->getId() . "<br><br>";
        $this->mailer->sendToAdmin($subject, $prepend . $body);
    }
}
