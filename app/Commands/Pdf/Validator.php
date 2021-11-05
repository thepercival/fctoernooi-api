<?php
declare(strict_types=1);

namespace App\Commands\Pdf;

use App\Command;
use App\Export\Pdf\Document as PdfDocument;
use Exception;
use FCToernooi\Tournament;
use FCToernooi\Tournament\ExportConfig;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Sports\Round\Number\GamesValidator;
use Sports\Structure;
use Sports\Structure\Repository as StructureRepository;
use SportsPlanning\Input\Repository as PlanningInputRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Validator extends Command
{
    protected TournamentRepository $tournamentRepos;
    protected StructureRepository $structureRepos;
    protected PlanningInputRepository $planningInputRepos;
    protected GamesValidator $gamesValidator;

    public function __construct(ContainerInterface $container)
    {
        $this->tournamentRepos = $container->get(TournamentRepository::class);
        $this->structureRepos = $container->get(StructureRepository::class);
        $this->planningInputRepos = $container->get(PlanningInputRepository::class);
        $this->gamesValidator = new GamesValidator();

        parent::__construct($container->get(Configuration::class));
    }

    protected function configure(): void
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('app:validate-pdf')
            // the short description shown while running "php bin/console list"
            ->setDescription('validates the tournament-pdfs')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('validates the tournaments');
        parent::configure();

        $this->addArgument('tournamentId', InputArgument::OPTIONAL);
        $this->addOption('subjects', null, InputOption::VALUE_OPTIONAL, 'show certain subjects');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initLogger($input, 'command-pdf-validator');
        try {
            $this->getLogger()->info('pdf-en aan het genereren..');
            $filter = [];
            $tournamentId = $input->getArgument('tournamentId');
            if (is_string($tournamentId) && (int)$tournamentId > 0) {
                $filter = ['id' => $tournamentId];
            }
            $tournaments = $this->tournamentRepos->findBy($filter);
            $subjects = $this->getSubjects($input);
            /** @var Tournament $tournament */
            foreach ($tournaments as $tournament) {
                if ($tournament->getId() < 3000) {
                    continue;
                }
                foreach ($subjects as $subject) {
                    $this->getLogger()->info('creating for pdf ' . (string)$tournament->getId() . '-' . $subject);
                    $structure = null;
                    try {
                        $structure = $this->structureRepos->getStructure($tournament->getCompetition());
                        $this->createPdf($tournament, $structure, $subject);
                        $this->addStructureToLog($tournament, $structure);
                    } catch (Exception $exception) {
                        $this->getLogger()->error($exception->getMessage());
                        if ($structure !== null && count($filter) > 0) {
                            $this->addStructureToLog($tournament, $structure);
                        }
                    }
                }
            }
            $this->getLogger()->info('alle pdf-en zijn gegenereerd');
        } catch (Exception $exception) {
            if ($this->logger !== null) {
                $this->logger->error($exception->getMessage());
            }
        }
        return 0;
    }

    protected function createPdf(Tournament $tournament, Structure $structure, int $subjects): void
    {
        try {
            $url = $this->config->getString('www.wwwurl');
            $pdf = new PdfDocument($tournament, $structure, $subjects, $url);
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fctoernooipdf';
            $file = (string)$tournament->getId() . '-' . $subjects . '.pdf';
            $pdf->save($dir  . DIRECTORY_SEPARATOR . $file);
        } catch (Exception $exception) {
            // $this->showPlanning($tournament, $roundNumber, $competition->getReferees()->count());
            throw new Exception('toernooi-id(' . ((string)$tournament->getId()) . ') => ' . $exception->getMessage(), E_ERROR);
        }
    }

    /**
     * @param InputInterface $input
     * @return list<int>
     * @throws Exception
     */
    protected function getSubjects(InputInterface $input): array
    {
        $subjects = $input->getOption('subjects');
        if (is_string($subjects) && (int)$subjects > 0) {
            return [(int)$subjects];
        }
        $all = ExportConfig::GameNotes + ExportConfig::Structure + ExportConfig::GamesPerPoule +
            ExportConfig::GamesPerField + ExportConfig::Planning + ExportConfig::PoulePivotTables +
            ExportConfig::LockerRooms + ExportConfig::QrCode;

        return [
            ExportConfig::GameNotes,
            ExportConfig::Structure,
            ExportConfig::GamesPerPoule,
            ExportConfig::GamesPerField,
            ExportConfig::Planning,
            ExportConfig::PoulePivotTables,
            ExportConfig::LockerRooms,
            ExportConfig::QrCode,
            $all
        ];
    }

    protected function addStructureToLog(Tournament $tournament, Structure $structure): void
    {
        try {
            // (new StructureOutput($this->getLogger()))->output($structure);
        } catch (Exception $exception) {
            $this->getLogger()->error('could not find structure for tournamentId ' . ((string)$tournament->getId()));
        }
    }
}
