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
    protected CompetitionSportRepository $competiionSportRepos;
    protected StructureRepository $structureRepos;
    protected GameAmountConfigRepository $gameAmountConfigRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        CompetitionSportRepository $competiionSportRepos,
        StructureRepository $structureRepos,
        GameAmountConfigRepository $gameAmountConfigRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->competiionSportRepos = $competiionSportRepos;
        $this->structureRepos = $structureRepos;
        $this->gameAmountConfigRepos = $gameAmountConfigRepos;
    }

    public function save(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute('tournament')->getCompetition();

            /** @var GameAmountConfig $gameAmountConfigSer */
            $gameAmountConfigSer = $this->serializer->deserialize($this->getRawData(), GameAmountConfig::class, 'json');

            $roundNumberAsValue = 0;
            if (array_key_exists('roundNumber', $args) && strlen($args['roundNumber']) > 0) {
                $roundNumberAsValue = (int)$args['roundNumber'];
            }
            if ($roundNumberAsValue === 0) {
                throw new Exception('geen rondenummer opgegeven', E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $roundNumber = $structure->getRoundNumber($roundNumberAsValue);

            if (!array_key_exists('competitionSportId', $args) || strlen($args['competitionSportId']) === 0) {
                throw new \Exception('geen sport opgegeven', E_ERROR);
            }
            $competitionSport = $this->competiionSportRepos->find((int)$args['competitionSportId']);
            if ($competitionSport === null) {
                throw new \Exception('de sport kon niet gevonden worden', E_ERROR);
            }
            $gameAmountConfig = $roundNumber->getGameAmountConfig($competitionSport);
            if ($gameAmountConfig === null) {
                $gameAmountConfig = new GameAmountConfig($competitionSport, $roundNumber, $gameAmountConfigSer->getAmount());
            } else {
                $gameAmountConfig->setAmount($gameAmountConfigSer->getAmount());
            }

            $this->gameAmountConfigRepos->save($gameAmountConfig);

            $this->removeNext($roundNumber, $competitionSport);

            $json = $this->serializer->serialize($gameAmountConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function removeNext(RoundNumber $roundNumber, CompetitionSport $competitionSport)
    {
        while ($roundNumber->hasNext()) {
            $roundNumber = $roundNumber->getNext();
            $gameAmountConfig = $roundNumber->getGameAmountConfig($competitionSport);
            if ($gameAmountConfig === null) {
                continue;
            }
            $roundNumber->getGameAmountConfigs()->removeElement($gameAmountConfig);
            $this->gameAmountConfigRepos->remove($gameAmountConfig);
        }
    }
}
