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
use ObjectivePHP\Package\WebSocketServer\Command\WebSocketServerCommandInterface;
use ObjectivePHP\Package\WebSocketServer\Config\WebSocketServerConfig;
use ObjectivePHP\Package\WebSocketServer\Exception\WebSocketServerException;
use ObjectivePHP\ServicesFactory\ServiceReference;
use Psr\Log\LoggerAwareInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

class WebSocketServerPackage extends AbstractMiddleware
{

    public function run(ApplicationInterface $app)
    {
        $servers = $app->getConfig()->get(WebSocketServerConfig::class);

        /** @var WebSocketServerConfig $server */
        foreach($servers as $server) {
            
            $command = $server->getAction();

            switch (true) {
                case is_string($command):
                    $commandClass = $command;
                    $command = new $commandClass($server);
                    $app->getServicesFactory()->injectDependencies($command);
                    break;

                case $command instanceof ServiceReference:
                    $command = $app->getServicesFactory()->get($command);
                    if ($command instanceof WebSocketServer) {
                        $command->registerListeners(...$server->getListeners());
                    }
                    break;

                case $command instanceof WebSocketServerCommandInterface:
                    $command->setConfig($server);
                    break;

                default:
                    throw new WebSocketServerException('Invalid command class definition');

            }

            // inject default logger if needed
            if ($command instanceof LoggerAwareInterface) {

            if (!method_exists($command, 'getLogger') || !$command->getLogger()) {
                $logFile = $server->getLogFile();
                if($logFile) {
                    $logger = new Logger();
                    $logger->addWriter(new Stream($logFile));
                }
            }

        }

            $app->getConfig()->import(new CliCommand($command));
        }
    }


}