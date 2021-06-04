<?php

namespace Gvera\Cache;

use Gvera\Exceptions\InvalidArgumentException;
use Predis\Client;

class RedisPoolableClientCache
{
    private $bufferSize;
    private $index = 0;
    private $pool = [];

    /**
     * PoolableClientCache constructor.
     * @param int $bufferSize
     * @param string $clientClassName
     * @param array $config
     * @throws \ReflectionException
     */
    public function __construct(int $bufferSize, array $config)
    {
        $this->bufferSize = $bufferSize;
        $this->constructPool($bufferSize, $config);
    }

    /**
     * @return Client
     * @throws InvalidArgumentException
     */
    public function nextClient(): Client
    {
        if (count($this->pool) === 0) {
            throw new InvalidArgumentException('there is no client in the client pool');
        }

        if (($this->index + 1) < ($this->bufferSize - 1)) {
            $this->index++;
            return $this->pool[$this->index];
        }

        $this->index = 0;
        return $this->returnCurrentClient();
    }

    /**
     * @param $bufferSize
     * @param $config
     */
    private function constructPool($bufferSize, $config)
    {
        for ($i = 0; $i < $bufferSize; $i++) {
            $clientInstance = new Client($config);
            array_push($this->pool, $clientInstance);
        }
    }

    /**
     * @return void
     */
    public function destructPool()
    {
        $this->pool = [];
        $this->index = 0;
        $this->bufferSize = 0;
    }

    private function returnCurrentClient()
    {
        return $this->pool[$this->index];
    }
}
