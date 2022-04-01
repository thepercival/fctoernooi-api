<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use DateTimeImmutable;
use Exception;
use FCToernooi\Auth\Item as AuthItem;
use FCToernooi\Auth\Service as AuthService;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Memcached;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use stdClass;

final class AuthAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private Memcached $memcached,
        private AuthService $authService,
        private UserRepository $userRepos,
        private CreditActionRepository $creditActionRepos
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function extendToken(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute('user');
            $authItem = new AuthItem($this->authService->createToken($user), (int)$user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function register(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var stdClass $registerData */
            $registerData = $this->getFormData($request);
            if (property_exists($registerData, 'emailaddress') === false) {
                throw new Exception('geen emailadres ingevoerd');
            }
            if (property_exists($registerData, 'password') === false) {
                throw new Exception('geen wachtwoord ingevoerd');
            }
            $emailAddress = strtolower(trim($registerData->emailaddress));
            $password = $registerData->password;

            $user = $this->authService->register($emailAddress, $password);
            if ($user === null) {
                throw new Exception('de nieuwe gebruiker kan niet worden geretourneerd');
            }

            $authItem = new AuthItem($this->authService->createToken($user), (int)$user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function login(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var stdClass $authData */
            $authData = $this->getFormData($request);
            if (!property_exists($authData, 'emailaddress') || strlen($authData->emailaddress) === 0) {
                throw new Exception('het emailadres is niet opgegeven');
            }
            $emailaddress = filter_var($authData->emailaddress, FILTER_VALIDATE_EMAIL);
            if ($emailaddress === false) {
                throw new Exception("het emailadres \"" . $authData->emailaddress . "\" is onjuist");
            }
            $emailaddress = strtolower(trim($emailaddress));
            if (!property_exists($authData, 'password') || strlen($authData->password) === 0) {
                throw new Exception('het wachtwoord is niet opgegeven');
            }

            $user = $this->userRepos->findOneBy(['emailaddress' => $emailaddress]);

            if ($user === null || !password_verify($user->getSalt() . $authData->password, $user->getPassword())) {
                throw new Exception('ongeldige emailadres en wachtwoord combinatie');
            }

            /*if ( !$user->getActive() ) {
             throw new \Exception( "activeer eerst je account met behulp van de link in je ontvangen email", E_ERROR );
             }*/

            $authItem = new AuthItem($this->authService->createToken($user), (int)$user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function passwordreset(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var stdClass $paswordResetData */
            $paswordResetData = $this->getFormData($request);
            if (property_exists($paswordResetData, 'emailaddress') === false) {
                throw new Exception('geen emailadres ingevoerd');
            }
            $emailAddress = strtolower(trim($paswordResetData->emailaddress));

            $retVal = $this->authService->sendPasswordCode($emailAddress);

            $data = ['retval' => $retVal];
            $json = $this->serializer->serialize($data, 'json');
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function passwordchange(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var stdClass $paswordChangeData */
            $paswordChangeData = $this->getFormData($request);
            if (property_exists($paswordChangeData, 'emailaddress') === false) {
                throw new Exception('geen emailadres ingevoerd');
            }
            if (property_exists($paswordChangeData, 'password') === false) {
                throw new Exception('geen wachtwoord ingevoerd');
            }
            if (property_exists($paswordChangeData, 'code') === false) {
                throw new Exception('geen code ingevoerd');
            }
            $emailAddress = $emailAddress = strtolower(trim($paswordChangeData->emailaddress));
            $password = $paswordChangeData->password;
            $code = (string)$paswordChangeData->code;

            $user = $this->authService->changePassword($emailAddress, $password, $code);

            $authItem = new AuthItem($this->authService->createToken($user), (int)$user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function validatationRequest(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute('user');

            $expireSeconds = 60 * 30;
            $code = '' . random_int(100000, 999999);
            if (!$this->memcached->set('validate-code-' . (string)$user->getId(), $code, $expireSeconds)) {
                throw new \Exception('de validatie-aanvraag is niet gelukt', E_ERROR);
            }
            $expireDateTime = (new DateTimeImmutable())->modify('+' . $expireSeconds . ' seconds');

            $this->authService->mailValidationCode($user, $code, $expireDateTime);

            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function validate(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute('user');

            $code = $this->memcached->get('validate-code-' . (string)$user->getId());
            if ($code === false || $code === Memcached::RES_NOTFOUND) {
                throw new \Exception('de validatie-code is niet meer geldig', E_ERROR);
            }

            if ($code !== $args['code']) {
                throw new \Exception('de validatie-code komt niet overeen', E_ERROR);
            }

            $this->authService->validate($user);

            $json = $this->serializer->serialize($user, 'json', $this->getSerializationContext());
            return $this->respondWithJson($response, $json);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }

    protected function getSerializationContext(): SerializationContext
    {
        $serGroups = ['Default', 'self'];
        return SerializationContext::create()->setGroups($serGroups);
    }
}
