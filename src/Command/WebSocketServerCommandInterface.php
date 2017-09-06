<?php
/**
 * Created by PhpStorm.
 * User: gde
 * Date: 06/09/2017
 * Time: 10:26
 */

namespace ObjectivePHP\Package\WebSocketServer\Command;


use ObjectivePHP\Package\WebSocketServer\Config\WebSocketServerConfig;

interface WebSocketServerCommandInterface
{

    public function __construct(WebSocketServerConfig $config);

}