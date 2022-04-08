<?php

declare(strict_types=1);

namespace App\Renderer;

use Slim\Error\Renderers\PlainTextErrorRenderer;

class ErrorRenderer extends PlainTextErrorRenderer
{
    /**
     * @param \Throwable $exception
     * @param bool $displayErrorDetails
     * @return string
     */
    public function __invoke(\Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            return parent::__invoke($exception, $displayErrorDetails);
        }

        return $exception->getMessage();
    }
}