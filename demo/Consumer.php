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

//usage: php Consumer.php

define("VENDOR_DIR", __DIR__ . '/../../../');

include VENDOR_DIR . "/autoload.php";

use Dubbo\Consumer\DubboConsumer;
use Dubbo\Common\Protocol\Dubbo\DubboParam;


$consumerConfig = __DIR__ . '/../src/rpc/Config/ConsumerConfig.yaml';
$instance = DubboConsumer::getInstance($consumerConfig, '/tmp/ConsumerConfigCache.php');
$service = $instance->loadService('php.dubbo.demo.DemoService');
$res = $service->invoke('sayHello', ['a' => 'b'], [1, 3]);
var_dump($res);

/*

// When the argument is an Integer
$service = $instance->loadService('com.imooc.springboot.dubbo.demo.IntegerDemoService');
$res = $service->invoke('sayHello', 20880);

// When the argument is an String
$service = $instance->loadService('com.imooc.springboot.dubbo.demo.StringDemoService');
$res = $service->invoke('sayHello', "hello");

// When the argument is an Map
$service = $instance->loadService('com.imooc.springboot.dubbo.demo.MapDemoService');
$res = $service->invoke('sayHello', ['a'=>'b']);

// When the argument is an ArrayList
$service = $instance->loadService('com.imooc.springboot.dubbo.demo.ArrayListDemoService');
$res = $service->invoke('sayHello', [2,3,4]);

// When the argument is an LinkedList
$service = $instance->loadService('com.imooc.springboot.dubbo.demo.LinkedListDemoService');
$res = $service->invoke('sayHello', DubboParam::Type('java.util.LinkedList', ['a', 'b']));

// When the argument is an object
$service = $instance->loadService('com.imooc.springboot.dubbo.demo.ObjectDemoService');
$res = $service->invoke('sayHello',
    DubboParam::object(
        'com.imooc.springboot.dubbo.demo.dto.TestObjectDemo',
        [
            "name" => "Tom",
            "age" => 30,
            'bigDecimal' => DubboParam::object('java.lang.Object', ['value' => 15.6])
        ])
);

 */
