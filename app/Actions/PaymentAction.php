<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\CreditAction\Name;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\Payment;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use JMS\Serializer\SerializerInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\PaymentMethod;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;

final class PaymentAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private UserRepository $userRepos,
        private CreditActionRepository $creditActionRepos,
        private AuthSyncService $syncService,
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
    public function fetchIDealMethods(Request $request, Response $response, array $args): Response
    {
        try {
            $mollie = new MollieApiClient();
            $methods = $mollie->methods->get(PaymentMethod::IDEAL, ['include' => 'issuers']);

            // $json = $this->serializer->serialize($tournament->getSponsors(), 'json');
            return $this->respondWithJson($response, json_encode($methods));
        } catch (\Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function buyCredits(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $user */
            $user = $request->getAttribute('user');

            /** @var Payment $payment */
            $payment = $this->serializer->deserialize(
                $this->getRawData($request),
                Payment::class,
                'json'
            );

            if ($user->getId() !== $payment->getUser()->getId()) {
                throw new Exception('de ingelogde gebruiker en de aan te passen gebruiker zijn verschillend', E_ERROR);
            }

            $amount = $payment->getNrOfCredits() * Payment::EurosPerCredit;

            $mollie = new MollieApiClient();
            $mollie->setApiKey($this->config->getString('payment.apikey'));

            $molliePayment = $mollie->payments->create([
                                                           'amount' => [
                                                               'currency' => 'EUR',
                                                               'value' => '' . round($amount, 2)
                                                           ],
                                                           'description' => 'API payment user ' . (string)$user->getId(
                                                               ),
                                                           'redirectUrl' => $this->config->getString(
                                                               'payment.redirectUrl'
                                                           ),
                                                           'webhookUrl' => $this->config->getString(
                                                               'payment.webhookUrl'
                                                           )
                                                       ]);

            $creditAction = $this->creditActionRepos->doAction($user, Name::Buy, $payment->getNrOfCredits());
            $creditAction->setPaymentId($molliePayment->id);
            $this->creditActionRepos->save($creditAction, true);

            // header("Location: " . $molliePayment->getCheckoutUrl(), true, 303);

            return $this->respondWithJson(
                $response,
                $this->serializer->serialize(
                    $user,
                    'json'
                )
            );
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
    public function remove(Request $request, Response $response, array $args): Response
    {
        try {
            /** @var User $userAuth */
            $userAuth = $request->getAttribute('user');

            $user = $this->userRepos->find((int)$args['userId']);
            if ($user === null || $userAuth->getId() !== $user->getId()) {
                throw new Exception(
                    'de ingelogde gebruiker en de te verwijderen gebruiker zijn verschillend',
                    E_ERROR
                );
            }

            $this->syncService->revertTournamentUsers($userAuth);

            $this->userRepos->remove($user);
            return $response->withStatus(200);
        } catch (Exception $exception) {
            return new ErrorResponse($exception->getMessage(), 422);
        }
    }
}
