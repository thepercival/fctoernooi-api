<?php

declare(strict_types=1);

namespace App\Actions\Tournament;

use App\Actions\Action;
use FCToernooi\Tournament\Repository as TournamentRepository;
use FCToernooi\Tournament\Shell as Shell;
use FCToernooi\Tournament\ShellFilter;
use FCToernooi\User;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

final class ShellAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRepository $tournamentRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchPublic(Request $request, Response $response, array $args): Response
    {
        /** @var User $user */
        $user = $request->getAttribute("user");
        try {
            $shellFilter = $this->getShellFilterFromInput( $request->getQueryParams() );


            $shells = [];
            $tournamentsByDates = $this->tournamentRepos->findByFilter(
                $shellFilter, null, null, 100);
            foreach ($tournamentsByDates as $tournament) {
                $shells[] = new Shell($tournament, $user);
            }

            $json = $this->serializer->serialize($shells, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }

    /**
     * @param list<string> $queryParams
     * @return ShellFilter
     */
    protected function getShellFilterFromInput(array $queryParams): ShellFilter{
        $startDateTime = null;
        if (array_key_exists("startDate", $queryParams) && strlen($queryParams["startDate"]) > 0) {
            $startDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $queryParams["startDate"]);
        }
        if ($startDateTime === false) {
            $startDateTime = null;
        }

        $endDateTime = null;
        if (array_key_exists("endDate", $queryParams) && strlen($queryParams["endDate"]) > 0) {
            $endDateTime = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $queryParams["endDate"]);
        }
        if ($endDateTime === false) {
            $endDateTime = null;
        }

        $name = null;
        if (array_key_exists("name", $queryParams) && strlen($queryParams["name"]) > 0) {
            $name = $queryParams["name"];
        }

        $example = null;
        if (array_key_exists("example", $queryParams) && strlen($queryParams["example"]) > 0) {
            $example = $queryParams["example"] == 1;
        }
        return new ShellFilter(
            $startDateTime, $endDateTime, $name, true, $example
        );
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchWithRole(Request $request, Response $response, array $args): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $roles = 0;
            if (array_key_exists("roles", $queryParams) && strlen($queryParams["roles"]) > 0) {
                $roles = (int)$queryParams["roles"];
            }

            $shells = [];
            /** @var User $user */
            $user = $request->getAttribute("user");
            $tournamentsByRole = $this->tournamentRepos->findByRoles($user, $roles);
            foreach ($tournamentsByRole as $tournament) {
                $shells[] = new Shell($tournament, $user);
            }
            $json = $this->serializer->serialize($shells, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 422);
        }
    }
}
