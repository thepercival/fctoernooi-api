<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use App\ViewHelpers\TournamentReport;
use App\ViewHelpers\TournamentReport as TournamentReportHelper;
use DateTimeImmutable;
use FCToernooi\Tournament;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Repository as TournamentRepository;
use Psr\Log\LoggerInterface;
use Sports\Structure\Repository as StructureRepository;
use FCToernooi\LockerRoom\Repository as LockerRoomRepistory;
use JMS\Serializer\SerializerInterface;
use App\Copiers\TournamentCopier;
use Selective\Config\Configuration;
use Slim\Views\Twig as TwigView;

class ReportAction extends Action
{
    /**
     * @var TournamentRepository
     */
    protected $tournamentRepos;
    /**
     * @var TournamentCopier
     */
    protected $tournamentCopier;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var LockerRoomRepistory
     */
    protected $lockerRoomRepos;
    /**
     * @var TwigView
     */
    protected $view;


    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        TournamentRepository $tournamentRepos,
        TournamentCopier $tournamentCopier,
        StructureRepository $structureRepos,
        LockerRoomRepistory $lockerRoomRepos,
        TwigView $view,
        Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->tournamentRepos = $tournamentRepos;
        $this->tournamentCopier = $tournamentCopier;
        $this->structureRepos = $structureRepos;
        $this->lockerRoomRepos = $lockerRoomRepos;
        $this->view = $view;
        $this->config = $config;
    }

    public function usage(Request $request, Response $response, $args): Response
    {
        // $user = $request->getAttribute("user");
        try {
            $tournamentHelpers = $this->getTournamentHelpers($request);
            return $this->view->render(
                $response,
                'usagereport.twig',
                [
                    'tournaments' => $tournamentHelpers,
                    'amount' => count($tournamentHelpers)
                ]
            );
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * @param Request $request
     * @return array|TournamentReportHelper[]
     */
    public function getTournamentHelpers(Request $request): array
    {
        $tournamentHelpers = [];
        foreach ($this->getTournaments($request) as $tournament) {
            $structure = $this->structureRepos->getStructure($tournament->getCompetition());
            $publicUrl = $tournament->getPublic() ? ($this->config->getString("www.wwwurl") . $tournament->getId(
                )) : '';
            $tournamentHelpers[] = new TournamentReportHelper($tournament, $structure, $publicUrl);
        }
        return $tournamentHelpers;
    }

    /**
     * @param Request $request
     * @return array|Tournament[]
     */
    public function getTournaments(Request $request): array
    {
        $queryParams = $request->getQueryParams();

        $startDateTimeCreated = null;
        if (array_key_exists("startDateTimeCreated", $queryParams) && strlen(
                $queryParams["startDateTimeCreated"]
            ) > 0) {
            $startDateTimeCreated = \DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i:s.u\Z',
                $queryParams["startDateTimeCreated"]
            );
        }
        if ($startDateTimeCreated === false) {
            $startDateTimeCreated = null;
        }
        if ($startDateTimeCreated === null) {
            $startDateTimeCreated = (new DateTimeImmutable())->modify("-7 days");
        }

        $tournaments = $this->tournamentRepos->findByFilter(
            null,
            null,
            null,
            null,
            $startDateTimeCreated,
            null
        );
        uasort(
            $tournaments,
            function (Tournament $tA, Tournament $tB): int {
                return $tA->getCreatedDateTime() > $tB->getCreatedDateTime() ? -1 : 1;
            }
        );
        return $tournaments;
    }
}
