<?php
/**
 * Created by PhpStorm.
 * User: gde
 * Date: 07/09/2017
 * Time: 11:52
 */

namespace ObjectivePHP\Package\WebSocketServer\Identification;


interface WebSocketIdentificationAdapterInterface
{

    public function identify($identifier, array $context = []);

}