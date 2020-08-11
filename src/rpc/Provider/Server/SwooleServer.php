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

namespace Dubbo\Provider\Server;

use Dubbo\Agent\Logger\LoggerFacade;
use Dubbo\Common\YMLParser;
use Dubbo\Registry\RegistryFactory;
use Dubbo\Provider\Service;
use Dubbo\Common\Protocol\Dubbo\DubboProtocol;
use Dubbo\Monitor\MonitorFilter;
use Swoole\Lock;
use Swoole\Process;
use Swoole\Server;
use Swoole\Table;
use Swoole\Coroutine;

class SwooleServer
{
    private $_ymlParser;
    private $_swServer;
    private $_service;
    private $_monitorFilter;
    private $_registry;
    private $_serviceTable;
    private $_pidHandle;

    public function __construct(YMLParser $ymlParser, Service $service)
    {
        $this->_ymlParser = $ymlParser;
        $this->_service = $service;
        $this->initMonitor();
    }

    public function startUp()
    {
        $this->_swServer = new Server('0.0.0.0', $this->_ymlParser->getProtocolPort());
        $this->_swServer->set($this->_ymlParser->getSwooleSettings());
        $this->onStart();
        $this->onManagerStart();
        $this->onWorkerStart();
        $this->onReceive();
        $this->onTask();
        $this->onFinish();
        $this->onManagerStop();
        $this->_swServer->start();
    }

    public function onStart()
    {
        $this->_swServer->on('Start', function (Server $server) {
            $suffix = '';
            if ($this->_ymlParser->getConfigFile()) {
                $suffix = "({$this->_ymlParser->getConfigFile()})";
            }
            swoole_set_process_name("php-dubbo.{$this->_ymlParser->getApplicationName()}: master process{$suffix}");
            echo "Server start......\n";
            if (!ftruncate($this->_pidHandle, 0) || (fwrite($this->_pidHandle, $server->master_pid) === false)) {
                $server->shutdown();
            }
        });
    }

    public function onManagerStart()
    {
        $this->_swServer->on('ManagerStart', function (Server $server) {
            swoole_set_process_name("php-dubbo.{$this->_ymlParser->getApplicationName()}: manager process");

            $this->registerService();

            echo "Start providing services\n";
        });
    }

    public function onWorkerStart()
    {
        $this->_swServer->on('WorkerStart', function (Server $server, int $worker_id) {
            swoole_set_process_name("php-dubbo.{$this->_ymlParser->getApplicationName()}: worker process");
            $this->_service->load();
        });

    }

    public function onReceive()
    {
        $this->_swServer->on('Receive', function (Server $server, int $fd, int $reactor_id, string $data) {
            $monitorKey = '';
            $startTime = getMillisecond();
            try {
                $protocol = new DubboProtocol();
                $decoder = $protocol->unpackRequest($data);
                if ($protocol->getHeartBeatEvent()) {
                    $result = $this->_service->returnHeartBeat($protocol);
                    goto _result;
                }
                if ($this->_monitorFilter) {
                    $monitorKey = $decoder->getServiceName() . '/' . $decoder->getMethod();
                }
                $result = $this->_service->invoke($protocol, $decoder, $server, $fd, $reactor_id);

                if ($monitorKey) {
                    goto _success;
                }
            } catch (\Exception $exception) {
                LoggerFacade::getLogger()->error('Service Exception. ', $exception);
                $result = $this->_service->returnException($protocol, (string)$exception);
                if ($monitorKey) {
                    goto _failure;
                }
            }
            if (false) {
                _success:
                $this->_monitorFilter->normalCollect($monitorKey, $startTime, $protocol);
                goto _result;
                _failure:
                $this->_monitorFilter->failureCollect($monitorKey, $startTime);
            }
            _result:
            $server->send($fd, $result);
        });
    }

    public function onTask()
    {
        if (function_exists('swoole_onTask')) {
            $this->_swServer->on('Task', swoole_onTask);
        }
    }

    public function onFinish()
    {
        if (function_exists('swoole_onFinish')) {
            $this->_swServer->on('Finish', swoole_onFinish);
        }
    }

    public function onManagerStop()
    {
        $this->_swServer->on('ManagerStop', function (Server $serv) {
            $this->_registry->destroyService();
        });
    }


    public function registerService()
    {
        $this->_registry = RegistryFactory::getInstance($this->_ymlParser);
        $this->_service->load();
        $this->_serviceTable = $this->_service->getServiceTable();
        $serviceSet = [];
        foreach ($this->_serviceTable as $serviceName => $element) {
            $serviceSet[$serviceName] = $element['dubboUrl']->buildUrl();
        }
        $_arr = $this->_registry->registerServiceSet($serviceSet);
        echo "Register service to the registration center:  \033[32m success:{$_arr[0]}, \033[0m \033[31m fail:{$_arr[1]} \033[0m\n";
        if (!$_arr[0]) {
            $this->_swServer->shutdown();
        }
        $this->_swServer->tick(60, function () {
            zookeeper_dispatch();
        });
        if ($this->_monitorFilter) {
            $this->_monitorFilter->setRegistry($this->_registry);
            $this->_swServer->tick(60000, function () {
                $this->_monitorFilter->send();
            });
        }
    }

    public function initMonitor()
    {
        if ($this->_ymlParser->getMonitorProtocol()) {
            $this->_monitorFilter = new MonitorFilter($this->_ymlParser);
        }
    }

    public function setPidHandle($fp)
    {
        $this->_pidHandle = $fp;
    }

}