<?php

declare(strict_types=1);

namespace App\Actions;

use App\Export\PdfService;
use App\Export\PdfSubject;
use App\Mailer;
use App\QueueService\Pdf as PdfQueueService;
use Exception;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\User;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use Slim\Exception\HttpException;
use stdClass;

final class PdfAction extends Action
{
    private PdfQueueService $pdfQueueService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos,
        private PdfService $pdfService,
        private Mailer $mailer,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
        $this->pdfQueueService = new PdfQueueService($config->getArray('queue'));
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

            /** @var stdClass $postData */
            $postData = $this->getFormData($request);
            if (property_exists($postData, 'subjects') === false) {
                throw new Exception('geen subjects ingevoerd');
            }

            $subjects = $this->getSubjects($postData->subjects);

            $tournament->setExported($tournament->getExported() | PdfSubject::sum($subjects));
            $this->tournamentRepos->save($tournament);

            $fileName = $this->pdfService->createASyncOnDisk($tournament, $subjects, $this->pdfQueueService);

            $json = json_encode(['fileName' => $fileName]);
            return $this->respondWithJson($response, $json === false ? '' : $json);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
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

//            $this->pdfService->validateHash($tournament, $args['hash']);

            $progressValue = $this->pdfService->getProgress((string)$tournament->getId())->getProgress();

            if ($progressValue < 0) {
                throw new Exception('de pdf-aanvraag voor dit toernooi is niet gestart', E_ERROR);
            }

            $json = json_encode(['progress' => (int)$progressValue]);
            return $this->respondWithJson($response, $json === false ? '' : $json);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 400);
        }
    }


    /**
     * @param int $subjects
     * @return non-empty-list<PdfSubject>
     * @throws Exception
     */
    protected function getSubjects(int $subjects): array
    {
        $subjects = PdfSubject::toFilteredArray($subjects);
        if (count($subjects) === 0) {
            throw new Exception('kies minimaal 1 exportoptie', E_ERROR);
        }
        return $subjects;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, string> $args
     * @return Response
     */
    public function applyService(Request $request, Response $response, array $args): Response {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            /** @var User $user */
            $user = $request->getAttribute('user');

            $tournamentUser = $tournament->getUser($user);
            if( $tournamentUser === null ) {
                throw new Exception('toernooi gebruiker niet gevonden', E_ERROR);
            }

            $this->sendPrintServiceApplication($tournament, $user);

            return $this->respondWithJson($response, '');
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    protected function sendPrintServiceApplication(Tournament $tournament, User $user): void
    {
        $subject = 'printaanvraag voor toernooi "' . $tournament->getName() . '"';

        $content = 'vraag naar de aantallen, maak voeg welkomstpagina toe <br/>';
        $content .= 'stuur door naar ' . $user->getEmailaddress();

        $this->mailer->send($subject, $content, $this->config->getString('email.admin'));
    }
}
