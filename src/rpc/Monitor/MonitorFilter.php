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

namespace Dubbo\Monitor;

use Dubbo\Common\Logger\LoggerFacade;
use Swoole\Table;
use Dubbo\Common\YMLParser;
use Dubbo\Common\Protocol\Dubbo\DubboRequest;
use Dubbo\Common\Protocol\Dubbo\DubboParam;

class MonitorFilter
{

    private $_swTable;
    private $_ymlParser;
    private $_registry;
    private $_monitorService = 'com.alibaba.dubbo.monitor.MonitorService';

    const COLUMN_SUCCESS = 'success';
    const COLUMN_FAILURE = 'failure';
    const COLUMN_ELAPSED = 'elapsed';
    const COLUMN_MAX_ELAPSED = 'max.elapsed';
    const COLUMN_COUNT = 'count';
    const COLUMN_MAX_CONCURRENT = 'max.concurrent';
    const COLUMN_INPUT = 'input';
    const COLUMN_MAX_INPUT = 'max.input';
    const COLUMN_OUTPUT = 'output';
    const COLUMN_MAX_OUTPUT = 'max.output';


    public function __construct(YMLParser $ymlParser)
    {
        $this->_ymlParser = $ymlParser;
        $_swTable = new Table(10240);
        $_swTable->column(self::COLUMN_SUCCESS, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_FAILURE, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_ELAPSED, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_MAX_ELAPSED, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_COUNT, Table::TYPE_INT);
        $_swTable->column(self::COLUMN_MAX_CONCURRENT, Table::TYPE_INT);
        $_swTable->column(self::COLUMN_INPUT, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_MAX_INPUT, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_OUTPUT, Table::TYPE_INT, 8);
        $_swTable->column(self::COLUMN_MAX_OUTPUT, Table::TYPE_INT, 8);
        $_swTable->create();
        $this->_swTable = $_swTable;
    }

    public function incrSuccess($key)
    {
        $this->_swTable->incr($key, self::COLUMN_SUCCESS);
    }

    public function getSuccess($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_SUCCESS);
    }

    public function incrFailure($key)
    {
        $this->_swTable->incr($key, self::COLUMN_FAILURE);
    }

    public function getFailure($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_FAILURE);
    }

    public function incrElapsed($key, $startTime)
    {
        $elapsed = bcsub(getMillisecond(), $startTime);
        $maxElapsed = $this->_swTable->get($key, self::COLUMN_MAX_ELAPSED);
        if ($elapsed > $maxElapsed) {
            $this->_swTable->set($key, [self::COLUMN_MAX_ELAPSED => $elapsed]);
        }
        $this->_swTable->incr($key, self::COLUMN_ELAPSED, $elapsed);
    }

    public function getElapsed($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_ELAPSED);
    }

    public function getMaxElapsed($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_MAX_ELAPSED);
    }

    public function incrCount($key)
    {
        $this->_swTable->incr($key, self::COLUMN_COUNT);
    }

    public function getCount($key)
    {
        return $this->_swTable->get($key, self::COLUMN_COUNT);
    }

    public function getMaxConcurrent($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_MAX_CONCURRENT);
    }

    public function setMaxConcurrent($key, $num)
    {
        $this->_swTable->set($key, [self::COLUMN_MAX_CONCURRENT => $num]);
    }

    public function incrInput($key, $size)
    {
        $maxElapsed = $this->_swTable->get($key, self::COLUMN_MAX_INPUT);
        if ($size > $maxElapsed) {
            $this->_swTable->set($key, [self::COLUMN_MAX_INPUT => $size], $size);
        }
        $this->_swTable->incr($key, self::COLUMN_INPUT, $size);
    }

    public function getInput($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_INPUT);
    }

    public function getMaxInput($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_MAX_INPUT);
    }

    public function incrOutput($key, $size)
    {
        $maxElapsed = $this->_swTable->get($key, self::COLUMN_MAX_OUTPUT);
        if ($size > $maxElapsed) {
            $this->_swTable->set($key, [self::COLUMN_MAX_OUTPUT => $size]);
        }
        $this->_swTable->incr($key, self::COLUMN_OUTPUT, $size);
    }

    public function getOutput($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_OUTPUT);
    }

    public function getMaxOutput($key)
    {
        return (string)$this->_swTable->get($key, self::COLUMN_MAX_OUTPUT);
    }

    public function resetTable()
    {
        foreach ($this->_swTable as $key => $value) {
            $this->_swTable->del($key);
        }
    }

    public function send()
    {
        try {
            $monitorProvider = $this->getMonitorProvider();
            if (!$this->_swTable->count() || !$monitorProvider) {
                return;
            }
            $parameters = [
                'application' => $this->_ymlParser->getApplicationName(),
                'provider' => $this->_ymlParser->getProtocolHost() . ':' . $this->_ymlParser->getProtocolPort()
            ];
            $dubboRequest = new DubboRequest($monitorProvider, ['timeout' => 1]);
            foreach ($this->_swTable as $key => $value) {
                list($service, $method) = explode('/', $key);
                $tps = bcdiv($this->getCount($key), 60);
                $maxTps = ($this->getMaxConcurrent($key) ?: $tps);
                if ($tps > $maxTps) {
                    $this->setMaxConcurrent($key, $tps);
                    $maxTps = $tps;
                }
                $collectData = [
                    'concurrent' => $tps,
                    'elapsed' => $this->getElapsed($key),
                    'failure' => $this->getFailure($key),
                    'input' => $this->getInput($key),
                    'interface' => $service,
                    'max.concurrent' => $maxTps,
                    'max.elapsed' => $this->getMaxElapsed($key),
                    'max.input' => $this->getMaxInput($key),
                    'max.output' => $this->getMaxOutput($key),
                    'method' => $method,
                    'output' => $this->getOutput($key),
                    'success' => $this->getSuccess($key),
                    'timestamp' => getMillisecond(),
                ];
                $parameters = array_merge($parameters, $collectData);
                $property['parameters'] = DubboParam::object('java.util.Collections', $parameters);
                $property['path'] = $key;
                if ($parameters['provider']) {
                    $property['port'] = 0;
                    $property['host'] = $this->_ymlParser->getProtocolHost();
                } else {
                    $property['port'] = 0;
                    $property['host'] = $this->_ymlParser->getParameterClientIp();
                }
                $property['password'] = '';
                $property['username'] = '';
                $property['protocol'] = 'count';
                $dubboRequest->invoke('collect',
                    DubboParam::object('com.alibaba.dubbo.common.URL', $property));
                $this->resetTable();
            }
        } catch (\Exception $exception) {
            LoggerFacade::getLogger()->error("Monitor exception: ", $exception);
        }

    }

    public function getMonitorProvider()
    {
        static $_path;
        if (is_null($_path)) {
            $_path = '/dubbo/' . $this->_ymlParser->getMonitorService($this->_monitorService) . '/providers';
        }
        return $this->_registry->getChildren($_path);
    }

    public function setRegistry($registry)
    {
        $this->_registry = $registry;

    }

    public function normalCollect($monitorKey, $startTime, $protocol)
    {
        $this->incrCount($monitorKey);
        $this->incrSuccess($monitorKey);
        $this->incrElapsed($monitorKey, $startTime);
        $this->incrOutput($monitorKey, $protocol->getLen());
    }

    public function failureCollect($monitorKey, $startTime)
    {
        $this->incrCount($monitorKey);
        $this->incrFailure($monitorKey);
        $this->incrElapsed($monitorKey, $startTime);
    }
}