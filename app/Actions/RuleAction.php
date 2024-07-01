<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use Exception;
use FCToernooi\Tournament;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Rule as TournamentRule;
use FCToernooi\Tournament\Rule\Repository as TournamentRuleRepository;
use Psr\Log\LoggerInterface;
use Sports\Priority\Service as PriorityService;

final class RuleAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRuleRepository $ruleRepos
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

            $json = $this->serializer->serialize($tournament->getRules(), 'json');
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

//            if( $tournament->getRules() >= TournamentRule::MAX_PER_TOURNAMENT ) {
                throw new \Exception('Het maximum aantal regels is bereikt');
  //          }
            /** @var TournamentRule $serRule */
            $serRule = $this->serializer->deserialize($this->getRawData($request), TournamentRule::class, 'json');

            $newRule = new TournamentRule(
                $tournament,
                $serRule->getText()
            );
            $this->ruleRepos->save($newRule, true);

            $json = $this->serializer->serialize($newRule, 'json');
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

            /** @var TournamentRule $ruleSer */
            $ruleSer = $this->serializer->deserialize($this->getRawData($request), TournamentRule::class, 'json');

            $rule = $this->ruleRepos->find((int)$args['ruleId']);
            if ($rule === null) {
                throw new \Exception("geen regel met het opgegeven id gevonden", E_ERROR);
            }
            if ($rule->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de regel");
            }

            $rule->setText($ruleSer->getText());
            $this->ruleRepos->save($rule);

            $json = $this->serializer->serialize($rule, 'json');
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
    public function priorityUp(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute('tournament');

            $competition = $tournament->getCompetition();

            $rule = $this->ruleRepos->find((int)$args['ruleId']);
            if ($rule === null) {
                throw new \Exception("geen regel met het opgegeven id gevonden", E_ERROR);
            }
            if ($rule->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de regel");
            }

            $priorityService = new PriorityService(array_values($tournament->getRules()->toArray()));
            $changedRules = $priorityService->upgrade($rule);
            foreach ($changedRules as $changedRule) {
                if ($changedRule instanceof Tournament\Rule) {
                    $this->ruleRepos->save($changedRule);
                }
            }

            return $response->withStatus(200);
        } catch (Exception $exception) {
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

            $rule = $this->ruleRepos->find((int)$args['ruleId']);
            if ($rule === null) {
                throw new \Exception("geen regel met het opgegeven id gevonden", E_ERROR);
            }
            if ($rule->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de regel");
            }

            $tournament->getRules()->removeElement($rule);
            $this->ruleRepos->remove($rule);

            $priorityService = new PriorityService(array_values($tournament->getRules()->toArray()));
            $changedRules = $priorityService->validate();
            foreach ($changedRules as $changedRule) {
                if ($changedRule instanceof Tournament\Rule) {
                    $this->ruleRepos->save($changedRule);
                }
            }

            $this->ruleRepos->remove($rule);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
    
}
