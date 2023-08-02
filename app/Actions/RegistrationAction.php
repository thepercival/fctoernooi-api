<?php

declare(strict_types=1);

namespace App\Actions;

use App\ImageService;
use App\Mailer;
use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use FCToernooi\Role;
use FCToernooi\Sponsor;
use FCToernooi\Tournament;
use FCToernooi\Tournament\RegistrationSettings\Repository as TournamentRegistrationSettingsRepository;
use FCToernooi\User;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use FCToernooi\Tournament\Registration as TournamentRegistration;
use FCToernooi\Tournament\Registration\Repository as TournamentRegistrationRepository;
use Slim\Views\Twig as TwigView;
use Sports\Category\Repository as CategoryRepository;
use Sports\Category;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;

final class RegistrationAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRegistrationRepository $registrationRepos,
        private TournamentRegistrationSettingsRepository $settingsRepos,
        private CategoryRepository $categoryRepos,
        protected Mailer $mailer,
        private TwigView $view,
        private Configuration $configuration
    ) {
        parent::__construct($logger, $serializer);
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

            $registration = $this->registrationRepos->find($args['registrationId']);
            if ($registration === null) {
                throw new \Exception("geen registratie  met het opgegeven id gevonden", E_ERROR);
            }
            if ($registration->getTournament() !== $tournament) {
                throw new \Exception("de registratie is van een ander toernooi", E_ERROR);
            }

            $json = $this->serializer->serialize($registration, 'json');
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
    public function fetch(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $category = $this->categoryRepos->find((int)$args['categoryId']);
            if ($category === null) {
                throw new \Exception("geen categorie met het opgegeven id gevonden", E_ERROR);
            }

            $registrations = $this->registrationRepos->findByCategoryNr($tournament, $category->getNumber() );

            $json = $this->serializer->serialize($registrations, 'json');
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

            $category = $this->categoryRepos->find((int)$args['categoryId']);
            if ($category === null) {
                throw new \Exception("geen categorie met het opgegeven id gevonden", E_ERROR);
            }
            /** @var TournamentRegistration $serRegistration */
            $serRegistration = $this->serializer->deserialize($this->getRawData($request), TournamentRegistration::class, 'json');

            $newRegistration = new TournamentRegistration(
                $tournament,
                $category->getNumber(),
                $serRegistration->getName(),
                $serRegistration->getEmailaddress(),
                $serRegistration->getTelephone(),
                $serRegistration->getInfo()
            );
            $this->registrationRepos->save($newRegistration);

            $settings = $this->settingsRepos->findOneBy(['tournament' => $tournament]);
            if( $settings && $settings->getMailAlert() ) {
                $this->sendMailAlert($newRegistration);
            }

            $json = $this->serializer->serialize($newRegistration, 'json');
            return $this->respondWithJson($response, $json);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }

    protected function sendMailAlert(TournamentRegistration $newRegistration): void
    {
        $tournament = $newRegistration->getTournament();
        $tournamentName = $tournament->getName();
        $url = $this->configuration->getString('www.wwwurl');
        $url .= 'admin/competitors/' . ((string)$tournament->getId()) . '/3';

        $content = $this->view->fetch(
            'newregistration.twig',
            [
                'name' => $newRegistration->getName(),
                'url' => $url
            ]
        );

        foreach( $tournament->getUsers() as $tournamentUser ) {
            if( !$tournamentUser->hasARole(Role::ADMIN) ) {
                continue;
            }
            $this->mailer->send('nieuwe registratie voor toernooi "' . $tournamentName . '"', $content, $tournamentUser->getUser()->getEmailaddress(), false);
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

            /** @var TournamentRegistration $registrationSer */
            $registrationSer = $this->serializer->deserialize($this->getRawData($request), TournamentRegistration::class, 'json');

            $registration = $this->registrationRepos->find((int)$args['registrationId']);
            if ($registration === null) {
                throw new \Exception("geen registration met het opgegeven id gevonden", E_ERROR);
            }
            if ($registration->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de registration");
            }


            $registration->setName($registrationSer->getName());
            $registration->setState($registrationSer->getState());
            $registration->setEmailaddress($registrationSer->getEmailaddress());
            $registration->setTelephone($registrationSer->getTelephone());
            $registration->setInfo($registrationSer->getInfo());
            $this->registrationRepos->save($registration);

            $json = $this->serializer->serialize($registration, 'json');
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

            $registration = $this->registrationRepos->find((int)$args['registrationId']);
            if ($registration === null) {
                throw new \Exception("geen registratie met het opgegeven id gevonden", E_ERROR);
            }
            if ($registration->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de registratie");
            }

            $this->registrationRepos->remove($registration);

            return $response->withStatus(200);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422, $this->logger);
        }
    }
    
}
