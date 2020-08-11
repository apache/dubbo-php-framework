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

namespace Dubbo\Registry\Client;

use Dubbo\Common\Logger\LoggerFacade;
use Dubbo\Common\YMLParser;
use \Zookeeper;

class ZookeeperClient
{
    private $_handle;
    private $_rootNode = '/dubbo';
    private $_providersNode = 'providers';
    private $_serviceSet;
    private $_clusterIp;

    public function __construct(YMLParser $ymlParser)
    {
        $this->_clusterIp = $ymlParser->getRegistryAddress();
        $this->_handle = new Zookeeper();
        $this->connect();
    }

    public function connect()
    {
        $this->_handle->connect($this->_clusterIp, function ($type, $state, $path) {
            LoggerFacade::getLogger()->info('zookeeper connect() callback.', $type, $state, $path);
            if ($state == Zookeeper::CONNECTED_STATE) {
                $this->registerServiceSet($this->_serviceSet);
            } elseif ($state == Zookeeper::EXPIRED_SESSION_STATE) {
                $this->connect();
            }
        });
    }

    public function registerServiceSet($serviceSet)
    {
        if (is_null($serviceSet)) {
            return;
        }
        if (is_null($this->_serviceSet)) {
            $this->_serviceSet = $serviceSet;
        }
        $succCount = 0;
        $failCount = 0;
        foreach ($serviceSet as $serviceName => $url) {
            $path = $this->registerService($serviceName, $url);
            if ($path) {
                $succCount++;
            } else {
                $failCount++;
            }
        }
        return [$succCount, $failCount];
    }

    public function registerService($serviceName, $url)
    {
        $path = $this->_rootNode . '/' . $serviceName . '/' . $this->_providersNode . '/' . $url;
        if (!$this->_handle->exists($path)) {
            $path = $this->_createPath($path, Zookeeper::EPHEMERAL);
            if ($path) {
                LoggerFacade::getLogger()->info("Register.  ", $path);
            } else {
                LoggerFacade::getLogger()->error("Register fail. ", $path);
            }
        }
        return $path;
    }

    public function destroyService()
    {
        foreach ($this->_serviceSet as $serviceName => $url) {
            $path = $this->_rootNode . '/' . $serviceName . '/' . $this->_providersNode . '/' . $url;
            if ($this->_handle->exists($path)) {
                $this->_handle->delete($path);
                LoggerFacade::getLogger()->info("Unregister.", $path);
            }
        }
    }

    private function _createPath($path, $flag)
    {
        if (!$path) {
            return;
        }
        if (!$this->_handle->exists($path)) {
            $prevPath = substr($path, 0, strrpos($path, '/'));
            if ($prevPath) {
                $prevPath = $this->_createPath($prevPath, null);
                if (!$prevPath) {
                    return false;
                }
            }
            $path = $this->_handle->create($path, null, [['perms' => Zookeeper::PERM_ALL, 'scheme' => 'world', 'id' => 'anyone']], $flag);
            if (!$path) {
                LoggerFacade::getLogger()->error("Create path fail. ", $path);
                return false;
            }
        }
        return $path;
    }

    public function getChildren($path, $watcher = null)
    {
        if (!$this->_handle->exists($path)) {
            LoggerFacade::getLogger()->warn("getChildren() path no exists. ", $path);
            return false;
        }
        return $this->_handle->getChildren($path, $watcher);
    }

    public function exists($path)
    {
        return $this->_handle->exists($path);
    }

    public function close()
    {
        $this->_handle->close();
    }


}