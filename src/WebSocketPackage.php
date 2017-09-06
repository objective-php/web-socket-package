<?php
/**
 * Created by PhpStorm.
 * User: gde
 * Date: 04/09/2017
 * Time: 14:55
 */

namespace ObjectivePHP\Package\WebSocketServer;


use ObjectivePHP\Application\ApplicationInterface;
use ObjectivePHP\Application\Middleware\AbstractMiddleware;
use ObjectivePHP\Cli\Config\CliCommand;
use ObjectivePHP\Package\WebSocketServer\Command\WebSocketServer;
use ObjectivePHP\Package\WebSocketServer\Exception\WebSocketServerException;
use ObjectivePHP\ServicesFactory\ServiceReference;
use Psr\Log\LoggerAwareInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

class WebSocketPackage extends AbstractMiddleware
{

    protected $listeners = [];

    protected $command;

    /**
     * WebSocketPackage constructor.
     * @param array $listeners
     */
    public function __construct($command = null, ...$listeners)
    {
        $this->command = $command;
        $this->listeners = $listeners;
    }


    public function run(ApplicationInterface $app)
    {
        $config = $app->getConfig()->get();
        if(is_null($this->command))
        {
            if($app->getConfig())
            {

            } else {
                $command = WebSocketServer::class;
            }
        }

        switch (true) {
            case is_string($this->command):
                $commandClass = $this->command;
                $command = new $commandClass(...$this->listeners);
                $app->getServicesFactory()->injectDependencies($command);
                break;

            case $this->command instanceof ServiceReference:
                $command = $app->getServicesFactory()->get($this->command);
                if ($command instanceof WebSocketServer) {
                    $command->registerListeners(...$this->listeners);
                }
                break;

            default:
                throw new WebSocketServerException('Invalid command class definition');

        }

        // inject default logger if needed
        if($command instanceof LoggerAwareInterface)
        {
            $logFile = $app->getConfig()
            if(!method_exists($command, 'getLogger') || !$command->getLogger())
            {
                $logger = new Logger();
                $logger->addWriter(new Stream())
            }

        }

        $app->getConfig()->import(new CliCommand($command));
    }
}