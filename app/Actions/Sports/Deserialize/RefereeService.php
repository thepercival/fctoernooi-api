<?php

declare(strict_types=1);

namespace App\Actions\Sports\Deserialize;

use Sports\Round\Number as RoundNumber;
use Sports\Place;

class RefereeService
{
    /**
     * @var array<string|int, Place>|null
     */
    protected array|null $roundNumberPlaceMap = null;

    public function getPlace(RoundNumber $roundNumber, int $placeId): ?Place
    {
        if ($this->roundNumberPlaceMap === null) {
            $this->roundNumberPlaceMap = $this->getPlaceMap($roundNumber);
        }
        if (array_key_exists($placeId, $this->roundNumberPlaceMap) === false) {
            return null;
        }
        return $this->roundNumberPlaceMap[$placeId];
    }

    /**
     * @param RoundNumber $roundNumber
     * @return array<string|int, Place>
     */
    protected function getPlaceMap(RoundNumber $roundNumber): array
    {
        $roundNumberPlaces = [];
        foreach ($roundNumber->getPoules() as $poule) {
            foreach ($poule->getPlaces() as $place) {
                $placeId = $place->getId();
                if($placeId !== null) {
                    $roundNumberPlaces[$placeId] = $place;
                }
            }
        }
        return $roundNumberPlaces;
    }
}
