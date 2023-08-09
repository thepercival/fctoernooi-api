<?php

namespace App\ImageService;

enum Entity: string
{
    case Competitor = 'competitors';
    case Sponsor = 'sponsors';
    case Tournament = 'tournaments';
}