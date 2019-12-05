<?php
/*
  +----------------------------------------------------------------------+
  | dubbo-php-framework                                                        |
  +----------------------------------------------------------------------+
  | This source file is subject to version 2.0 of the Apache license,    |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.apache.org/licenses/LICENSE-2.0.html                      |
  +----------------------------------------------------------------------+
  | Author: Jinxi Wang  <crazyxman01@gmail.com>                              |
  +----------------------------------------------------------------------+
*/

//usage: php CustomLogger.php

define("VENDOR_DIR", __DIR__ . '/../../../');

include VENDOR_DIR . "/autoload.php";

use Dubbo\Consumer\DubboConsumer;
use Dubbo\Common\Logger\LoggerInterface;
use Dubbo\Common\Logger\LoggerFacade;

class CustomLogger implements LoggerInterface {
    public function debug(string $text, ...$params)
    {
        // TODO: Implement debug() method.
    }
    public function info(string $text, ...$params)
    {
        // TODO: Implement info() method.
    }
    public function warn(string $text, ...$params)
    {
        // TODO: Implement warn() method.
    }
    public function error(string $text, ...$params)
    {
        // TODO: Implement error() method.
    }
}
LoggerFacade::setLogger(new CustomLogger());
$consumerConfig = __DIR__ . '/../src/rpc/Config/ConsumerConfig.yaml';
$instance = DubboConsumer::getInstance($consumerConfig, '/tmp/ConsumerConfigCache.php');
$service = $instance->loadService('php.dubbo.demo.DemoService');
$res = $service->invoke('sayHello', ['a' => 'b'], [1, 3]);
var_dump($res);


