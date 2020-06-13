<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 22-5-18
 * Time: 12:23
 */

namespace App\Actions;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use App\ImageService;
use Slim\Factory\ServerRequestCreatorFactory;
use \Suin\ImageResizer\ImageResizer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use FCToernooi\Sponsor\Repository as SponsorRepository;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Sponsor;
use FCToernooi\Tournament;

final class SponsorAction extends Action
{
    /**
     * @var SponsorRepository
     */
    private $sponsorRepos;
    /**
     * @var TournamentRepository
     */
    private $tournamentRepos;
    /**
     * @var ImageService
     */
    protected $imageService;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        SponsorRepository $sponsorRepos,
        TournamentRepository $tournamentRepos,
        ImageService $imageService
    ) {
        parent::__construct($logger, $serializer);
        $this->sponsorRepos = $sponsorRepos;
        $this->tournamentRepos = $tournamentRepos;
        $this->imageService = $imageService;
    }

    public function fetch(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");
            $json = $this->serializer->serialize($tournament->getSponsors(), 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 400);
        }
    }

    public function fetchOne(Request $request, Response $response, $args): Response
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
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 400);
        }
    }

    public function add(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var Sponsor $sponsor */
            $sponsor = $this->serializer->deserialize($this->getRawData(), Sponsor::class, 'json');

            $this->sponsorRepos->checkNrOfSponsors($tournament, $sponsor->getScreenNr());

            $newSponsor = new Sponsor($tournament, $sponsor->getName());
            $newSponsor->setUrl($sponsor->getUrl());
            $newSponsor->setLogoUrl($sponsor->getLogoUrl());
            $newSponsor->setScreenNr($sponsor->getScreenNr());
            $this->sponsorRepos->save($newSponsor);

            $json = $this->serializer->serialize($newSponsor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function edit(Request $request, Response $response, $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var Sponsor $sponsorSer */
            $sponsorSer = $this->serializer->deserialize($this->getRawData(), Sponsor::class, 'json');

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
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function remove(Request $request, Response $response, $args): Response
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
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function upload(Request $request, Response $response, $args): Response
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

            $logoUrl = $this->imageService->process((string)$sponsor->getId(), $uploadedFiles["logostream"]);

            $sponsor->setLogoUrl($logoUrl);
            $this->sponsorRepos->save($sponsor);

            $json = $this->serializer->serialize($sponsor, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }


}
