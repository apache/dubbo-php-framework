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

namespace Dubbo\Common\Client;

use Dubbo\Common\Logger\LoggerFacade;
use Swoole\Client;

class SwooleClient
{
    private $_client;

    private $_host;

    private $_port;

    private $_timeout;

    public function __construct($host, $port, $timeout = NULL, $type = SWOOLE_SOCK_TCP)
    {
        static $_clients = [];
        $key = $host . ':' . $port;
        if (!isset($_clients[$key])) {
            $_clients[$key] = new Client($type, SWOOLE_SOCK_SYNC);
        }
        $this->_client = $_clients[$key];
        $this->_host = $host;
        $this->_port = $port;
        $this->_timeout = $timeout;

    }

    public function setDiscoverer()
    {
        $this->set([
            'open_eof_check' => true,
            'package_eof' => "\r\n\r\n"
        ]);
    }

    public function setConsumer()
    {
        $this->set([
            'open_length_check' => TRUE,
            'package_length_offset' => 12,
            'package_body_offset' => 16,
            'package_length_type' => 'N',
            'package_max_length' => 1024 * 1024 * 8, //dubbo默认8M
        ]);
    }

    public function set($settings)
    {
        $stat = $this->_client->set($settings);
        if (!$stat) {
            LoggerFacade::getLogger()->warn("swoole set(). parameter: " . json_encode($settings) . ", fail. " . __FILE__ . ':' . __LINE__);
        }
        return $stat;
    }

    public function connect()
    {
        if ($this->isConnected()) {
            return true;
        }
        $stat = $this->_client->connect($this->_host, $this->_port, $this->_timeout);
        if (!$stat) {
            LoggerFacade::getLogger()->warn("swoole connect(). parameter: {$this->_host}:{$this->_port}, timeout:{$this->_timeout} fail. " . __FILE__ . ':' . __LINE__);
        }
        return $stat;
    }

    public function isConnected()
    {
        return $this->_client->isConnected();
    }

    public function send($data)
    {
        $stat = $this->_client->send($data);
        if (!$stat) {
            LoggerFacade::getLogger()->warn("swoole send(). parameter: {$data} fail. " . __FILE__ . ':' . __LINE__);
        }
        return $stat;
    }

    public function recv($size = 65535)
    {
        $data = $this->_client->recv($size);
        if (!$data) {
            LoggerFacade::getLogger()->warn("swoole recv(). parameter: {$size} fail. " . __FILE__ . ':' . __LINE__);
        }
        return $data;

    }

    public function close()
    {
        $stat = $this->_client->close();
        if (!$stat) {
            DubboConsumer::logger()->warn("swoole close(). fail. " . __FILE__ . ':' . __LINE__);
        }
        return $stat;
    }

    public function getHost()
    {
        return $this->_host;
    }

    public function getPort()
    {
        return $this->_port;
    }

    public function getTimeout()
    {
        return $this->_timeout;
    }
}