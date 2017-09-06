<?php

namespace ObjectivePHP\Package\WebSocketServer;

/**
 * Interface Server
 * @package ObjectivePHP\Package\WebSocket
 */
interface ServerWrapperInterface
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