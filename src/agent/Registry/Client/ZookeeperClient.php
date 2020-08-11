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

namespace Dubbo\Agent\Registry\Client;

use Dubbo\Agent\YMLParser;
use Zookeeper;
use ZookeeperConnectionException;
use Dubbo\Agent\Logger\LoggerFacade;

class ZookeeperClient extends BaseClient
{
    /* zookeeper event type */
    private $_eventType = [
        Zookeeper::CREATED_EVENT => 'CREATED_EVENT_DEF',
        Zookeeper::DELETED_EVENT => 'DELETED_EVENT_DEF',
        Zookeeper::CHANGED_EVENT => 'CHANGED_EVENT_DEF',
        Zookeeper::CHILD_EVENT => 'CHILD_EVENT_DEF',
        Zookeeper::SESSION_EVENT => 'SESSION_EVENT_DEF',
        Zookeeper::NOTWATCHING_EVENT => 'NOTWATCHING_EVENT_DEF',
    ];
    /* zookeeper state */
    private $_state = [
        Zookeeper::EXPIRED_SESSION_STATE => 'EXPIRED_SESSION_STATE_DEF',
        Zookeeper::AUTH_FAILED_STATE => 'AUTH_FAILED_STATE_DEF',
        Zookeeper::CONNECTING_STATE => 'CONNECTING_STATE_DEF',
        Zookeeper::ASSOCIATING_STATE => 'ASSOCIATING_STATE_DEF',
        Zookeeper::CONNECTED_STATE => 'CONNECTED_STATE_DEF',
        Zookeeper::NOTCONNECTED_STATE => 'NOTCONNECTED_STATE',
    ];

    private $_client;

    public function __construct(YMLParser $ymlParser)
    {
        parent::__construct($ymlParser);
        $this->_client = new Zookeeper();
        $this->connect();
    }

    private function connect()
    {
        $this->_client->connect($this->_ymlParser->getRegistryAddress(), function ($type, $state, $path) {
            LoggerFacade::getLogger()->info('zookeeper connect() callback.', $this->_eventType[$type], $this->_state[$state], $path);
            if ($state == Zookeeper::CONNECTED_STATE) {
                foreach ($this->_ymlParser->getWatchNodes() as $service) {
                    $this->getProvider($service);
                }
            } elseif ($state == Zookeeper::EXPIRED_SESSION_STATE) {
                $this->connect();
            }
        });
    }

    private function getProvider($service)
    {
        $providerPath = '/dubbo/' . $service . '/providers';
        if (!$this->_client->exists($providerPath)) {
            LoggerFacade::getLogger()->error('not found service.', $providerPath);
            return;
        }
        $zk_providers = $this->_client->getchildren($providerPath, function ($type, $state, $path) use ($service) {
            LoggerFacade::getLogger()->info('zookeeper getchildren() callback.', $this->_eventType[$type], $this->_state[$state], $path);
            if ($type == Zookeeper::CHILD_EVENT) {
                $this->getProvider($service);
            }
        });
        $sw_provider = $this->_table->get($service, 'provider') ?: '';
        $sw_provider = json_decode($sw_provider, true);
        if ($zk_providers != $sw_provider) {
            $provider = json_encode($zk_providers);
            $state = $this->_table->set($service, ['provider' => $provider]);
            if (!$state) {
                LoggerFacade::getLogger()->error('swoole_table set() fail.', $service, $provider);
            }
            LoggerFacade::getLogger()->info('swoole_table set().', $service, $provider);
        }
    }

}