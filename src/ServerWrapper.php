<?php

namespace ObjectivePHP\Package\WebSocket;

/**
 * Interface Server
 * @package ObjectivePHP\Package\WebSocket
 */
interface ServerWrapper
{
    /**
     * @param string $event
     * @param array $data
     * @param array ...$filters
     * @return mixed
     */
    public function broadcast(string $event, array $data, ...$filters);

    /**
     * @param string $event
     * @param array $data
     * @param array ...$filters
     * @return mixed
     */
    public function broadcastOthers(string $event, array $data, ...$filters);

    /**
     * @param string $event
     * @param array $data
     * @return mixed
     */
    public function reply(string $event, array $data);
}