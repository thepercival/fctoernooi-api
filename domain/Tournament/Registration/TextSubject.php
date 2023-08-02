<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Registration;

enum TextSubject: int
{
    case Accept = 1;
    case AcceptAsSubstitute = 2;
    case Decline = 3;
}