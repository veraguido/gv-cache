<?php namespace Gvera\Cache;

use Exception;
use Gvera\Exceptions\InvalidArgumentException;
use Gvera\Helpers\config\Config;
use ReflectionException;

/**
 * Cache Class Doc Comment
 *
 * @category Class
 * @package  src/cache
 * @author    Guido Vera
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.github.com/veraguido/gv
 *
 */
class RedisClientCache implements CacheInterface
{
    private static RedisClientCache $instance;
    private CacheItemPool $itemPool;

    /**
     * RedisClientCache constructor.
     * @param Config $config
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    private function __construct(Config $config)
    {
        $this->itemPool = new CacheItemPool($config);
    }

    /**
     * @param Config $config
     * @return RedisClientCache
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public static function getInstance(Config $config): RedisClientCache
    {
        if (!isset(self::$instance)) {
            self::$instance = new RedisClientCache($config);
        }
        
        return self::$instance;
    }

    /**
     * @param $key
     * @param $value
     * @param null $expirationTime
     * @throws Exception
     */
    public function save($key, $value, $expirationTime = null)
    {
        $cacheItem = new CacheItem($key);
        $cacheItem->set($value);
        $cacheItem->expiresAfter($expirationTime);
        $this->itemPool->save($cacheItem);
    }

    /**
     * @param $key
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function load($key)
    {
        return $this->itemPool->getItem($key)->get();
    }

    /**
     * @param $key
     * @param null $expirationTime
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function setExpiration($key, $expirationTime = null)
    {
        $cacheItem = $this->itemPool->getItem($key);
        $cacheItem->expiresAfter($expirationTime);
        $this->itemPool->save($cacheItem);
    }

    /**
     * @param $key
     * @return bool
     * @throws InvalidArgumentException
     */
    public function exists($key): bool
    {
        return $this->itemPool->hasItem($key);
    }

    /**
     * @param $key
     * @return bool|void
     * @throws InvalidArgumentException
     */
    public function delete($key): bool
    {
        return $this->itemPool->deleteItem($key);
    }

    public function deleteAll()
    {
        $this->itemPool->deleteAll();
    }
}
