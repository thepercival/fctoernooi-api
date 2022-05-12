<?php

declare(strict_types=1);

namespace FCToernooi;

use Sports\Score\Config as ScoreConfig;
use Sports\Sport;
use Sports\Sport\Custom as CustomSport;

/**
 * Class TranslationService, translates certain sports-terms
 * @package FCToernooi
 */
class TranslationService
{
    protected const LANGUAGE = 'nl';

    public function getSportName(string $language, int $customId): string
    {
        switch ($customId) {
            case CustomSport::Badminton:
            {
                return 'badminton';
            }
            case CustomSport::Basketball:
            {
                return 'basketbal';
            }
            case CustomSport::Darts:
            {
                return 'darten';
            }
            case CustomSport::ESports:
            {
                return 'e-sporten';
            }
            case CustomSport::Hockey:
            {
                return 'hockey';
            }
            case CustomSport::Korfball:
            {
                return 'korfbal';
            }
            case CustomSport::Chess:
            {
                return 'schaken';
            }
            case CustomSport::Squash:
            {
                return 'squash';
            }
            case CustomSport::TableTennis:
            {
                return 'tafeltennis';
            }
            case CustomSport::Tennis:
            {
                return 'tennis';
            }
            case CustomSport::Football:
            {
                return 'voetbal';
            }
            case CustomSport::Volleyball:
            {
                return 'volleybal';
            }
            case CustomSport::Baseball:
            {
                return 'honkbal';
            }
            case CustomSport::Padel:
            {
                return 'padel';
            }
            case CustomSport::Rugby:
            {
                return 'rugby';
            }
        }
        return '';
    }

    public function getScoreNameSingular(ScoreConfig $scoreConfig): string
    {
        $customId = $scoreConfig->getCompetitionSport()->getSport()->getCustomId();
        if ($scoreConfig->isFirst()) {
            return $this->getFirstScoreNameSingular($customId);
        } elseif ($scoreConfig->isLast()) {
            return $this->getLastScoreNameSingular($customId);
        }
        return '';
    }

    protected function getFirstScoreNameSingular(int $customId): string
    {
        switch ($customId) {
            case CustomSport::Darts:
            {
                return 'leg';
            }
            case CustomSport::Tennis:
            case CustomSport::Padel:
            {
                return 'game';
            }
            case CustomSport::Football:
            case CustomSport::Hockey:
            {
                return 'goal';
            }
        }
        return 'punt';
    }

    protected function getLastScoreNameSingular(int $customId): string
    {
        switch ($customId) {
            case CustomSport::Badminton:
            case CustomSport::Darts:
            case CustomSport::Squash:
            case CustomSport::TableTennis:
            case CustomSport::Tennis:
            case CustomSport::Padel:
            case CustomSport::Volleyball:
            {
                return 'set';
            }
        }
        return '';
    }

    public function getScoreNamePlural(ScoreConfig $scoreConfig): string
    {
        $customId = $scoreConfig->getCompetitionSport()->getSport()->getCustomId();
        if ($scoreConfig->isFirst()) {
            return $this->getFirstScoreNamePlural($customId);
        } else {
            if ($scoreConfig->isLast()) {
                return $this->getLastScoreNamePlural($customId);
            }
        }
        return '';
    }

    protected function getFirstScoreNamePlural(int $customId): string
    {
        switch ($customId) {
            case CustomSport::Darts:
            {
                return 'legs';
            }
            case CustomSport::Tennis:
            case CustomSport::Padel:
            {
                return 'games';
            }
            case CustomSport::Football:
            case CustomSport::Hockey:
            {
                return 'goals';
            }
        }
        return 'punten';
    }

    protected function getLastScoreNamePlural(int $customId): string
    {
        switch ($customId) {
            case CustomSport::Badminton:
            case CustomSport::Darts:
            case CustomSport::Squash:
            case CustomSport::TableTennis:
            case CustomSport::Tennis:
            case CustomSport::Padel:
            case CustomSport::Volleyball:
            {
                return 'sets';
            }
        }
        return '';
    }

    public function getScoreDirection(int $direction): string
    {
        switch ($direction) {
            case ScoreConfig::UPWARDS:
            {
                return 'naar';
            }
            case ScoreConfig::DOWNWARDS:
            {
                return 'vanaf';
            }
        }
        return '';
    }

    public function getFieldNameSingular(Sport $sport): string
    {
        $customId = $sport->getCustomId();
        switch ($customId) {
            case CustomSport::Badminton:
            case CustomSport::Baseball:
            case CustomSport::Basketball:
            case CustomSport::ESports:
            case CustomSport::Hockey:
            case CustomSport::Korfball:
            case CustomSport::Volleyball:
            case CustomSport::Football:
            case CustomSport::Rugby:
                return 'veld';
            case CustomSport::Darts:
            case CustomSport::Chess:
                return 'bord';
            case CustomSport::Squash:
            case CustomSport::Tennis:
            case CustomSport::Padel:
                return 'baan';
            case CustomSport::TableTennis:
                return 'tafel';
        }
        return '';
    }

    public function getFieldNamePlural(Sport $sport): string
    {
        $customId = $sport->getCustomId();
        switch ($customId) {
            case CustomSport::Badminton:
            case CustomSport::Baseball:
            case CustomSport::Basketball:
            case CustomSport::ESports:
            case CustomSport::Hockey:
            case CustomSport::Korfball:
            case CustomSport::Volleyball:
            case CustomSport::Football:
            case CustomSport::Rugby:
                return 'velden';
            case CustomSport::Darts:
            case CustomSport::Chess:
                return 'borden';
            case CustomSport::Squash:
            case CustomSport::Tennis:
            case CustomSport::Padel:
                return 'banen';
            case CustomSport::TableTennis:
                return 'tafels';
        }
        return '';
    }
}
