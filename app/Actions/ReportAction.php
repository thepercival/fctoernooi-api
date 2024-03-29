<?php

declare(strict_types=1);

namespace App\Actions;

use App\ViewHelpers\TournamentReport;
use App\ViewHelpers\TournamentReport as TournamentReportHelper;
use DateTimeImmutable;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\ShellFilter;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\Exception\HttpException;
use Slim\Views\Twig as TwigView;
use Sports\Structure\Repository as StructureRepository;

final class ReportAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,
        private StructureRepository $structureRepos,
        private TwigView $view,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string|int> $args
     * @return Response
     */
    public function usage(Request $request, Response $response, array $args): Response
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
            throw new HttpException($request, $exception->getMessage(), 400);
        }
    }

    /**
     * @param Request $request
     * @return list<TournamentReportHelper>
     */
    public function getTournamentHelpers(Request $request): array
    {
        $tournamentHelpers = [];
        foreach ($this->getTournaments($request) as $tournament) {
            $structure = $this->structureRepos->getStructure($tournament->getCompetition());
            $publicUrl = $tournament->getPublic() ? ($this->config->getString("www.wwwurl") .
                ((string)$tournament->getId())) : '';
            $tournamentHelpers[] = new TournamentReportHelper($tournament, $structure, $publicUrl);
        }
        return $tournamentHelpers;
    }

    /**
     * @param Request $request
     * @return list<Tournament>
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
            new ShellFilter(null, null, null, null, null),
            $startDateTimeCreated,
            null
        );
        uasort(
            $tournaments,
            function (Tournament $tA, Tournament $tB): int {
                return $tA->getCreatedDateTime() > $tB->getCreatedDateTime() ? -1 : 1;
            }
        );
        return array_values($tournaments);
    }
}
