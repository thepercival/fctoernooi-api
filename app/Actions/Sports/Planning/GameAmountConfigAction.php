<?php

declare(strict_types=1);

namespace App\Actions\Sports\Planning;

use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
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
    /**
     * @var SportRepository
     */
    protected $sportRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var GameAmountConfigRepository
     */
    protected $gameAmountConfigRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        SportRepository $sportRepos,
        StructureRepository $structureRepos,
        GameAmountConfigRepository $gameAmountConfigRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->sportRepos = $sportRepos;
        $this->structureRepos = $structureRepos;
        $this->gameAmountConfigRepos = $gameAmountConfigRepos;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            /** @var GameAmountConfig $gameAmountConfigSer */
            $gameAmountConfigSer = $this->serializer->deserialize($this->getRawData(), GameAmountConfig::class, 'json');

            $roundNumberAsValue = 0;
            if (array_key_exists("roundnumber", $args) && strlen($args["roundnumber"]) > 0) {
                $roundNumberAsValue = (int)$args["roundnumber"];
            }
            if ($roundNumberAsValue === 0) {
                throw new \Exception("geen rondenummer opgegeven", E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $roundNumber = $structure->getRoundNumber($roundNumberAsValue);

            $sport = $this->sportRepos->find((int)$args["sportid"]);
            if ($sport === null) {
                throw new \Exception('de sport kon niet gevonden worden', E_ERROR);
            }
            $competitionSport = $competition->getSport($sport);
            if ($competitionSport === null) {
                throw new \Exception("de sport kon niet gevonden worden", E_ERROR);
            }
            if ($roundNumber->getGameAmountConfig($competitionSport) !== null) {
                throw new \Exception("er zijn al wedstrijdaantal-instellingen aanwezig", E_ERROR);
            }

            $gameAmountConfig = new GameAmountConfig($competitionSport, $roundNumber, $gameAmountConfigSer->getAmount());

            $this->gameAmountConfigRepos->save($gameAmountConfig);

            $this->removeNext($roundNumber, $competitionSport);

            $json = $this->serializer->serialize($gameAmountConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            $structure = $this->structureRepos->getStructure($competition); // to init next/previous

            $roundNumberAsValue = 0;
            if (array_key_exists("roundnumber", $args) && strlen($args["roundnumber"]) > 0) {
                $roundNumberAsValue = (int)$args["roundnumber"];
            }
            if ($roundNumberAsValue === 0) {
                throw new \Exception("geen rondenummer opgegeven", E_ERROR);
            }

            $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
            if ($roundNumber === null) {
                throw new \Exception("het rondenummer kan niet gevonden worden", E_ERROR);
            }

            /** @var GameAmountConfig $gameAmountConfigSer */
            $gameAmountConfigSer = $this->serializer->deserialize(
                $this->getRawData(),
                GameAmountConfig::class,
                'json'
            );

            /** @var GameAmountConfig|null $gameAmountConfig */
            $gameAmountConfig = $this->gameAmountConfigRepos->find((int)$args['gameAmountConfigId']);
            if ($gameAmountConfig === null) {
                throw new \Exception("er zijn geen wedstrijdaantal-instellingen gevonden om te wijzigen", E_ERROR);
            }

            $gameAmountConfig->setAmount($gameAmountConfigSer->getAmount());

            $this->removeNext($roundNumber, $gameAmountConfig->getCompetitionSport());

            $json = $this->serializer->serialize($gameAmountConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
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
