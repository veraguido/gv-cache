<?php


class CacheTest extends \PHPUnit\Framework\TestCase
{
    private string $filesCachePath = __DIR__ . "/../var/cache/files/";

    /**
     * @test
     */
    public function testException()
    {
        $this->expectException(Exception::class);
        \Gvera\Cache\Cache::getCache();
    }

    /**
     * @test
     * @throws Exception
     */
    public function testFilesCache()
    {
        $config = new \Gvera\Helpers\config\Config(__DIR__ . "/../config/config.yml");
        $config->overrideKey('files_cache_path', $this->filesCachePath);
        \Gvera\Cache\Cache::setConfig($config);
        $itemToCache = "test";

        $cache = \Gvera\Cache\Cache::getCache();

        $cache->save('testkey', $itemToCache);
        $this->assertTrue($cache->load('testkey') === "test");
        $cache->delete('testkey');
        $this->assertFalse($cache->exists('testkey'));

        $cache->save('testkey1', $itemToCache);
        $cache->save('testkey2', $itemToCache);
        $cache->save('testkey3', $itemToCache);
        $cache->save('testkey4', $itemToCache);
        $cache->save('testkey5', $itemToCache);

        $cache->deleteAll();
        $this->assertTrue(count(scandir($this->filesCachePath)) === 2);

        $this->expectException(Exception::class);
        $cache->setExpiration('asd', 2);
    }

    /**
     * @test
     * @throws Exception
     */
    public function testCacheItem()
    {
        $item = new \Gvera\Cache\CacheItem('test');
        $item->set('newtest');
        $item->expiresAfter(12);
        $this->assertTrue($item->get() === 'newtest');
        $this->assertTrue($item->getExpirationTime() === 12);
        $this->assertTrue($item->getKey() === 'test');
        $this->assertTrue($item->isHit());
        $this->expectException(Exception::class);
        $item->expiresAt(new DateTime('2022-02-01'));
    }

    /**
     * @test
     */
    public function testCacheItemWithExpiration()
    {
        $expirationItem = $item = new \Gvera\Cache\CacheItem('expiration');
        $expirationItem->expiresAt(new DateTime('2022-02-01'));
        $this->expectException(Exception::class);
        $expirationItem->expiresAfter(2);
    }

    /**
     * @test
     * @throws Exception
     */
    public function testRedisPoolableClientCache()
    {
        $config = new \Gvera\Helpers\config\Config(__DIR__ . "/../config/config.yml");
        $redisConfig = [
            "scheme" => "tcp",
            "host" => $config->getConfigItem('redis')["host"],
            "port" => $config->getConfigItem('redis')["port"],
            "cluster" => $config->getConfigItem('redis')["cluster"]
        ];
        $poolableClientCache = new \Gvera\Cache\RedisPoolableClientCache(1, $redisConfig);
        $client = $poolableClientCache->nextClient();
        $this->assertNotNull($client);
        $poolableClientCache->destructPool();
        $this->expectException(\Gvera\Exceptions\InvalidArgumentException::class);
        $poolableClientCache->nextClient();
    }

    /**
     * @test
     * @throws \Gvera\Exceptions\InvalidArgumentException
     */
    public function testRedisPoolableClients()
    {
        $config = new \Gvera\Helpers\config\Config(__DIR__ . "/../config/config.yml");
        $redisConfig = [
            "scheme" => "tcp",
            "host" => $config->getConfigItem('redis')["host"],
            "port" => $config->getConfigItem('redis')["port"],
            "cluster" => $config->getConfigItem('redis')["cluster"]
        ];
        $poolableClientCache = new \Gvera\Cache\RedisPoolableClientCache(3, $redisConfig);
        $client = $poolableClientCache->nextClient();
        $this->assertNotNull($client);
        $secondClient = $poolableClientCache->nextClient();
        $this->assertFalse($client === $secondClient);
    }

}