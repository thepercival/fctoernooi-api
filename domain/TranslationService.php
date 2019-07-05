<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 5-7-19
 * Time: 11:18
 */

namespace FCToernooi;

use Voetbal\Sport\Custom as SportCustom;
use Voetbal\Sport\ScoreConfig as SportScoreConfig;
use Voetbal\Sport;

class TranslationService {

    const language = 'nl';

    public function getSportName( string $language, int $customId): string {
        switch ($customId) {
            case SportCustom::Badminton: { return 'badminton'; }
            case SportCustom::Basketball: { return 'basketbal'; }
            case SportCustom::Darts: { return 'darten'; }
            case SportCustom::ESports: { return 'e-sporten'; }
            case SportCustom::Hockey: { return 'hockey'; }
            case SportCustom::Korfball: { return 'korfbal'; }
            case SportCustom::Chess: { return 'schaken'; }
            case SportCustom::Squash: { return 'squash'; }
            case SportCustom::TableTennis: { return 'tafeltennis'; }
            case SportCustom::Tennis: { return 'darten'; }
            case SportCustom::Football: { return 'voetbal'; }
            case SportCustom::Voleyball: { return 'volleybal'; }
            case SportCustom::Baseball: { return 'honkbal'; }
        }
        return '';
    }

    public function getScoreNameSingle(string $language, SportScoreConfig $sportScoreConfig): string {
        $customId = $sportScoreConfig->getSport()->getCustomId();
        if ($sportScoreConfig->getParent() !== null) {
            return $this->getScoreSubSingleName($language, $customId);
        }
        switch ($customId) {
            case SportCustom::Badminton: { return 'set'; }
            case SportCustom::Basketball: { return 'punt'; }
            case SportCustom::Darts: { return 'set'; }
            case SportCustom::ESports: { return 'punt'; }
            case SportCustom::Hockey: { return 'goal'; }
            case SportCustom::Korfball: { return 'punt'; }
            case SportCustom::Chess: { return 'punt'; }
            case SportCustom::Squash: { return 'set'; }
            case SportCustom::TableTennis: { return 'set'; }
            case SportCustom::Tennis: { return 'set'; }
            case SportCustom::Football: { return 'goal'; }
            case SportCustom::Voleyball: { return 'set'; }
            case SportCustom::Baseball: { return 'punt'; }
        }
        return '';
    }

    protected function getScoreSubSingleName(string $language, int $customId): string {
        switch ($customId) {
            case SportCustom::Darts: { return 'leg'; }
            case SportCustom::Tennis: { return 'game'; }
        }
        return '';
    }

    public function getScoreNameMultiple(string $language, SportScoreConfig $sportScoreConfig): string {
        $customId = $sportScoreConfig->getSport()->getCustomId();
        if ($sportScoreConfig->getParent() !== null) {
            return $this->getScoreSubSingleName($language, $customId);
        }
        switch ($customId) {
            case SportCustom::Badminton: { return 'sets'; }
            case SportCustom::Basketball: { return 'punten'; }
            case SportCustom::Darts: { return 'sets'; }
            case SportCustom::ESports: { return 'punten'; }
            case SportCustom::Hockey: { return 'goals'; }
            case SportCustom::Korfball: { return 'punten'; }
            case SportCustom::Chess: { return 'punten'; }
            case SportCustom::Squash: { return 'sets'; }
            case SportCustom::TableTennis: { return 'sets'; }
            case SportCustom::Tennis: { return 'sets'; }
            case SportCustom::Football: { return 'goals'; }
            case SportCustom::Voleyball: { return 'sets'; }
            case SportCustom::Baseball: { return 'punten'; }
        }
        return '';
    }

    protected function getScoreSubNameMultiple(string $language, int $customId): string {
        switch ($customId) {
            case SportCustom::Darts: { return 'legs'; }
            case SportCustom::Tennis: { return 'games'; }
        }
        return '';
    }

    public function  getScoreDirection(string $language, int $direction): string {
        switch ($direction) {
            case SportScoreConfig::UPWARDS: { return 'naar'; }
            case SportScoreConfig::DOWNWARDS: { return 'vanaf'; }
        }
        return '';
    }

    public function getFieldName( string $language, Sport $sport): string {
        $customId = $sport->getCustomId();
        switch ($customId) {
            case SportCustom::Badminton: { return 'veld'; }
            case SportCustom::Basketball: { return 'veld'; }
            case SportCustom::Darts: { return 'bord'; }
            case SportCustom::ESports: { return 'veld'; }
            case SportCustom::Hockey: { return 'veld'; }
            case SportCustom::Korfball: { return 'veld'; }
            case SportCustom::Chess: { return 'bord'; }
            case SportCustom::Squash: { return 'baan'; }
            case SportCustom::TableTennis: { return 'tafel'; }
            case SportCustom::Tennis: { return 'veld'; }
            case SportCustom::Football: { return 'veld'; }
            case SportCustom::Voleyball: { return 'veld'; }
            case SportCustom::Baseball: { return 'veld'; }
        }
        return '';
    }

    public function getFieldsName( string $language, Sport $sport ): string {
        $customId = $sport->getCustomId();
        switch ($customId) {
            case SportCustom::Badminton: { return 'velden'; }
            case SportCustom::Basketball: { return 'velden'; }
            case SportCustom::Darts: { return 'borden'; }
            case SportCustom::ESports: { return 'velden'; }
            case SportCustom::Hockey: { return 'velden'; }
            case SportCustom::Korfball: { return 'velden'; }
            case SportCustom::Chess: { return 'borden'; }
            case SportCustom::Squash: { return 'banen'; }
            case SportCustom::TableTennis: { return 'tafels'; }
            case SportCustom::Tennis: { return 'velden'; }
            case SportCustom::Football: { return 'velden'; }
            case SportCustom::Voleyball: { return 'velden'; }
            case SportCustom::Baseball: { return 'velden'; }
        }
        return '';
    }
}
