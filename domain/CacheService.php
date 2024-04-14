<?php

declare(strict_types=1);

namespace FCToernooi;

use FCToernooi\Planning\RoundNumbersToAssignPlanningTo;
use Memcached;

class CacheService
{
    public const TournamentCacheIdPrefix = 'json-tournament-';
    public const StructureCacheIdPrefix = 'json-structure-';

    public const RoundNumberWithoutPlanningCacheId = 'json-roundNumbers-without-planning';
    public const CacheTime = 86400;

    public function __construct(private MemCached $memCached, private string $namespace)
    {
    }

    public function getTournament(string|int|null $tournamentId): string|false
    {
        if ($tournamentId === null) {
            return false;
        }
        /** @var string|false $memCachedItem */
        $memCachedItem = $this->memCached->get($this->getTournamentCacheId($tournamentId));
        return $memCachedItem;
    }

    private function getTournamentCacheId(string|int $tournamentId): string
    {
        return $this->namespace . '-' . self::TournamentCacheIdPrefix . $tournamentId;
    }

    public function setTournament(string|int $tournamentId, string $json): void
    {
        $this->memCached->set($this->getTournamentCacheId($tournamentId), $json, self::CacheTime);
    }

    public function resetTournament(string|int $tournamentId): void
    {
        $this->memCached->delete($this->getTournamentCacheId($tournamentId));
    }

    public function getStructure(string|int|null $structureId): string|false
    {
        if ($structureId === null) {
            return false;
        }
        /** @var string|false $memCachedItem */
        $memCachedItem = $this->memCached->get($this->getStructureCacheId($structureId));
        return $memCachedItem;
    }

    private function getStructureCacheId(string|int $structureId): string
    {
        return $this->namespace . '-' . self::StructureCacheIdPrefix . $structureId;
    }

    public function setStructure(string|int $structureId, string $json): void
    {
        $this->memCached->set($this->getStructureCacheId($structureId), $json, self::CacheTime);
    }

    public function resetStructure(string|int $structureId): void
    {
        $this->memCached->delete($this->getStructureCacheId($structureId));
    }

    /**
     * @return array<int|string, int|string>
     */
    public function getCompetitionIdsWithoutPlanning(): array
    {
        /** @var array<int|string, int|string>|false $memCachedItem */
        $memCachedItem = $this->memCached->get($this->getCompetitionIdsWithoutPlanningId());
        if( $memCachedItem === false ) {
            $memCachedItem = [];
            $this->memCached->set($this->getCompetitionIdsWithoutPlanningId(), $memCachedItem);
        }
        return $memCachedItem;
    }

    public function addCompetitionIdWithoutPlanning(string|int|null $competitionId): void
    {
        if( $competitionId === null ) {
            return;
        }
        $competitionIdsWithoutPlanning = $this->getCompetitionIdsWithoutPlanning();
        $competitionIdsWithoutPlanning[$competitionId] = $competitionId;
        $this->memCached->set($this->getCompetitionIdsWithoutPlanningId(), $competitionIdsWithoutPlanning);
    }

    public function removeCompetitionIdWithoutPlanning(string|int|null $competitionId): void
    {
        if( $competitionId === null ) {
            return;
        }
        $competitionIdsWithoutPlanning = $this->getCompetitionIdsWithoutPlanning();
        unset($competitionIdsWithoutPlanning[$competitionId]);
        $this->memCached->set($this->getCompetitionIdsWithoutPlanningId(), $competitionIdsWithoutPlanning);
    }

    private function getCompetitionIdsWithoutPlanningId(): string
    {
        return $this->namespace . '-' . self::RoundNumberWithoutPlanningCacheId;
    }
}
