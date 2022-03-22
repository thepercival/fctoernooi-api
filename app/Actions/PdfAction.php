<?php

declare(strict_types=1);

namespace App\Actions;

use App\Export\PdfService;
use App\Export\PdfSubject;
use App\Response\ErrorResponse;
use Exception;
use FCToernooi\CacheService;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Sports\Structure\Repository as StructureRepository;

final class PdfAction extends Action
{


    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,
        private StructureRepository $structureRepos,
        private PdfService $pdfService,
        private CacheService $cacheService,
        Configuration $config
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $progressValue = $this->pdfService->getProgressValue((string)$tournament->getId(), $args['hash']);
            if( $progressValue < 0) {
                throw new Exception('de pdf-aanvraag is verlopen', E_ERROR);
            }
            if( $progressValue < 100) {
                throw new Exception('de pdf-aanvraag is nog in behandeling', E_ERROR);
            }

            $pdf = $this->pdfService->getFromDisk((string)$tournament->getId(), $args['hash']);
            $vtData = $pdf->render();

            $fileName = $this->pdfService->getFileNameFromCache((string)$tournament->getId(), $args['hash']);
            $response->getBody()->write($vtData);

            return $response
                ->withHeader('Cache-Control', 'must-revalidate')
                ->withHeader('Pragma', 'public')
                ->withHeader('Content-Disposition', 'inline; filename="' . $fileName . ';')
                ->withHeader(
                    'Content-Type',
                    'application/pdf;charset=utf-8'
                )
                ->withHeader('Content-Length', '' . strlen($vtData));
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $subjects = $this->getSubjects($request->getQueryParams());

            $tournament->setExported($tournament->getExported() | PdfSubject::sum($subjects));
            $this->tournamentRepos->save($tournament);

            $structure = $this->structureRepos->getStructure($tournament->getCompetition());

            if( $this->pdfService->isStarted((string)$tournament->getId(), $args['hash']) ) {
                throw new Exception('er loopt nog een pdf-aanvraag voor dit toernooi', E_ERROR);
            }

            // do async
            $hash = $this->pdfService->createASyncOnDisk($tournament, $structure, $subjects, false);

            // hoe kun je nu hierkomen, zodat het proces toch verder gaat?

            // return $this->writePdf($response, $subjects, $tournament, $this->config->getString('www.wwwurl'));

            $json = json_encode(['hash' => $hash]);
            return $this->respondWithJson($response, $json === false ? '' : $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function progress(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $progressValue = $this->pdfService->getProgressValue((string)$tournament->getId(), $args['hash']);

            if( $progressValue < 0 ) {
                throw new Exception('de pdf-aanvraag voor dit toernooi is niet gestart', E_ERROR);
            }

            $json = json_encode(['progressValue' => $progressValue]);
            return $this->respondWithJson($response, $json === false ? '' : $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }



    /**
     * @param array<string, int|string> $queryParams
     * @return non-empty-list<PdfSubject>
     * @throws Exception
     */
    protected function getSubjects(array $queryParams): array
    {
        $inputSubjects = 0;
        if (isset($queryParams['subjects'])) {
            $inputSubjects = (int)$queryParams['subjects'];
        }

        $subjects = PdfSubject::toFilteredArray($inputSubjects);
        if (count($subjects) === 0) {
            throw new Exception('kies minimaal 1 exportoptie', E_ERROR);
        }
        return $subjects;
    }
}
