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

namespace Dubbo\Provider;

use Dubbo\Common\YMLParser;
use Dubbo\Common\Logger\LoggerFacade;
use Dubbo\Common\Logger\LoggerSimple;
use Dubbo\Provider\Server\SwooleServer;
use Dubbo\Common\DubboException;

class Initialization
{

    private $_ymlParser;

    public function __construct($configFile)
    {
        $ymlParser = new YMLParser($configFile);
        $ymlParser->providerRequired();
        $this->_ymlParser = $ymlParser;
    }

    public function getPidHandle()
    {
        $pidFile = $this->_ymlParser->getApplicationPidFile();
        @mkdir(dirname($pidFile), true);
        $fp = fopen($pidFile, 'cb');
        if (flock($fp, LOCK_EX | LOCK_NB) === false) {
            throw new DubboException("'{$this->_ymlParser->getApplicationName()}' this application has started\n");
        }
        return $fp;
    }

    public function setLogger()
    {
        LoggerFacade::setLogger(new LoggerSimple($this->_ymlParser));
    }

    private function loadService()
    {
        $service = new Service($this->_ymlParser);
        $service->loadDubboBootstrap();
        return $service;
    }

    public function startServer()
    {
        $this->setLogger();
        $server = new SwooleServer($this->_ymlParser, $this->loadService());
        $server->setPidHandle($this->getPidHandle());
        $server->startUp();
    }

    public function getApplicationName()
    {
        return $this->_ymlParser->getApplicationName();
    }

    public function getPid()
    {
        return file_get_contents($this->_ymlParser->getApplicationPidFile());
    }

    public function getApplicationPidFile()
    {
        return $this->_ymlParser->getApplicationPidFile();
    }

}