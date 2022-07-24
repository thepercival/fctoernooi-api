<?php

declare(strict_types=1);

namespace FCToernooi\Tournament;

enum StartEditMode: string
{
    case EditLongTerm = 'EditLongTerm';
    case EditShortTerm = 'EditShortTerm';
    case ReadOnly = 'ReadOnly';
}