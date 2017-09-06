<?php
/**
 * Created by PhpStorm.
 * User: gauthier
 * Date: 30/08/2017
 * Time: 15:05
 */

namespace ObjectivePHP\Package\WebSocket\Command;


use Hoa\Event\Bucket;
use Hoa\Websocket\Server;
use League\CLImate\CLImate;
use ObjectivePHP\Application\ApplicationInterface;
use ObjectivePHP\Cli\Action\AbstractCliAction;
use ObjectivePHP\Cli\Action\Parameter\Argument;
use ObjectivePHP\Cli\Action\Parameter\Param;
use ObjectivePHP\Cli\Action\Parameter\Toggle;
use ObjectivePHP\Package\WebSocket\Exception\InvalidListenerException;
use ObjectivePHP\Package\WebSocket\Exception\MalformedMessageException;
use ObjectivePHP\Package\WebSocket\Exception\WebSocketServerException;
use ObjectivePHP\Primitives\String\Camel;

/**
 * Class WebSocketServer
 * @package ObjectivePHP\Package\WebSocket\Command
 */
class WebSocketServer extends AbstractCliAction
{
    protected $defaultPidFile = '/tmp/ws-server.pid';
    
    protected $backgroundExecution = false;

    protected $listeners = [];

    protected $callbackHandlers = [];
    
    /**
     * WebSocketServer constructor.
     */
    public function __construct(...$listeners)
    {
        $this->setCommand('ws-server');
        $this->allowUnexpectedParameters();
        $this->setDescription('Start a web socket server');
        $this->expects(new Toggle(['v' => 'verbose'], 'Verbosely reports server activity (foreground mode only)'));
        $this->expects(new Toggle(['f' => 'force'], 'Force starting the server, ignoring existing PID file'));
        $this->expects((new Param('pid-file', 'file where to store daemon PID')));
        $this->expects(new Argument('operation',
            'In daemon mode, tells what operation to apply on daemon (start|stop|restart|run)'));

        // register listener classes
        $this->listeners = $listeners;
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
                    $c->info('Starting server...');
                    $this->startServer();
                    break;
                
                case 'stop':
                    $this->stopDaemon();
                    $c->info('Server stopped');
                    exit(0);
                    
                case 'start':
                    $this->startDaemon();
                    exit(0);
                    
                case 'restart':
                    $c->info('Restarting WS server...');
                    $this->stopDaemon($silent = true);
                    $this->startDaemon();
                    exit(0);
                    
                default:
                    $c->error('Unknown background operation: "' . $operation . '"');
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
        $server = new Server(new \Hoa\Socket\Server('tcp://127.0.0.1:8889'));
        $wsServer = new \ObjectivePHP\Package\WebSocket\Socket\Server($server);

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
                    throw new InvalidListenerException('The listnener class "' . $listener . '" was not found.');
                }
            } elseif(is_object($listener))
            {
                $callbackHandler = $listener;
            }
            else {
                throw new InvalidListenerException('Valid listeners are class names or object instances. ' . gettype($listener) . ' has been provided');
            }

            $this->callbackHandlers[] = $callbackHandler;
        }


        $mainHandler = function (Bucket $bucket) use($wsServer) {

            try {


                $data = $bucket->getData();
                $rpc = json_decode($data['message'], true);

                if (!is_array($rpc) || !isset($rpc['event']) || !isset($rpc['params'])) {
                    throw new MalformedMessageException('Incoming message does not comply to expected format.');
                }

                $event = $rpc['event'];
                $params = $rpc['params'];

                $this->debug('event "' . $event . '" with params ' . json_encode($params));

                // trigger handlers
                $method = 'on' . Camel::case($event, Camel::UPPER, ['.', '_', '-']);

                foreach($this->callbackHandlers as $handler)
                {
                    if(method_exists($handler, $method)) {
                        $handler->$method($params, $wsServer);
                        $this->debug('ran ' . get_class($handler) . '->'  . $method . '()');
                    }
                }


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
            (new CLImate())->error('Cannot start server. Maybe is another instance already running on same port?' . PHP_EOL . 'Use "-v" to get more information about what was wrong.');
            if($this->getParam('v'))
            {
                (new CLImate())->comment('Exception message: ' . '<white>' . $e->getMessage() . '</white>');
            }
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

    protected function debug($message)
    {
        if($this->getParam('v') && !$this->backgroundExecution)
        {
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

        $this->debug($message);
    }

    public function onOpen()
    {
        $this->debug('new incoming connection');
    }

    public function onClose()
    {
        $this->debug('a connection stopped');
    }
}
