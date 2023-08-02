<?php

declare(strict_types=1);

namespace App\Actions;

use App\ImageService;
use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Sponsor;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;

final class SponsorAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private SponsorRepository $sponsorRepos,
        private ImageService $imageService,
        private Configuration $config
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetch(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $json = $this->serializer->serialize($tournament->getSponsors(), 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchOne(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $sponsor = $this->sponsorRepos->find((int)$args['sponsorId']);
            if ($sponsor === null) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ($sponsor->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }
            $json = $this->serializer->serialize($sponsor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function add(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var Sponsor $sponsor */
            $sponsor = $this->serializer->deserialize($this->getRawData($request), Sponsor::class, 'json');

            $this->sponsorRepos->checkNrOfSponsors($tournament, $sponsor->getScreenNr());

            $newSponsor = new Sponsor($tournament, $sponsor->getName());
            $newSponsor->setUrl($sponsor->getUrl());
            $newSponsor->setLogoUrl($sponsor->getLogoUrl());
            $newSponsor->setScreenNr($sponsor->getScreenNr());
            $this->sponsorRepos->save($newSponsor);

            $json = $this->serializer->serialize($newSponsor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize($this->getRawData($request), Sponsor::class, 'json');

            $sponsor = $this->sponsorRepos->find((int)$args['sponsorId']);
            if ($sponsor === null) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ($sponsor->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }

            $this->sponsorRepos->checkNrOfSponsors($tournament, $sponsorSer->getScreenNr(), $sponsor);

            $sponsor->setName($sponsorSer->getName());
            $sponsor->setUrl($sponsorSer->getUrl());
            $sponsor->setLogoUrl($sponsorSer->getLogoUrl());
            $sponsor->setScreenNr($sponsorSer->getScreenNr());
            $this->sponsorRepos->save($sponsor);

            $json = $this->serializer->serialize($sponsor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $sponsor = $this->sponsorRepos->find((int)$args['sponsorId']);
            if ($sponsor === null) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ($sponsor->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }

            $this->sponsorRepos->remove($sponsor);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function upload(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $sponsor = $this->sponsorRepos->find((int)$args['sponsorId']);
            if ($sponsor === null) {
                throw new \Exception("geen sponsor met het opgegeven id gevonden", E_ERROR);
            }
            if ($sponsor->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de sponsor");
            }

            $uploadedFiles = $request->getUploadedFiles();
            if (!array_key_exists("logostream", $uploadedFiles)) {
                throw new \Exception("geen goede upload gedaan, probeer opnieuw", E_ERROR);
            }

            $pathPostfix = $this->config->getString('images.sponsors.pathpostfix');
            $logoUrl = $this->imageService->processImage((string)$sponsor->getId(), $uploadedFiles["logostream"], $pathPostfix);

            $sponsor->setLogoUrl($logoUrl);
            $this->sponsorRepos->save($sponsor);

            $json = $this->serializer->serialize($sponsor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
}
