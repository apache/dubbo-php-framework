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

namespace Dubbo\Agent\Registry;

use Dubbo\Agent\YMLParser;
use Dubbo\Agent\Registry\Client\ZookeeperClient;
use Dubbo\Agent\Logger\LoggerFacade;

class RegistryFactory
{

    private function __construct()
    {

    }

    public static function getInstance(YMLParser $ymlParser)
    {
        if (!$ymlParser->getWatchNodes()) {
            LoggerFacade::getLogger()->error("No services found to subscribe");
        }
        $instance = new ZookeeperClient($ymlParser);
        return $instance;
    }


}