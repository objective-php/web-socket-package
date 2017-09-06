<?php

namespace ObjectivePHP\Package\WebSocketServer\Config;

use ObjectivePHP\Config\Exception;
use ObjectivePHP\Config\SingleDirective;

class WebSocketServerConfig extends SingleDirective
{

    protected $bindingAddress;

    protected $port;

    protected $command;

    protected $protocol;

    public function __construct($port = 8889, $bindingAddress = '127.0.0.1', $protocol = 'ws')
    {
        $this->setPort($port);
        $this->setBindingAddress($bindingAddress);
        $this->setProtocol($protocol);
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

}