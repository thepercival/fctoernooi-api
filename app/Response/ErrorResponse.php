<?php
declare(strict_types=1);

namespace App\Response;

use Slim\Psr7\Headers;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;

class ErrorResponse extends Response
{
    public function __construct(string $message, int $status)
    {
        $headers = new Headers;
        $headers->setHeader("Content-type", "application/json");

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
