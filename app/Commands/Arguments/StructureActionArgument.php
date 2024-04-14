<?php

namespace App\Commands\Arguments;

enum StructureActionArgument: string
{
    case Show = 'show';
    case Validate = 'validate';
}
