<?php
namespace Gvera\Cache;

use Exception;
use Gvera\Exceptions\InvalidConstructorParameterException;
use Gvera\Helpers\config\Config;

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
class Cache
{
    private static string $cacheFilesPath;
    private static Config $config;

    /**
     * it will ping redis to check the availability of the service, if it's not present it will fallback
     * to files as default. As PRedis will ping true OR exception I can only catch the exception and fallback to
     * FilesCache
     * @return FilesCache|RedisClientCache
     * @throws Exception
     */
    public static function getCache(): CacheInterface
    {

        if (!isset(self::$config)) {
            throw new Exception(
                'cache cannot be initialized without the config file'
            );
        }

        $cacheType = self::$config->getConfigItem('cache_type');

        if ('files' === $cacheType) {
            return FilesCache::getInstance(self::$config);
        }

        //setting up files cache as a fallback in case redis client fails.
        try {
            return RedisClientCache::getInstance(self::$config);
        } catch (Exception $e) {


            if (!self::$config->getConfigItem('cache_fallback')) {
                throw new Exception('Redis cache could not be initialized and fallback is not activated');
            }

            return FilesCache::getInstance(self::$config);
        }
    }

    public static function setConfig(Config $config)
    {
        self::$config = $config;
    }
}
