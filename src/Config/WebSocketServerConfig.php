<?php

namespace ObjectivePHP\Package\WebSocketServer\Config;

use ObjectivePHP\Config\Exception;
use ObjectivePHP\Config\SingleDirective;
use ObjectivePHP\Config\StackedDirective;
use ObjectivePHP\Package\WebSocketServer\Command\WebSocketServer;
use ObjectivePHP\Package\WebSocketServer\Command\WebSocketServerCommandInterface;

class WebSocketServerConfig extends StackedDirective
{

    protected $bindingAddress;

    protected $port;

    protected $action;

    protected $protocol;

    protected $listeners = [];

    protected $logFile;

    public function __construct($listeners = [], $port = 8889, $bindingAddress = '127.0.0.1', $protocol = 'ws', $action = WebSocketServer::class, $logFile = null)
    {
        $this->setAction($action);
        $this->setPort($port);
        $this->setBindingAddress($bindingAddress);
        $this->setProtocol($protocol);
        $this->setListeners($listeners);
    }


    /**
     * @return mixed
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @param mixed $protocol
     */
    public function setProtocol($protocol)
    {
        if(!in_array($protocol, ['tcp', 'ws', 'wss']))
        {
            throw new Exception('Forbidden protocol value. Allowed values are: tcp, ws and wss');
        }
        $this->protocol = $protocol;

        return $this;
    }



    /**
     * @return mixed
     */
    public function getBindingAddress()
    {
        return $this->bindingAddress;
    }

    /**
     * @param mixed $bindingAddress
     */
    public function setBindingAddress($bindingAddress)
    {
        $this->bindingAddress = $bindingAddress;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param mixed $port
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param mixed $action
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }

    /**
     * @param array $listeners
     */
    public function setListeners(array $listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * @return mixed
     */
    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * @param mixed $logFile
     */
    public function setLogFile($logFile)
    {
        $this->logFile = $logFile;
    }

}