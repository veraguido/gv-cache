<?php

namespace Gvera\Cache;

use Gvera\Exceptions\InvalidArgumentException;
use Gvera\Helpers\config\Config;
use Predis\Client;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionException;
use Throwable;
use Traversable;

class CacheItemPool implements CacheItemPoolInterface
{
    const INSTRUCTIONS = ['save', 'delete'];


    private array $pool = [];
    private array $bus = [];
    private $config;
    private RedisPoolableClientCache $poolCacheClient;

    /**
     * CacheItemPool constructor.
     * @param Config $config
     * @throws ReflectionException
     */
    public function __construct(Config $config)
    {
        $this->config = $config->getConfigItem('redis');
        $redisConfig = [
            "scheme" => "tcp",
            "host" => $this->config["host"],
            "port" => $this->config["port"],
            "cluster" => $this->config["cluster"]
        ];
        $clientCacheBufferSize = $this->config["pool_size"];

        $this->poolCacheClient = new RedisPoolableClientCache(
            $clientCacheBufferSize,
            $redisConfig
        );
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key
     *   The key for which to return the corresponding Cache Item.
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return CacheItemInterface
     *   The corresponding Cache Item.
     */
    public function getItem($key)
    {
        try {
            $client = $this->poolCacheClient->nextClient();
            $item = new CacheItem($key);
            $rawItem = $client->get($key);
            if (isset($rawItem)){
                $item->set(unserialize($rawItem));
            } 
        } catch (Throwable $t) {
            throw new InvalidArgumentException('something went wrong retrieving a cache item', [$key]);
        }
        return $item;
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param array $keys
     * An indexed array of keys of items to retrieve.
     *
     * @return array|Traversable
     *   A traversable collection of Cache Items keyed by the cache keys of
     *   each item. A Cache item will be returned for each key, even if that
     *   key is not found. However, if no keys are specified then an empty
     *   traversable MUST be returned instead.
     *@throws InvalidArgumentException
     *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     */
    public function getItems(array $keys = array())
    {
        try {
            $collectedItems = [];
            foreach ($keys as $itemKey) {
                array_push($collectedItems, $this->getItem($itemKey));
            }
        } catch (Throwable $t) {
            throw new InvalidArgumentException('something went wrong getting items from cache');
        }

        return $collectedItems;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons.
     * This could result in a race condition with CacheItemInterface::get(). To avoid
     * such situation use CacheItemInterface::isHit() instead.
     *
     * @param string $key
     *    The key for which to check existence.
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *  True if item exists in the cache, false otherwise.
     */
    public function hasItem($key): bool
    {
        if (array_key_exists($key, $this->pool)) {
            return true;
        }

        $item = $this->getItem($key);
        return $item->get() != false;
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     *   True if the pool was successfully cleared. False if there was an error.
     */
    public function clear(): bool
    {
        $this->pool = [];
        $this->bus = [];
        return $this->commit();
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     *   The key for which to delete
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if the item was successfully removed. False if there was an error.
     */
    public function deleteItem($key): bool
    {
        if (!isset($this->pool[$key])) {
            return true;
        }

        if (!$this->pool[$key]) {
            throw new InvalidArgumentException('key does not exist in cache', ['key' => $key]);
        }

        $this->bus['delete'] = [$key => ''] ;
        unset($this->pool[$key]);
        return $this->commit();
    }
    /**
 * Removes multiple items from the pool.
 *
 * @param array $keys
 *   An array of keys that should be removed from the pool.
 * @throws InvalidArgumentException
 *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
 *   MUST be thrown.
 *
 * @return bool
 *   True if the items were successfully removed. False if there was an error.
 */
    public function deleteItems(array $keys): bool
    {
        if (!isset($this->bus['delete'])) {
            $this->bus['delete'] = [];
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->pool)) {
                throw new InvalidArgumentException("there's one corrupted item passed as an argument", [$key]);
            }

            array_push($this->bus['delete'], $this->pool[$key]);
            unset($this->pool[$key]);
        }

        return $this->commit();
    }

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   True if the item was successfully persisted. False if there was an error.
     */
    public function save(CacheItemInterface $item): bool
    {
        $this->pool[$item->getKey()] = $item;
        $this->bus['save'] = [$item->getKey() => $item];
        return $this->commit();
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   False if the item could not be queued or if a commit was attempted and failed. True otherwise.
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        array_push($this->pool[$item->getKey()], $item);
        $this->bus['save'] = [$item->getKey() => $item];
        return true;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     *   True if all not-yet-saved items were successfully saved or there were none. False otherwise.
     */
    public function commit(): bool
    {
        $success = false;
        $client = $this->poolCacheClient->nextClient();
        try {
            $client->connect();
            $this->executeBusInstructions($client);

            $success = true;
        } catch (Throwable $t) {
        } finally {
            $this->bus = [];
            $client->disconnect();
        }
        return $success;
    }

    public function deleteAll()
    {
        $client = $this->poolCacheClient->nextClient();
        $client->connect();
        $client->flushall();
    }

    /**
     * @param $client
     */
    private function executeBusInstructions($client)
    {
        foreach (self::INSTRUCTIONS as $instruction) {
            if (!isset($this->bus[$instruction])) {
                continue;
            }

            $this->executeBusInstruction($instruction, $client);
        }
    }

    /**
     * @param string $instruction
     * @param $client
     */
    private function executeBusInstruction(string $instruction, $client)
    {
        foreach ($this->bus[$instruction] as $keyToChange => $itemToChange) {
            if ('save' === $instruction) {
                $this->setItemWithExpirationTime($keyToChange, $itemToChange, $client);
            }

            if ('delete' === $instruction) {
                $this->delete($keyToChange, $client);
            }
        }
    }

    /**
     * @param $itemKey
     * @param $item
     * @param $client
     */
    private function setItemWithExpirationTime($itemKey, $item, $client)
    {
        $client->set($itemKey, serialize($item->get()));
        if ($item->getExpirationTime()) {
            $client->expire($itemKey, $item->getExpirationTime());
        }
    }

    /**
     * @param $itemKey
     * @param $client
     */
    private function delete($itemKey, Client $client)
    {
        $client->del($itemKey);
    }
}
