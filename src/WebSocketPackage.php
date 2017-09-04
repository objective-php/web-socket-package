<?php
/**
 * Created by PhpStorm.
 * User: gde
 * Date: 04/09/2017
 * Time: 14:55
 */

namespace ObjectivePHP\Package\WebSocket;


use ObjectivePHP\Application\ApplicationInterface;
use ObjectivePHP\Application\Middleware\AbstractMiddleware;
use ObjectivePHP\Cli\Config\CliCommand;
use ObjectivePHP\Package\WebSocket\Command\WebSocketServer;

class WebSocketPackage extends AbstractMiddleware
{

    protected $listeners = [];

    /**
     * WebSocketPackage constructor.
     * @param array $listeners
     */
    public function __construct(...$listeners)
    {
        $this->listeners = $listeners;
    }


    public function run(ApplicationInterface $app)
    {
        $app->getConfig()->import(new CliCommand(new WebSocketServer(...$this->listeners)));
    }

}