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

namespace Dubbo\Consumer;

use Dubbo\Common\DubboException;
use Dubbo\Consumer\Discoverer\RemoteSwTable;
use Dubbo\Common\Protocol\Dubbo\DubboRequest;
use Dubbo\Common\Logger\LoggerFacade;
use Dubbo\Common\Logger\LoggerSimple;
use Dubbo\Common\YMLParser;

class DubboConsumer
{

    private $_ymlParser = [];

    private $_discoverer = null;

    private function __construct($config, $cacheFile)
    {
        $this->_ymlParser = new YMLParser($config, $cacheFile);
        $this->_ymlParser->consumerRequired();
        $this->_discoverer = new RemoteSwTable($this->_ymlParser);
    }

    public static function getInstance($config = null, $cacheFile = null)
    {
        static $_instance = null;
        if (is_null($_instance)) {
            $_instance = new self($config, $cacheFile);
        }
        if (is_null(LoggerFacade::getLogger())) {
            LoggerFacade::setLogger(new LoggerSimple($_instance->_ymlParser));
        }
        return $_instance;
    }

    public function loadService($service)
    {
        $reference = $this->_ymlParser->getReference();
        if (!array_key_exists($service, $reference)) {
            throw new DubboException("Unable to find '{$service}' service in configuration file");
        }
        $serviceConfig = $reference[$service] ?: [];
        foreach ($this->_ymlParser->getParameter() as $key => $value) {
            if (!isset($serviceConfig[$key])) {
                $serviceConfig[$key] = $value;
            }
        }
        if ($serviceConfig['url'] ?? false) {
            $providers[] = $serviceConfig['url'];
        } else {
            $providers = $this->_discoverer->getProviders($service, $serviceConfig['query'] ?? []);
        }
        if (!$providers) {
            throw new DubboException("No '{$service}' provider found");
        }
        $request = new DubboRequest($providers, $serviceConfig);
        return $request;
    }

    public static function setConfigcache()
    {

    }

}
