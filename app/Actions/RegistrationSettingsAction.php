<?php

declare(strict_types=1);

namespace App\Actions;

use App\Repositories\TournamentRegistrationSettingsRepository;
use App\Response\ErrorResponse;
use App\Response\ForbiddenResponse as ForbiddenResponse;
use Doctrine\ORM\EntityManagerInterface;
use FCToernooi\Tournament;
use FCToernooi\Tournament\Registration\TextSubject;
use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;

/**
 * @api
 */
final class RegistrationSettingsAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private TournamentRegistrationSettingsRepository $settingsRepos,
        /*private ImageService $imageService,*/
        private Configuration $config,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct($logger, $serializer);
    }

    /**
     * @psalm-suppress UnusedParam
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

            $settings = $this->settingsRepos->findOneBy(['tournament' => $tournament]);
            if ($settings === null) {
                $settings = $this->settingsRepos->saveDefault($tournament);
            }
            $json = $this->serializer->serialize($settings, 'json');
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
    public function edit(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            /** @var Tournament\RegistrationSettings $settingsSer */
            $settingsSer = $this->serializer->deserialize($this->getRawData($request), Tournament\RegistrationSettings::class, 'json');

            $settings = $this->settingsRepos->find((int)$args['settingsId']);
            if ($settings === null) {
                throw new \Exception("geen inschrijfinstellingen met het opgegeven id gevonden", E_ERROR);
            }
            if ($settings->getTournament() !== $tournament) {
                return new ForbiddenResponse("het toernooi komt niet overeen met het toernooi van de inschrijfinstellingen");
            }

            $settings->setEnabled($settingsSer->getEnabled());
            $settings->setEndDateTime($settingsSer->getEndDateTime());
            $settings->setMailAlert($settingsSer->getMailAlert());
            $settings->setRemark($settingsSer->getRemark());
            $this->entityManager->persist($settings);
            $this->entityManager->flush();

            $json = $this->serializer->serialize($settings, 'json');
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
    public function fetchOneText(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $settings = $this->settingsRepos->findOneBy(['tournament' => $tournament]);
            if ($settings === null) {
                $settings = $this->settingsRepos->saveDefault($tournament);
            }
            $subject = TextSubject::from((int)$args['subject']);
            $text = $settings->getText( $subject );
            if( $text == null ) {
                $text = $this->getDefaultText($subject);
                $settings->setText($subject, $text);
                $this->entityManager->persist($settings);
                $this->entityManager->flush();
            }
            return $this->respondWithPlainText($response, $text);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
        }
    }

    protected function getDefaultText(TextSubject $subject): string {
        $language = 'nl-NL';
        $path = realpath($this->config->getString('email.textTemplatesPath'));

        if( $path === false ) {
            throw new \Exception('no default text could be found', E_ERROR);
        }

        $path .= '/';

        $file = $path . strtolower($subject->name) . '.' . $language . '.txt';

        if( file_exists($file) === false ) {
            throw new \Exception('no default text could be found', E_ERROR);
        }

        $retVal = file_get_contents($file);
        if( $retVal === false ) {
            throw new \Exception('no default text could be found', E_ERROR);
        }
        return $retVal;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function editText(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var Tournament $tournament */
            $tournament = $request->getAttribute("tournament");

            $settings = $this->settingsRepos->findOneBy(['tournament' => $tournament]);
            if ($settings === null) {
                throw new \Exception('no settings could be found', E_ERROR);
            }
            $subject = TextSubject::from((int)$args['subject']);
            $text = $this->getRawData($request);
            $settings->setText($subject, $text);
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
            return $this->respondWithPlainText($response, $text);
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400, $this->logger);
        }
    }

}
