<?php

declare(strict_types=1);

namespace App\Response;

use Psr\Log\LoggerInterface;
use Slim\Psr7\Headers;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;

class ErrorResponse extends Response
{
    public function __construct(string $message, int $status, LoggerInterface $logger = null)
    {
        // logger->error('HTTPSTATUS: ' . $status . ' => ' . $message);
        $headers = new Headers();
        $headers->setHeader("Content-type", "application/json");
        if ($logger !== null) {
            $logger->error($message);
        }

        $body = null;
        $handle = fopen("php://temp", "wb+");
        if ($handle !== false) {
            $body = new Stream($handle);
            $json = json_encode(["message" => $message]/*, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT*/);
            if (is_string($json)) {
                $body->write($json);
            }
        }
        parent::__construct($status, $headers, $body);
    }
}
