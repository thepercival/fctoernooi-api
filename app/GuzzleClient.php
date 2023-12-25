<?php

namespace App;

use GuzzleHttp\Client;
use SportsPlanning\Input;
use SportsPlanning\Planning;

class GuzzleClient
{
    private Client $client;

    public function __construct(private string $baseUrl, private string $apikey)
    {
        $this->client = new Client();
    }

    public function get(string $body): string {
        $endPoint = $this->baseUrl;
        $response = $this->client->post($endPoint, $this->getOptions($body) );
        return $response->getBody()->getContents();
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