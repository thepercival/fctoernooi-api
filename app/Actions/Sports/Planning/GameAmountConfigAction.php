<?php

declare(strict_types=1);

namespace App\Actions\Sports\Planning;

use App\Actions\Action;
use App\Repositories\Sports\StructureRepository;
use App\Response\ErrorResponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Sports\Competition\CompetitionSport;
use Sports\Planning\GameAmountConfig;
use Sports\Round\Number as RoundNumber;

/**
 * @api
 */
final class GameAmountConfigAction extends Action
{
    /** @var EntityRepository<CompetitionSport> */
    protected EntityRepository $competiionSportRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected StructureRepository $structureRepos,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct($logger, $serializer);

        $metaData = $entityManager->getClassMetadata(CompetitionSport::class);
        $this->competiionSportRepos = new EntityRepository($entityManager, $metaData);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function save(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $competition = $tournament->getCompetition();

            /** @var GameAmountConfig $gameAmountConfigSer */
            $gameAmountConfigSer = $this->serializer->deserialize($this->getRawData($request), GameAmountConfig::class, 'json');

            $structure = $this->structureRepos->getStructure($competition);

            $argRoundNumber = isset($args['roundNumber']) ? $args['roundNumber'] : null;
            if (!is_string($argRoundNumber) || strlen($argRoundNumber) === 0) {
                throw new \Exception('geen rondenummer opgegeven', E_ERROR);
            }
            $roundNumber = $structure->getRoundNumber((int)$argRoundNumber);
            if ($roundNumber === null) {
                throw new \Exception('het rondenummer kan niet gevonden worden', E_ERROR);
            }
            $argCompetitionSportId = isset($args['competitionSportId']) ? $args['competitionSportId'] : null;
            if (!is_string($argCompetitionSportId) || strlen($argCompetitionSportId) === 0) {
                throw new \Exception('geen sport opgegeven', E_ERROR);
            }
            $competitionSport = $this->competiionSportRepos->find((int)$argCompetitionSportId);
            if ($competitionSport === null) {
                throw new \Exception('de sport kon niet gevonden worden', E_ERROR);
            }
            $gameAmountConfig = $roundNumber->getGameAmountConfig($competitionSport);
            if ($gameAmountConfig === null) {
                $gameAmountConfig = new GameAmountConfig(
                    $competitionSport,
                    $roundNumber,
                    $gameAmountConfigSer->getAmount()
                );
            } else {
                $gameAmountConfig->setAmount($gameAmountConfigSer->getAmount());
            }

            $this->entityManager->persist($gameAmountConfig);
            $this->entityManager->flush();

            $this->removeNext($roundNumber, $competitionSport);

            $json = $this->serializer->serialize($gameAmountConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    protected function removeNext(RoundNumber $roundNumber, CompetitionSport $competitionSport): void
    {
        $next = $roundNumber->getNext();
        if ($next === null) {
            return;
        }
        $gameAmountConfig = $next->getGameAmountConfig($competitionSport);
        if ($gameAmountConfig !== null) {
            $next->getGameAmountConfigs()->removeElement($gameAmountConfig);
            $this->entityManager->remove($gameAmountConfig);
            $this->entityManager->flush();
        }
        $this->removeNext($next, $competitionSport);
    }
}
