<?php

declare(strict_types=1);

namespace App\Actions;

use App\Response\ErrorResponse;
use Exception;
use FCToernooi\Auth\SyncService as AuthSyncService;
use FCToernooi\CreditAction\Repository as CreditActionRepository;
use FCToernooi\Payment;
use FCToernooi\Payment\CreditCard as CreditCardPayment;
use FCToernooi\Payment\IDeal as IDealPayment;
use FCToernooi\Payment\IDealIssuer;
use FCToernooi\Payment\Repository as PaymentRepository;
use FCToernooi\User;
use FCToernooi\User\Repository as UserRepository;
use JMS\Serializer\SerializerInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Types\PaymentMethod;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Selective\Config\Configuration;
use stdClass;

final class PaymentAction extends Action
{
    private LoggerInterface $paymentLogger;

    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        private UserRepository $userRepos,
        private PaymentRepository $paymentRepos,
        private CreditActionRepository $creditActionRepos,
        private AuthSyncService $syncService,
        private Configuration $config
    ) {
        $this->paymentLogger = $this->initPaymentLogger($config);
        parent::__construct($logger, $serializer);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array<string, int|string> $args
     * @return Response
     */
    public function fetchMethods(Request $request, Response $response, array $args): Response
    {
        try {
            $methods = json_encode([PaymentMethod::IDEAL, PaymentMethod::CREDITCARD]);
            if ($methods === false) {
                throw new \Exception('incorrect payment methods', E_ERROR);
            }

            // $json = $this->serializer->serialize($tournament->getSponsors(), 'json');
            return $this->respondWithJson($response, $methods);
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
    public function fetchIDealIssuers(Request $request, Response $response, array $args): Response
    {
        try {
            $iDeal = $this->getMollieClient()->methods->get(PaymentMethod::IDEAL, ['include' => 'issuers']);
            if (!property_exists($iDeal, 'issuers')) {
                throw new \Exception('could not load idealissuers', E_ERROR);
            }
            /** @psalm-suppress UndefinedPropertyFetch */
            $idealIssuers = $iDeal->issuers;
            $issuers = array_map(function (stdClass $issuer): IDealIssuer {
                $imgUrl = '';
                if (property_exists($issuer, 'image') && property_exists($issuer->image, 'size1X')) {
                    $imgUrl = $issuer->image->size1X;
                }
                return new IDealIssuer($issuer->id, $issuer->name, $imgUrl);
            }, $idealIssuers);

            $json = $this->serializer->serialize($issuers, 'json');
            return $this->respondWithJson($response, $json);
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
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $paymentId = $this->getIdFromRequest($request);
            if ($paymentId === null) {
                $this->paymentLogger->warning('empty paymentId');
                return $response->withStatus(200);
            }

            $payment = $this->paymentRepos->findOneBy(['paymentId' => $paymentId]);
            if ($payment === null) {
                throw new Exception('payment for id "' . $paymentId . '" not found', E_ERROR);
            }

            $molliePayment = $this->getMollieClient()->payments->get($paymentId);

            $logAmount = number_format($payment->getAmount(), 2, '.', '');
            $logUserId = (string)$payment->getUser()->getId();

            if ($payment->getState() !== 'paid' && $molliePayment->status === 'paid') {
                $payment->setState($molliePayment->status);
                $this->paymentRepos->save($payment, true);

                $this->creditActionRepos->buyCredits($payment);

                $this->paymentLogger->info(
                    'payment to state ' . $molliePayment->status . ' for user ' . $logUserId . ' with amount ' . $logAmount
                );
            }

            if ($payment->getState() === 'paid' && $molliePayment->status !== 'paid') {
                $this->creditActionRepos->cancelCredits($payment);

                $payment->setState($molliePayment->status);
                $this->paymentRepos->save($payment, true);

                $this->paymentLogger->info(
                    'payment to state ' . $molliePayment->status . ' for user ' . $logUserId . ' with amount ' . $logAmount
                );
            }
        } catch (Exception $e) {
            $this->paymentLogger->error($e->getMessage());
        }
        return $response->withStatus(200);
    }

    protected function getIdFromRequest(Request $request): string|null
    {
        $rawData = $this->getRawData($request);
        if (mb_strlen($rawData) === 0) {
            $this->paymentLogger->warning('empty paymentId');
            return null;
        }
        $keyValue = explode('=', $rawData);
        if (count($keyValue) !== 2) {
            throw new \ErrorException('wrong paymentId');
        }
        if (array_shift($keyValue) !== 'id') {
            throw new \ErrorException('wrong paymentId');
        }
        return array_shift($keyValue);
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

            $queryParams = $request->getQueryParams();

            $method = null;
            if (array_key_exists('method', $queryParams)) {
                $method = $queryParams['method'];
            }

            if ($method === PaymentMethod::IDEAL) {
                /** @var IDealPayment $payment */
                $serPayment = $this->serializer->deserialize(
                    $this->getRawData($request),
                    IDealPayment::class,
                    'json'
                );
            } elseif ($method === PaymentMethod::CREDITCARD) {
                /** @var CreditCardPayment $payment */
                $serPayment = $this->serializer->deserialize(
                    $this->getRawData($request),
                    CreditCardPayment::class,
                    'json'
                );
            } else {
                throw new \Exception('unknown paymentmethod', E_ERROR);
            }

            $molliePaymentOptions = [
                'amount' => [
                    'value' => number_format($serPayment->getAmount(), 2, '.', ''),
                    'currency' => 'EUR'
                ],
                'description' => 'API payment user ' . (string)$user->getId(),
                'redirectUrl' => $this->config->getString('payment.webhookUrl'),
                'webhookUrl' => $this->config->getString('payment.webhookUrl'),
                'method' => $method
            ];

            if ($method === PaymentMethod::IDEAL) {
                $molliePaymentOptions['issuer'] = $serPayment->getIssuer()->getId();
            } // elseif ($method === PaymentMethod::CREDITCARD) {
            // $molliePaymentOptions['issuer'] = $serPayment->getIssuer()->getId();
            // }

            $molliePayment = $this->getMollieClient()->payments->create($molliePaymentOptions);

            $payment = new Payment($user, $molliePayment->id, PaymentMethod::IDEAL, $serPayment->getAmount());
            $this->paymentRepos->save($payment, true);

            return $this->respondWithJson(
                $response,
                $this->serializer->serialize(
                    ['checkoutUrl' => $molliePayment->getCheckoutUrl()],
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

    protected function getMollieClient(): MollieApiClient
    {
        $mollie = new MollieApiClient();
        $mollie->setApiKey($this->config->getString('payment.mollie.apikey'));
        return $mollie;
    }

    public function initPaymentLogger(Configuration $config): LoggerInterface
    {
        $loggerSettings = $config->getArray('logger');
        $name = 'payment';
        $logger = new Logger($name);

        $processor = new UidProcessor();
        $logger->pushProcessor($processor);

        $loggerPath = $config->getString('logger.path') . $name . '.log';

        $handler = new StreamHandler($loggerPath, $loggerSettings['level']);
        $logger->pushHandler($handler);

        return $logger;
    }
}
