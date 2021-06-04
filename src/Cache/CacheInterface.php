<?php
namespace Gvera\Cache;

/**
 * Cache Interface Doc Comment
 *
 * @category Interface
 * @package  src/cache
 * @author    Guido Vera
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.github.com/veraguido/gv
 *
 */
interface CacheInterface
{
    /**
     * @param $key
     * @param $value
     * @param null $expirationTime
     * @return void
     */
    public function save($key, $value, $expirationTime = null);

    /**
     * @param $key
     * @return mixed
     */
    public function load($key);

    /**
     * @param $key
     * @param null $expirationTime
     * @return void
     */
    public function setExpiration($key, $expirationTime = null);

    /**
     * @param $key
     * @return bool
     */
    public function exists($key): bool;

    /**
     * @param $key
     * @return void
     */
    public function delete($key);
}
