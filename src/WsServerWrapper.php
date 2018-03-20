<?php

namespace ObjectivePHP\Package\WebSocketServer;

use Hoa\Websocket\Node;
use Hoa\Websocket\Server as HoaServer;
use ObjectivePHP\Package\WebSocketServer\Exception\MalformedMessageException;
use ObjectivePHP\Package\WebSocketServer\Identification\WebSocketIdentificationAdapterInterface;
use ObjectivePHP\Package\WebSocketServer\Node\Client;

/**
 * Class Server
 * @package ObjectivePHP\Package\WebSocket
 */
class WsServerWrapper implements ServerWrapperInterface
{
    /**
     * @var HoaServer
     */
    protected $server;

    /**
     * @var Client[]
     */
    protected $clients = [];

    /**
     * @var WebSocketIdentificationAdapterInterface
     */
    protected $identificationAdapter;

    /**
     * Server constructor.
     * @param HoaServer $server
     */
    public function __construct(HoaServer $server = null)
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
            $this->server->broadcast(json_encode(['event' => $event, 'data' => $data]));
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
        $this->server->send(json_encode(['event' => $event, 'data' => $data]));
        return true;
    }

    /**
     * @param array ...$filters
     * @return bool
     */
    protected function broadcastFiltering(...$filters)
    {
        $bool = true;
        $filters = $filters[0];
        foreach ($filters as $filter) {
            $bool = $bool && $filter($this);
        }

        return $bool;
    }

    public function onOpen() {
        /** @var Node $currentNode */
        $currentNode = $this->getServer()->getConnection()->getCurrentNode();

        // store reference to new node
        $this->clients[$currentNode->getId()] = (new Client())->setIdentifier($currentNode->getId())->addNode($currentNode);
    }

    public function onCurrent()
    {
        $currentNode = $this->getServer()->getConnection()->getCurrentNode();
        $client = $this->getCurrentClient();
        $client->setActiveNode($currentNode);
    }

    /**
     * @param array $data
     * @return bool
     * @throws MalformedMessageException
     */
    public function onIdentify(array $data)
    {

        $identifier = $data['identifier'] ?? null;
        $context = $data['context'] ?? [];

        if(is_null($identifier))
        {
            throw new MalformedMessageException('"identify" event callback expects an "identifier" key in data array.');
        }

        /** @var Node $currentNode */
        $currentNode = $this->getServer()->getConnection()->getCurrentNode();

        if($this->hasIdentificationAdapter())
        {
            if(!$this->getIdentificationAdapter()->identify($identifier, $context))
            {
                // TODO send an error to client
                return false;
            }
        }

        if(!$this->hasClient($identifier)) {
            echo 'client ' . $identifier . ' was not found' . PHP_EOL;
            $this->clients[$this->normalize($identifier)] = $this->clients[$this->normalize($currentNode->getId())]->setIdentifier($identifier);
        } else {
            $this->clients[$this->normalize($currentNode->getId())] = $this->getClient($identifier)->addNode($currentNode);
        }

        $this->reply('identified', ['identity' => $identifier]);

        return true;

    }

    public function onClose()
    {

        $currentNode = $this->getServer()->getConnection()->getCurrentNode();
        $this->getCurrentClient()->removeNode($currentNode);
        if(!count($this->getCurrentClient()->getNodes()))
        {
            unset($this->clients[$this->normalize($this->getCurrentClient()->getIdentifier())]);
        }
        unset($this->clients[$currentNode->getId()]);

    }


    /**
     * @return array
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * @param array $clients
     */
    public function setClients(array $clients)
    {
        $this->clients = $clients;
    }

    /**
     * @param $identifier
     * @return bool
     */
    public function hasClient($identifier) : bool
    {
        return isset($this->clients[$this->normalize($identifier)]);
    }

    /**
     * @param $identifier
     * @return Client
     */
    public function getClient($identifier) : Client
    {
        return $this->clients[$this->normalize($identifier)];
    }

    /**
     * @return Client
     */
    public function getCurrentClient() : Client
    {
        return $this->clients[$this->getServer()->getConnection()->getCurrentNode()->getId()];
    }

    /**
     * @return WebSocketIdentificationAdapterInterface
     */
    public function getIdentificationAdapter(): WebSocketIdentificationAdapterInterface
    {
        return $this->identificationAdapter;
    }

    /**
     * @param WebSocketIdentificationAdapterInterface $identificationAdapter
     */
    public function setIdentificationAdapter(WebSocketIdentificationAdapterInterface $identificationAdapter)
    {
        $this->identificationAdapter = $identificationAdapter;
    }

    /**
     * @return bool
     */
    public function hasIdentificationAdapter(): bool
    {
        return !is_null($this->identificationAdapter);
    }

    /**
     * @param $recipient
     * @param $event
     * @param $data
     * @param bool $activeOnly
     * @return bool
     */
    public function sendTo($recipient, $event, $data, $activeOnly = false)
    {
        if (!$this->hasClient($recipient)) {
            return false;
        }
        $client = $this->getClient($recipient);
        $activeNode = $client->getActiveNode();

        /** @var Node $node */
        foreach($client->getNodes() as $node)
        {
            if ($activeOnly) {
                if ($activeNode !== $node->getId()) {
                    continue;
                }
            }
            $this->getServer()->send(json_encode(['event' => $event, 'data' => $data]), $node);
        }
    }

    protected function normalize($str) {
        return strtolower($str);
    }

}