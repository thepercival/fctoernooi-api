<?php

namespace App;

use App\Exceptions\DomainRecordBeingCalculatedException;
use App\Exceptions\DomainRecordNotFoundException;
use FCToernooi\CacheService;
use FCToernooi\Planning\RoundNumberWithPlanning;
use GuzzleHttp\Client;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Round\Number as RoundNumber;
use Sports\Round\Number\InputConfigurationCreator;
use SportsPlanning\Input\Configuration;
use SportsPlanning\Planning;
use SportsPlanning\Referee\Info as PlanningRefereeInfo;

class GuzzleClient
{
    private Client $client;
    private SerializationContext|null $serContext = null;

    public function __construct(
        private string $baseUrl,
        private string $apikey,
        private CacheService $cacheService,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger)
    {
        $this->client = new Client();
    }

    public function getPlanningAsString(
        Configuration $configuration,
        int|string $competitionId,
        bool $updateCache): string {
        $endPoint = $this->baseUrl;

        $jsonInputConfig = $this->serializer->serialize($configuration, 'json' );
        $response = $this->client->post($endPoint, $this->getOptions($jsonInputConfig) );

        if( $response->getStatusCode() === 204) {
            if( $updateCache ) {
                $this->cacheService->addCompetitionIdWithoutPlanning($competitionId);
            }
            $this->logger->info( 'no planning found : ' . $configuration->getName() );
            throw new DomainRecordNotFoundException('no planning found', E_ERROR);
        }

        if( $response->getStatusCode() === 202) {
            $this->logger->info( 'uncalculated inputConfig : ' . $configuration->getName() );
            throw new DomainRecordBeingCalculatedException('planning is being calculated', E_ERROR);
        }

        if( $updateCache ) {
            $this->cacheService->removeCompetitionIdWithoutPlanning($competitionId);
        }
        return $response->getBody()->getContents();
    }

    /**
     * @param Competition $competition
     * @param list<RoundNumber> $roundNumbers
     * @param bool $updateCache
     * @return list<RoundNumberWithPlanning>
     */
    public function getRoundNumbersWithPlanning(Competition $competition, array $roundNumbers, bool $updateCache): array {

        $roundNumbersWithPlanning = [];

        foreach( $roundNumbers as $roundNumber) {
            $refereeInfo = new PlanningRefereeInfo($roundNumber->getRefereeInfo());
            $inputConfiguration = (new InputConfigurationCreator())->create($roundNumber, $refereeInfo);

            $competitionId = (string)$competition->getId();
            $planningAsString = $this->getPlanningAsString($inputConfiguration, $competitionId, $updateCache);

            /** @var Planning $planning */
            $planning = $this->serializer->deserialize($planningAsString, Planning::class, 'json');
            $roundNumbersWithPlanning[] = new RoundNumberWithPlanning($roundNumber, $planning);

        }
        return $roundNumbersWithPlanning;
    }

    public function getProgress(string $body): int {
        $endPoint = $this->baseUrl . 'progress';
        $response = $this->client->post($endPoint, $this->getOptions($body) );
        $content = $response->getBody()->getContents();
        $json = json_decode($content);
        return (int)$json->progress;
    }

    public function getMinNrOfBatches(string $body): int {
        $endPoint = $this->baseUrl . 'minNrOfBatches';
        $response = $this->client->post($endPoint, $this->getOptions($body) );
        if( $response->getStatusCode() === 422) {
            throw new DomainRecordNotFoundException('no planning found', E_ERROR);
        }
        $content = $response->getBody()->getContents();
        $json = json_decode($content);
        return (int)$json->minNrOfBatches;
    }

    /**
     * @return array<string|int, mixed>
     */
    protected function getOptions(string $body): array
    {
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => false,
            // CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 6
        ];


        return [
            'curl' => $curlOptions,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apikey,
                /*"User:agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36"*/
            ],
            'body' => $body
        ];
    }

}