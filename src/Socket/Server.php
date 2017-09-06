<?php

namespace ObjectivePHP\Package\WebSocket\Socket;

use Hoa\Websocket\Server as HoaServer;

/**
 * Class Server
 * @package ObjectivePHP\Package\WebSocket\Socket
 */
class Server implements ServerInterface
{
    /**
     * @var
     */
    protected $server;

    /**
     * Server constructor.
     * @param HoaServer $server
     */
    public function __construct(HoaServer $server)
    {
        $this->setServer($server);
    }

    /**
     * @return HoaServer
     */
    public function getServer() : HoaServer
    {
        return $this->server;
    }

    /**
     * @param HoaServer $server
     * @return $this
     */
    public function setServer(HoaServer $server)
    {
        $this->server = $server;

        return $this;
    }

    /**
     * @param string $event
     * @param array $data
     * @param array ...$filters
     * @return bool
     */
    public function broadcast(string $event, array $data, ...$filters)
    {
        if ($this->broadcastFiltering($filters)) {
            $this->server->broadcastIf(function() { return true;}, json_encode(['event' => $event, 'message' => $data]));
            return true;
        }
        return false;
    }

    /**
     * @param string $event
     * @param array $data
     * @param array ...$filters
     * @return bool
     */
    public function broadcastOthers(string $event, array $data, ...$filters)
    {
        if ($this->broadcastFiltering($filters)) {
            $this->server->broadcast(json_encode(['event' => $event, 'message' => $data]));
            return true;
        }
        return false;
    }

    /**
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function reply(string $event, array $data)
    {
        $this->server->send(json_encode(['event' => $event, 'message' => $data]));
        return true;
    }

    /**
     * @param array ...$filters
     * @return bool
     */
    protected function broadcastFiltering(...$filters)
    {
        $filters = $filters[0];
        foreach ($filters as $filter) {
            $bool = $filter->call($this);
            if (!$bool) {
                return false;
            }
        }
        return true;
    }
}