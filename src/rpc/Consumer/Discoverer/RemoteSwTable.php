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

namespace Dubbo\Consumer\Discoverer;

use Dubbo\Common\Client\SwooleClient;
use Dubbo\Common\DubboException;
use Dubbo\Common\YMLParser;

class RemoteSwTable
{
    private $_timeout = 0.5; //default timeout

    private $_swClient;
    private $_swTcpClient;
    private $_swUnixSocketClient;

    private $_ymlParser;

    public function __construct(YMLParser $ymlParser)
    {
        $this->_ymlParser = $ymlParser;
        $this->initUnixSocketClient();
        if (!$this->_swUnixSocketClient) {
            $this->initTcpClient();
        }
    }

    private function initTcpClient()
    {
        $host = $this->_ymlParser->getDiscovererHost();
        $port = $this->_ymlParser->getDiscovererPort();
        if (!$this->_swTcpClient) {
            $this->_swTcpClient = $this->_swTcpClient = new SwooleClient($host, $port, $this->_timeout);
            $this->_swTcpClient->setDiscoverer();
            $this->_swClient[1] = $this->_swTcpClient;
        }
    }

    private function initUnixSocketClient()
    {
        $unixSocket = $this->_ymlParser->getDiscovererUnixSocket();
        if (!$this->_swUnixSocketClient) {
            $this->_swUnixSocketClient = new SwooleClient($unixSocket, 0, $this->_timeout, SWOOLE_SOCK_UNIX_STREAM);
            $this->_swClient[0] = $this->_swUnixSocketClient;
        }
    }

    public function getProviders($service, $query)
    {

        $retry = $this->_ymlParser->getDiscovererRetry();
        if (isset($this->_swClient[0])) {
            $client = $this->_swClient[0];
        } else {
            $client = $this->_swClient[1];
        }
        do {
            if ($client->connect()) {
                break;
            }
            if (!is_object($this->_swTcpClient)) {
                $this->initTcpClient();
            }
            if (!is_object($this->_swUnixSocketClient)) {
                $this->initUnixSocketClient();
            }
            $client = $this->_swClient[1] ?? $this->_swClient[0];
        } while ($retry-- > 0);
        if (!$client->isConnected()) {
            throw new DubboException("Discoverer cannot connect to {$client->getHost()}:{$client->getPort()}");
        }
        $filter = [];
        foreach ($query as $value) {
            $filter[] = ($value['group'] ?? '-') . ':' . ($value['version'] ?? '-');
        }
        $filter = array_unique($filter);
        $filter = implode('|', $filter);
        if (!$client->send($service . '|' . $filter)) {
            $client->close();
            throw new DubboException("Discoverer send() data fail!");
        }
        $content = $client->recv();
        if (false === $content) {
            throw new DubboException("Discoverer recv() data fail!");
        }
        return json_decode($content, true);

    }
}