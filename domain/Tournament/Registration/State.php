<?php

declare(strict_types=1);

namespace FCToernooi\Tournament\Registration;

enum State : string {
    case Created = 'Created';
    case Accepted = 'Accepted';
    case Substitute = 'Substitute';
    case Declined = 'Declined';
}
