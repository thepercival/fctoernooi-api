<?php

declare(strict_types=1);

namespace FCToernooi;

use Sports\Score\Config as ScoreConfig;
use Sports\Sport;
use Sports\Sport\Custom as SportCustom;

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
            case SportCustom::Badminton:
            {
                return 'badminton';
            }
            case SportCustom::Basketball:
            {
                return 'basketbal';
            }
            case SportCustom::Darts:
            {
                return 'darten';
            }
            case SportCustom::ESports:
            {
                return 'e-sporten';
            }
            case SportCustom::Hockey:
            {
                return 'hockey';
            }
            case SportCustom::Korfball:
            {
                return 'korfbal';
            }
            case SportCustom::Chess:
            {
                return 'schaken';
            }
            case SportCustom::Squash:
            {
                return 'squash';
            }
            case SportCustom::TableTennis:
            {
                return 'tafeltennis';
            }
            case SportCustom::Tennis:
            {
                return 'tennis';
            }
            case SportCustom::Football:
            {
                return 'voetbal';
            }
            case SportCustom::Volleyball:
            {
                return 'volleybal';
            }
            case SportCustom::Baseball:
            {
                return 'honkbal';
            }
            case SportCustom::Padel:
            {
                return 'padel';
            }
            case SportCustom::Rugby:
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
            case SportCustom::Darts:
            {
                return 'leg';
            }
            case SportCustom::Tennis:
            case SportCustom::Padel:
            {
                return 'game';
            }
            case SportCustom::Football:
            case SportCustom::Hockey:
            {
                return 'goal';
            }
        }
        return 'punt';
    }

    protected function getLastScoreNameSingular(int $customId): string
    {
        switch ($customId) {
            case SportCustom::Badminton:
            case SportCustom::Darts:
            case SportCustom::Squash:
            case SportCustom::TableTennis:
            case SportCustom::Tennis:
            case SportCustom::Padel:
            case SportCustom::Volleyball:
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
            case SportCustom::Darts:
            {
                return 'legs';
            }
            case SportCustom::Tennis:
            case SportCustom::Padel:
            {
                return 'games';
            }
            case SportCustom::Football:
            case SportCustom::Hockey:
            {
                return 'goals';
            }
        }
        return 'punten';
    }

    protected function getLastScoreNamePlural(int $customId): string
    {
        switch ($customId) {
            case SportCustom::Badminton:
            case SportCustom::Darts:
            case SportCustom::Squash:
            case SportCustom::TableTennis:
            case SportCustom::Tennis:
            case SportCustom::Padel:
            case SportCustom::Volleyball:
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
            case SportCustom::Badminton:
            case SportCustom::Baseball:
            case SportCustom::Basketball:
            case SportCustom::ESports:
            case SportCustom::Hockey:
            case SportCustom::Korfball:
            case SportCustom::Volleyball:
            case SportCustom::Football:
            case SportCustom::Rugby:
                return 'veld';
            case SportCustom::Darts:
            case SportCustom::Chess:
                return 'bord';
            case SportCustom::Squash:
            case SportCustom::Tennis:
            case SportCustom::Padel:
                return 'baan';
            case SportCustom::TableTennis:
                return 'tafel';
        }
        return '';
    }

    public function getFieldNamePlural(Sport $sport): string
    {
        $customId = $sport->getCustomId();
        switch ($customId) {
            case SportCustom::Badminton:
            case SportCustom::Baseball:
            case SportCustom::Basketball:
            case SportCustom::ESports:
            case SportCustom::Hockey:
            case SportCustom::Korfball:
            case SportCustom::Volleyball:
            case SportCustom::Football:
            case SportCustom::Rugby:
                return 'velden';
            case SportCustom::Darts:
            case SportCustom::Chess:
                return 'borden';
            case SportCustom::Squash:
            case SportCustom::Tennis:
            case SportCustom::Padel:
                return 'banen';
            case SportCustom::TableTennis:
                return 'tafels';
        }
        return '';
    }
}
