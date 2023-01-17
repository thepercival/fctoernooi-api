<?php

namespace App\Commands;

use Symfony\Component\Console\Input\InputInterface;

trait InputHelper {

    protected function getIntInput(InputInterface $input, string $paramName, int|null $defaultValue = null): int
    {
        $value = $input->getOption($paramName);
        if (!is_string($value) || strlen($value) === 0) {
            if( $defaultValue !== null) {
                return $defaultValue;
            }
            throw new \Exception('incorrect "'.$paramName.'" => "' . $value . '"', E_ERROR);
        }
        return (int)$value;
    }
}