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

namespace Dubbo\Agent;

use Dubbo\Agent\Server\SwooleServer;
use Dubbo\Agent\Registry\RegistryFactory;
use Dubbo\Agent\Logger\LoggerFacade;
use Dubbo\Agent\Logger\LoggerSimple;

class Bootstrap
{
    private $_ymlParser;

    public function __construct($configFile)
    {
        $this->_ymlParser = new YMLParser($configFile);
        LoggerFacade::setLogger(new LoggerSimple($this->_ymlParser));
    }

    public function run()
    {
        $callbackList = [
            'registry' => function () {
                return RegistryFactory::getInstance($this->_ymlParser);
            },
        ];
        $server = new SwooleServer($this->_ymlParser, $callbackList);
        $server->setPidHandle($this->getPidHandle());
        $server->startup();
    }

    public function getPidHandle()
    {
        $pidFile = $this->_ymlParser->getServerPidFile();
        @mkdir(dirname($pidFile), true);
        $fp = fopen($pidFile, 'cb');
        if (flock($fp, LOCK_EX | LOCK_NB) === false) {
            throw new DubboAgentException("'{$this->_ymlParser->getApplicationName()}' this agent has started\n");
        }
        return $fp;
    }

}