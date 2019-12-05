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

namespace Dubbo\Common\Protocol\Dubbo;

use Dubbo\Common\Client\SwooleClient;
use Dubbo\Common\Helper\Functions;
use Dubbo\Common\DubboException;

class DubboRequest
{
    const MAX_RECV_LEN = 0x100000;//1024*1024;
    const INT_MAX_32BIT = 0x7fffffff;
    const INT_MAX_64BIT = 0x7fffffffffffffff;

    private $_dubboUrls = [];
    private $_serviceConfig = [];
    private $_chosenDubboUrl;
    private $_requestId;
    private $_ioTimeout = 10; //default
    private $_method;
    private $_args;

    public function __construct($dubboUrls, $config)
    {
        foreach ($dubboUrls as $url) {
            $this->_dubboUrls[] = new DubboUrl($url);
        }
        $this->_serviceConfig = $config;
        if (!isset($this->_serviceConfig['timeout'])) {
            $this->_serviceConfig['timeout'] = $this->_ioTimeout;
        }
    }

    public function invoke($method, ...$args)
    {
        $this->_method = $method;
        $this->_args = $args;
        $response = $this->request();
        return $response->getContents();
    }

    private function request()
    {
        $this->generateRequestId();
        $index = $this->loadBalance();
        $retry = $this->_serviceConfig['retry'] ?? 0;
        do {
            $this->_chosenDubboUrl = $this->_dubboUrls[$index];
            $protocol = new DubboProtocol();
            $data = $protocol->packRequest($this, $this->_method, $this->_args);
            $host = $this->_chosenDubboUrl->getHost();
            $port = $this->_chosenDubboUrl->getPort();
            $timeout = $this->_serviceConfig['timeout'];
            $sw_client = new SwooleClient($host, $port, $timeout);
            $sw_client->setConsumer();
            $exceptionMsg = '';
            if (!$sw_client->connect()) {
                $exceptionMsg = "Connection service timeout. line:" . __LINE__;
                goto retry;
            }
            if (!$sw_client->send($data, strlen($data))) {
                $exceptionMsg = "Swoole send() data timeout. line:" . __LINE__;
                goto retry;
            }
            $respData = $sw_client->recv(DubboProtocol::PROTOCOL_HEAD_LEN);
            if (!$respData) {
                if ($respData === false) {
                    $exceptionMsg = "Swoole recv() data timeout. line:" . __LINE__;
                } else {
                    $exceptionMsg = "Swoole recv() wrong data protocol. line:" . __LINE__;
                }
                goto retry;
            }
            $protocol->unPackHeader($respData);
            if ($protocol->getRequestId() != $this->getRequestId()) {
                $exceptionMsg = "The returned requestId is inconsistent. line:" . __LINE__;
                goto retry;
            }
            $len = $protocol->getLen();
            if (strlen(substr($respData, DubboProtocol::PROTOCOL_HEAD_LEN)) == $len) {
                break;
            }
            $startTime = getMillisecond();
            do {
                if ($len >= self::MAX_RECV_LEN) {
                    $readLen = self::MAX_RECV_LEN;
                    $len -= self::MAX_RECV_LEN;
                } else {
                    $readLen = $len;
                    $len = 0;
                }
                $chunk = $sw_client->recv($readLen);
                if ((getMillisecond() - $startTime) > ($this->_serviceConfig['timeout'] * 1000)) {
                    $exceptionMsg = "Data too large to receive data timeout. line:" . __LINE__;
                    goto _footer;
                }
                if ($chunk === false) {
                    $exceptionMsg = "Swoole recv() data timeout. line:" . __LINE__;
                    goto retry;
                } else {
                    $respData .= $chunk;
                }
            } while ($len > 0);
            if (!$len) {
                break;
            }
            retry:
            $index = ($index + 1) % count($this->_dubboUrls);
        } while ($retry-- > 0);
        _footer:
        if ($exceptionMsg) {
            throw new DubboException("{$exceptionMsg}, host:{$host}:{$port}, timeout:{$timeout}!", DubboException::BAD_RESPONSE);
        }
        $response = new DubboResponse($protocol, $protocol->unpackResponse($respData));
        return $response;
    }

    private function loadBalance()
    {
        $table = [];
        foreach ($this->_dubboUrls as $index => $item) {
            $table = array_merge($table, array_pad([], $item->getWeight() % 10, $index));
        }
        return $table[mt_rand(0, count($table) - 1)];
    }

    public function generateRequestId()
    {
        if (PHP_INT_MAX == self::INT_MAX_32BIT) {
            $this->_requestId = mt_rand(0x3B9ACA00 & PHP_INT_MAX, PHP_INT_MAX);
        } else if (PHP_INT_MAX == self::INT_MAX_64BIT) {
            $this->_requestId = mt_rand(0xDE0B6B3A7640000 & PHP_INT_MAX, PHP_INT_MAX);
        } else {
            $this->_requestId = mt_rand();
        }
    }

    public function getRequestId()
    {
        return $this->_requestId;
    }

    public function getChosenDubboUrl()
    {
        return $this->_chosenDubboUrl;
    }

    public function getMethod()
    {
        return $this->_method;
    }

    public function getArgs()
    {
        return $this->_args;
    }

}