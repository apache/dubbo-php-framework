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

namespace DubboDemo\Service;

use Dubbo\Provider\Annotations\DubboClassAnnotation;
use Dubbo\Provider\Annotations\DubboMethodAnnotation;

/**
 *
 * @DubboClassAnnotation(serviceAlias="php.dubbo.demo.DemoService", version="", group="")
 */
class Demo
{

    /**
     * @DubboMethodAnnotation
     */
    public function sayHello($args)
    {
        return "Dubbo sayHello!";
    }

    public static function dubboIngress($method, $args, $server, $fd, $reactor_id)
    {
        $_self = new self();
        return call_user_func_array([$_self, $method], $args);
    }
}