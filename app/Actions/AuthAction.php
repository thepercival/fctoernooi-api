<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\DomainRecordNotFoundException;
use App\Response\ErrorResponse;
use JMS\Serializer\SerializerInterface;
use FCToernooi\User;
use Psr\Log\LoggerInterface;
use FCToernooi\User\Repository as UserRepository;
use FCToernooi\Auth\Service as AuthService;
use \Slim\Middleware\JwtAuthentication;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use stdClass;
use FCToernooi\Auth\Item as AuthItem;

final class AuthAction extends Action
{
    /**
     * @var AuthService
     */
    private $authService;
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        LoggerInterface $logger,
        AuthService $authService,
        UserRepository $userRepository,
        SerializerInterface $serializer
    ) {
        parent::__construct($logger, $serializer);
        $this->authService = $authService;
        $this->userRepository = $userRepository;
        $this->serializer = $serializer;
    }

    public function extendToken(Request $request, Response $response, $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute("user");
            $authItem = new AuthItem($this->authService->createToken($user), $user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function register(Request $request, Response $response, $args): Response
    {
        try {
            /** @var stdClass $registerData */
            $registerData = $this->getFormData($request);
            if (property_exists($registerData, "emailaddress") === false) {
                throw new \Exception("geen emailadres ingevoerd");
            }
            if (property_exists($registerData, "password") === false) {
                throw new \Exception("geen wachtwoord ingevoerd");
            }
            $emailAddress = strtolower(trim($registerData->emailaddress));
            $password = $registerData->password;

            $user = $this->authService->register($emailAddress, $password);
            if ($user === null) {
                throw new \Exception("de nieuwe gebruiker kan niet worden geretourneerd");
            }

            $authItem = new AuthItem($this->authService->createToken($user), $user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function login(Request $request, Response $response, $args): Response
    {
        try {
            /** @var stdClass $authData */
            $authData = $this->getFormData($request);
            if (!property_exists($authData, "emailaddress") || strlen($authData->emailaddress) === 0) {
                throw new \Exception("het emailadres is niet opgegeven");
            }
            $emailaddress = filter_var($authData->emailaddress, FILTER_VALIDATE_EMAIL);
            if ($emailaddress === false) {
                throw new \Exception("het emailadres \"" . $authData->emailaddress . "\" is onjuist");
            }
            $emailAddress = strtolower(trim($emailaddress));
            if (!property_exists($authData, "password") || strlen($authData->password) === 0) {
                throw new \Exception("het wachtwoord is niet opgegeven");
            }

            $user = $this->userRepository->findOneBy(
                array('emailaddress' => $emailaddress)
            );

            if (!$user or !password_verify($user->getSalt() . $authData->password, $user->getPassword())) {
                throw new \Exception("ongeldige emailadres en wachtwoord combinatie");
            }

            /*if ( !$user->getActive() ) {
             throw new \Exception( "activeer eerst je account met behulp van de link in je ontvangen email", E_ERROR );
             }*/

            $authItem = new AuthItem($this->authService->createToken($user), $user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function passwordreset(Request $request, Response $response, $args): Response
    {
        try {
            /** @var stdClass $paswordResetData */
            $paswordResetData = $this->getFormData($request);
            if (property_exists($paswordResetData, "emailaddress") === false) {
                throw new \Exception("geen emailadres ingevoerd");
            }
            $emailAddress = strtolower(trim($paswordResetData->emailaddress));

            $retVal = $this->authService->sendPasswordCode($emailAddress);

            $data = ["retval" => $retVal];
            $json = $this->serializer->serialize($data, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }

    public function passwordchange(Request $request, Response $response, $args): Response
    {
        try {
            /** @var stdClass $paswordChangeData */
            $paswordChangeData = $this->getFormData($request);
            if (property_exists($paswordChangeData, "emailaddress") === false) {
                throw new \Exception("geen emailadres ingevoerd");
            }
            if (property_exists($paswordChangeData, "password") === false) {
                throw new \Exception("geen wachtwoord ingevoerd");
            }
            if (property_exists($paswordChangeData, "code") === false) {
                throw new \Exception("geen code ingevoerd");
            }
            $emailAddress = $emailAddress = strtolower(trim($paswordChangeData->emailaddress));
            $password = $paswordChangeData->password;
            $code = (string)$paswordChangeData->code;

            $user = $this->authService->changePassword($emailAddress, $password, $code);

            $authItem = new AuthItem($this->authService->createToken($user), $user->getId());
            return $this->respondWithJson($response, $this->serializer->serialize($authItem, 'json'));
        } catch (\Exception $e) {
            return new ErrorResponse($e->getMessage(), 422);
        }
    }
}
