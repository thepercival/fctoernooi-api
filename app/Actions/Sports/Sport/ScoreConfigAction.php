<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 14-11-17
 * Time: 14:02
 */

namespace App\Actions\Sports\Sport;

use App\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Sports\Round\Number as RoundNumber;
use Sports\Sport;
use Sports\Structure\Repository as StructureRepository;
use Sports\Sport\ScoreConfig\Repository as SportScoreConfigRepository;
use Sports\Sport\ScoreConfig\Service as SportScoreConfigService;
use Sports\Sport\Repository as SportRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Actions\Action;
use Sports\Competition;
use Sports\Sport\ScoreConfig as SportScoreConfig;

final class ScoreConfigAction extends Action
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
     * @var SportScoreConfigRepository
     */
    protected $sportScoreConfigRepos;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        SportRepository $sportRepos,
        StructureRepository $structureRepos,
        SportScoreConfigRepository $sportScoreConfigRepos
    ) {
        parent::__construct($logger, $serializer);
        $this->sportRepos = $sportRepos;
        $this->structureRepos = $structureRepos;
        $this->sportScoreConfigRepos = $sportScoreConfigRepos;
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Competition $competition */
            $competition = $request->getAttribute("tournament")->getCompetition();

            /** @var SportScoreConfig $sportScoreConfigSer */
            $sportScoreConfigSer = $this->serializer->deserialize($this->getRawData(), SportScoreConfig::class, 'json');

            $queryParams = $request->getQueryParams();

            $roundNumberAsValue = 0;
            if (array_key_exists("roundnumber", $queryParams) && strlen($queryParams["roundnumber"]) > 0) {
                $roundNumberAsValue = (int)$queryParams["roundnumber"];
            }
            if ($roundNumberAsValue === 0) {
                throw new \Exception("geen rondenummer opgegeven", E_ERROR);
            }
            $structure = $this->structureRepos->getStructure($competition);
            $roundNumber = $structure->getRoundNumber($roundNumberAsValue);

            $sport = $competition->getSportBySportId((int)$queryParams["sportid"]);
            if ($sport === null) {
                throw new \Exception("de sport kon niet gevonden worden", E_ERROR);
            }
            if ($roundNumber->getSportScoreConfig($sport) !== null) {
                throw new \Exception("er zijn al score-instellingen aanwezig", E_ERROR);
            }

            $sportScoreConfig = new \Sports\Sport\ScoreConfig($sport, $roundNumber, null);
            $sportScoreConfig->setDirection(SportScoreConfig::UPWARDS);
            $sportScoreConfig->setMaximum($sportScoreConfigSer->getMaximum());
            $sportScoreConfig->setEnabled($sportScoreConfigSer->getEnabled());
            if ($sportScoreConfigSer->hasNext()) {
                $nextScoreConfig = new SportScoreConfig($sport, $roundNumber, $sportScoreConfig);
                $nextScoreConfig->setDirection(SportScoreConfig::UPWARDS);
                $nextScoreConfig->setMaximum($sportScoreConfigSer->getNext()->getMaximum());
                $nextScoreConfig->setEnabled($sportScoreConfigSer->getNext()->getEnabled());
            }

            $this->sportScoreConfigRepos->save($sportScoreConfig);

            $this->removeNext($roundNumber, $sport);

            $json = $this->serializer->serialize($sportScoreConfig, 'json');
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

            $queryParams = $request->getQueryParams();
            $roundNumberAsValue = 0;
            if (array_key_exists("roundnumber", $queryParams) && strlen($queryParams["roundnumber"]) > 0) {
                $roundNumberAsValue = (int)$queryParams["roundnumber"];
            }
            if ($roundNumberAsValue === 0) {
                throw new \Exception("geen rondenummer opgegeven", E_ERROR);
            }

            $roundNumber = $structure->getRoundNumber($roundNumberAsValue);
            if ($roundNumber === null) {
                throw new \Exception("het rondenummer kan niet gevonden worden", E_ERROR);
            }

            /** @var SportScoreConfig $sportScoreConfigSer */
            $sportScoreConfigSer = $this->serializer->deserialize(
                $this->getRawData(),
                SportScoreConfig::class,
                'json'
            );

            /** @var SportScoreConfig|null $sportScoreConfig */
            $sportScoreConfig = $this->sportScoreConfigRepos->find((int)$args['sportscoreconfigId']);
            if ($sportScoreConfig === null) {
                throw new \Exception("er zijn geen score-instellingen gevonden om te wijzigen", E_ERROR);
            }

            $sportScoreConfig->setMaximum($sportScoreConfigSer->getMaximum());
            $sportScoreConfig->setEnabled($sportScoreConfigSer->getEnabled());
            $this->sportScoreConfigRepos->save($sportScoreConfig);
            if ($sportScoreConfig->hasNext() && $sportScoreConfigSer->hasNext()) {
                $nextScoreConfig = $sportScoreConfig->getNext();
                $nextScoreConfig->setMaximum($sportScoreConfigSer->getNext()->getMaximum());
                $nextScoreConfig->setEnabled($sportScoreConfigSer->getNext()->getEnabled());
                $this->sportScoreConfigRepos->save($nextScoreConfig);
            }

            $this->removeNext($roundNumber, $sportScoreConfig->getSport());

            $json = $this->serializer->serialize($sportScoreConfig, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    protected function removeNext(RoundNumber $roundNumber, Sport $sport)
    {
        while ($roundNumber->hasNext()) {
            $roundNumber = $roundNumber->getNext();
            $scoreConfig = $roundNumber->getSportScoreConfig($sport);
            if ($scoreConfig === null) {
                continue;
            }
            $roundNumber->getSportScoreConfigs()->removeElement($scoreConfig);
            $this->sportScoreConfigRepos->remove($scoreConfig);
        }
    }
}
