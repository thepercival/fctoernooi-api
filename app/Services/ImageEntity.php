<?php

namespace App\Services;

/**
 * @api
 */
enum ImageEntity: string
{
    case Competitor = 'competitors';
    case Sponsor = 'sponsors';
    case Tournament = 'tournaments';
}