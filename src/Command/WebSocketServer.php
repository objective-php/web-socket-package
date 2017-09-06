<?php
/**
 * Created by PhpStorm.
 * User: gauthier
 * Date: 30/08/2017
 * Time: 15:05
 */

namespace ObjectivePHP\Package\WebSocketServer\Command;


use Hoa\Event\Bucket;
use Hoa\Websocket\Server;
use League\CLImate\CLImate;
use ObjectivePHP\Application\ApplicationInterface;
use ObjectivePHP\Cli\Action\AbstractCliAction;
use ObjectivePHP\Cli\Action\Parameter\Argument;
use ObjectivePHP\Cli\Action\Parameter\Param;
use ObjectivePHP\Cli\Action\Parameter\Toggle;
use ObjectivePHP\Package\WebSocketServer\WsServerWrapper;
use ObjectivePHP\Package\WebSocketServer\Config\WebSocketServerConfig;
use ObjectivePHP\Package\WebSocketServer\Exception\InvalidListenerException;
use ObjectivePHP\Package\WebSocketServer\Exception\MalformedMessageException;
use ObjectivePHP\Package\WebSocketServer\Exception\WebSocketServerException;
use ObjectivePHP\Primitives\String\Camel;
use Psr\Log\LoggerInterface;

class WebSocketServer extends AbstractCliAction implements WebSocketServerCommandInterface
{
    protected $defaultPidFile = '/tmp/ws-server.pid';

    protected $backgroundExecution = false;

    /**
     * @var WebSocketServerConfig
     */
    protected $config;

    protected $callbackHandlers = [];

    protected $logger;

    protected $listeners = [];

    /**
     * WebSocketServer constructor.
     */
    public function __construct(WebSocketServerConfig $config = null)
    {


        $this->setCommand('ws-server');
        $this->allowUnexpectedParameters();
        $this->setDescription('Start a web socket server');
        $this->expects(new Toggle(['d' => 'debug'], 'Log debug informations'));
        $this->expects(new Toggle(['f' => 'force'], 'Force starting the server, ignoring existing PID file'));
        $this->expects((new Param('pid-file', 'file where to store daemon PID')));
        $this->expects(new Argument('operation',
            'Server operation (start|stop|restart|run*)'));

        if($config) {
            $this->setConfig($config);
            $this->listeners += $config->getListeners();
        }
    }

    public function registerListeners(...$listeners)
    {
        $this->listeners += $listeners;
        $this->listeners = array_unique($this->listeners);

        return $this;
    }

    /**
     * @param ApplicationInterface $app
     *
     * @return mixed
     */
    public function run(ApplicationInterface $app)
    {
        $c = new CLImate();

        if ($this->getParam('force')) {
            $this->stopDaemon($silent = true);
        }

        $operation = $this->getParam('operation', 'run');
        switch($operation) {

            case 'run':
                $this->log('Starting server...');
                $this->startServer();
                break;

            case 'stop':
                $this->stopDaemon();
                $this->log('Server stopped');
                exit(0);

            case 'start':
                $this->startDaemon();
                exit(0);

            case 'restart':
                $this->log('Restarting WS server...');
                $this->stopDaemon($silent = true);
                $this->startDaemon();
                exit(0);

            default:
                $this->log('Unknown background operation: "' . $operation . '"');
                exit(-1);
        }
    }

    protected function stopDaemon($silent = false)
    {
        if ($this->checkPidFile()) {
            if (posix_kill($this->getPid(), SIGTERM)) {
                unlink($this->getPidFile());
            } else {
                if(!$silent) (new CLImate())->error('Unable to kill process #' . file_get_contents($this->getParam('pid-file')));
            }
        } else {
            if(!$silent) (new CLImate())->error('The PID file "' . $this->getParam('pid-file') . '" does not exist or is not readable');
        }
    }

    protected function checkPidFile()
    {
        return file_exists($this->getPidFile());
    }

    protected function startDaemon()
    {
        $force = $this->getParam('force');
        if($this->checkPidFile() && !$force)
        {
            (new CLImate())->error('Cannot start server while PID file (' . $this->getPidFile() . ') exists. Another instance of this server is likely to run with PID ' . $this->getPid() . '.');

            if(!posix_getpgid($this->getPid()))
            {
                (new CLImate())->br();
                (new CLImate())->comment('The process #' . $this->getPid() . ' does not seem to be still running. You may try to force server start using "-f" flag.');
            }

            exit(-1);
        }

        $pid = pcntl_fork();
        if ($pid == -1) {
            (new CLImate())->error('An error occurred while trying to launch the server in background');
            exit;
        } elseif ($pid === 0) {
            $this->backgroundExecution = true;
            $this->startServer();
        } else {
            (new CLImate())->info('Spawned daemon with pid ' . $pid);

            // store pid in PID file
            file_put_contents($this->getPidFile(), $pid);
            exit(0);
        }

    }

    protected function startServer()
    {

        $serverBinding = $this->getConfig()->getProtocol() . '://' . $this->getConfig()->getBindingAddress() . ':' . $this->getConfig()->getPort();
        $server = new Server(new \Hoa\Socket\Server($serverBinding));
        $wsServer = new WsServerWrapper($server);

        // add server itself as callback handler
        $this->callbackHandlers[] = $this;

        // initialize callback handlers
        foreach($this->listeners as $listener)
        {
            if(is_string($listener))
            {
                if(class_exists($listener)) {
                    $callbackHandler = new $listener;
                } else {
                    throw new InvalidListenerException('The listener class "' . $listener . '" was not found.');
                }
            } elseif(is_object($listener))
            {
                $callbackHandler = $listener;
            }
            else {
                throw new InvalidListenerException('Valid listeners are class names or object instances. ' . gettype($listener) . ' has been provided');
            }

            $this->getServicesFactory()->injectDependencies($callbackHandler);

            $this->callbackHandlers[] = $callbackHandler;
        }


        $mainHandler = function (Bucket $bucket) use($wsServer) {

            try {


                $data = $bucket->getData();
                $rpc = json_decode($data['message'], true);

                if (!is_array($rpc) || !isset($rpc['event']) || !isset($rpc['data'])) {
                    throw new MalformedMessageException('Incoming message does not comply to expected format.');
                }

                $event = $rpc['event'];
                $data = $rpc['data'];

                $this->log('event "' . $event . '" with data ' . json_encode($data));

                // trigger handlers
                $method = 'on' . Camel::case($event, Camel::UPPER, ['.', '_', '-']);

                foreach($this->callbackHandlers as $handler)
                {
                    if(method_exists($handler, $method)) {
                        $handler->$method($data, $wsServer);
                        $this->log('ran ' . get_class($handler) . '->'  . $method . '()');
                    }
                }

                // TODO throw Exception if no callback matched received event

            } catch (\Throwable $e) {
                $this->issueError($e);
            }

            return;
        };

        $server->on('message', $mainHandler);
        $server->on('open', [$this, 'onOpen']);
        $server->on('close',[$this, 'onClose']);



        try {
            $server->run();
        } catch(\Exception $e)
        {
            (new CLImate())->error('Cannot start server. Maybe is another instance already running on same port?' . PHP_EOL . 'Use "-d" to get more information about what was wrong.');
            if($this->getParam('debug')) $this->log('Exception message: ' . '<red>' . $e->getMessage() . '</red>');
        }
    }

    protected function getPid()
    {
        return (int) file_get_contents($this->getPidFile());
    }

    protected function getPidFile()
    {
        return $this->getParam('pid-file') ?: $this->defaultPidFile;
    }

    protected function log($message)
    {
        if($this->backgroundExecution) {
            $logger = $this->getLogger();
        }
        else {
            (new CLImate())->info(date('Y-m-d H:i') . ' => <comment>' . $message . '</comment>');
        }
    }

    protected function issueError($message, $code = 500)
    {
        if($message instanceof WebSocketServerException)
        {
            $code = $message->getCode() ?: $code;
            $message = $message->getMessage();
        }
        
        $this->log($message);
    }

    public function onOpen()
    {
        $this->log('new incoming connection');
    }

    public function onClose()
    {
        $this->log('a connection stopped');
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return WebSocketServerConfig
     */
    public function getConfig(): WebSocketServerConfig
    {
        return $this->config;
    }

    /**
     * @param WebSocketServerConfig $config
     */
    public function setConfig(WebSocketServerConfig $config)
    {
        $this->config = $config;

        return $this;
    }
}
