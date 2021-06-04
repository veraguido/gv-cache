<?php
namespace Gvera\Cache;

use Exception;
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
final class FilesCache implements CacheInterface
{
    private static FilesCache $instance;
    private static FilesCacheClient $client;

    const FILES_CACHE_PREFIX = 'gv_cache_files_';
    private static Config $config;

    private function __construct(Config $config)
    {
        self::$config = $config;
    }

    public static function getInstance(Config $config): FilesCache
    {
        if (!isset(self::$instance)) {
            self::$instance = new FilesCache($config);
            self::checkClient();
        }

        return self::$instance;
    }

    public function save($key, $value, $expirationTime = null)
    {
        self::$client->saveToFile(self::FILES_CACHE_PREFIX . $key, $value);
    }

    public function load($key)
    {
        return self::$client->loadFromFile(self::FILES_CACHE_PREFIX . $key);
    }

    /**
     * @param $key
     * @param null $expirationTime
     * @throws Exception
     */
    public function setExpiration($key, $expirationTime = null)
    {
        throw new Exception('FilesCache does not support expiration');
    }

    public function exists($key): bool
    {
        return file_exists($this->getFilesPath() . self::FILES_CACHE_PREFIX . $key);
    }

    public function delete($key)
    {
        $path = $this->getFilesPath() . self::FILES_CACHE_PREFIX . $key;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function deleteAll()
    {
        $files = glob($this->getFilesPath() . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private static function checkClient()
    {
        if (!isset(self::$client)) {
            self::$client = new FilesCacheClient(self::getFilesPath());
        }
    }

    private static function getFilesPath(): string
    {
        return self::$config->getConfigItem('files_cache_path');
    }
}
