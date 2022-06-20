<?php

declare(strict_types=1);

namespace FCToernooi;

use Memcached;

class CacheService
{
    public const TournamentCacheIdPrefix = 'json-tournament-';
    public const StructureCacheIdPrefix = 'json-structure-';
    public const CacheTime = 86400;

    public function __construct(private MemCached $memCached)
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
        return self::TournamentCacheIdPrefix . $tournamentId;
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
        return self::StructureCacheIdPrefix . $structureId;
    }

    public function setStructure(string|int $structureId, string $json): void
    {
        $this->memCached->set($this->getStructureCacheId($structureId), $json, self::CacheTime);
    }

    public function resetStructure(string|int $structureId): void
    {
        $this->memCached->delete($this->getStructureCacheId($structureId));
    }
}
