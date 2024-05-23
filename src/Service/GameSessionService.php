<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class GameSessionService
{
    public function __construct(private CacheItemPoolInterface $cache)
    {}

    public function generateGameId(): string
    {
        $bytes = random_bytes(4);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8);
    }

    public function startNewGame(string $gameId, array $gameData): void
    {
        $cacheItem = $this->cache->getItem($gameId);
        $cacheItem->set($gameData);
        $this->cache->save($cacheItem);
    }

    public function getGameData(string $gameId): ?array
    {
        $cacheItem = $this->cache->getItem($gameId);
//        if ($cacheItem->isHit()) {
//            return null;
//        }
        return $cacheItem->get();
    }

    public function updateGameData(string $gameId, array $gameData): void
    {
        $cacheItem = $this->cache->getItem($gameId);
        $cacheItem->set($gameData);
        $this->cache->save($cacheItem);
    }

    public function deleteGameData(string $gameId): void
    {
        $cacheItem = $this->cache->deleteItem($gameId);
    }

    public function addRoom(string $gameId, bool $isPrivate): void
    {
        $cacheItem = $this->cache->getItem($isPrivate ? 'roomPrivate' : 'roomPublic');
        $array = $cacheItem->get();
        $array[] = $gameId;
        $cacheItem->set($array);
        $this->cache->save($cacheItem);
    }

    public function findRoom($isPrivate): array
    {
        $cacheItem = $this->cache->getItem($isPrivate ? 'roomPrivate' : 'roomPublic');
        return $cacheItem->get() ?? [];
    }

    public function deleteRoom(string $gameId, $isPrivate): void
    {
        $cacheItem = $this->cache->getItem('roomPublic');
        $array = $cacheItem->get() ?? [];
        $key = array_search($gameId, $array);
        if ($key !== false) {
            unset($array[$key]);
            $cacheItem->set($array);
            $this->cache->save($cacheItem);
        }
    }

}