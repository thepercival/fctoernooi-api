<?php

declare(strict_types=1);

namespace App\Actions\Sports\Planning;

use App\Response\ErrorResponse;
use Exception;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Competition\Sport\Repository as CompetitionSportRepository;
use Sports\Round\Number as RoundNumber;
use Sports\Sport;
use Sports\Structure\Repository as StructureRepository;
use Sports\Planning\GameAmountConfig\Repository as GameAmountConfigRepository;
use Sports\Planning\GameAmountConfig;
use Sports\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Competition\Sport as CompetitionSport;

final class GameAmountConfigAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        protected CompetitionSportRepository $competiionSportRepos,
        protected StructureRepository $structureRepos,
        protected GameAmountConfigRepository $gameAmountConfigRepos
    ) {
        parent::__construct($logger, $serializer);
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
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

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
                    $gameAmountConfigSer->getAmount(),
                    $gameAmountConfigSer->getNrOfGamesPerPlace()
                );
            } else {
                $gameAmountConfig->setAmount($gameAmountConfigSer->getAmount());
                $gameAmountConfig->setNrOfGamesPerPlace($gameAmountConfigSer->getNrOfGamesPerPlace());
            }

            $this->gameAmountConfigRepos->save($gameAmountConfig);

            $this->removeNext($roundNumber, $competitionSport);

            $json = $this->serializer->serialize($gameAmountConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function removeNext(RoundNumber $roundNumber, CompetitionSport $competitionSport): void
    {
        while ($next = $roundNumber->getNext()) {
            $gameAmountConfig = $next->getGameAmountConfig($competitionSport);
            if ($gameAmountConfig === null) {
                continue;
            }
            $next->getGameAmountConfigs()->removeElement($gameAmountConfig);
            $this->gameAmountConfigRepos->remove($gameAmountConfig);
        }
    }
}
