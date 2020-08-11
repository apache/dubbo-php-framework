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

namespace Dubbo\Agent\Server;

use Dubbo\Agent\YMLParser;
use Dubbo\Agent\Registry\FilterProvider;
use Dubbo\Agent\DubboAgentException;
use Swoole\Server;
use Swoole\Coroutine;

class SwooleServer
{
    private $_ymlParser;

    private $_server;

    private $_callbackList;

    private $_registry;

    private $_host = '127.0.0.1'; //default

    private $_port = '9091';

    private $_pidHandle;

    public function __construct(YMLParser $ymlParser, $callbackList)
    {
        $this->_ymlParser = $ymlParser;
        $this->_callbackList = $callbackList;
    }

    public function startup()
    {
        $unixSocket = $this->_ymlParser->getServerUnixSocket();

        $this->_server = new Server($this->_ymlParser->getServerHost($this->_host), $this->_ymlParser->getServerPort($this->_port), SWOOLE_BASE);
        if ($unixSocket) {
            $this->_server->addlistener($unixSocket, 0, SWOOLE_UNIX_STREAM);
        }
        $this->_server->set(
            [
                'daemonize' => $this->_ymlParser->getServerDaemonize(),
            ]
        );
        $this->onWorkerStart();
        $this->onReceive();
        $this->_server->start();
    }

    public function onWorkerStart()
    {
        $this->_server->on('WorkerStart', function (Server $server, int $worker_id) {
            swoole_set_process_name("php-dubbo-agent.{$this->_ymlParser->getApplicationName()}: master process ({$this->_ymlParser->getFilename()})");
            if (!ftruncate($this->_pidHandle, 0) || (fwrite($this->_pidHandle, $server->master_pid) === false)) {
                $server->shutdown();
            }
            if (isset($this->_callbackList['registry'])) {
                $this->_registry = call_user_func($this->_callbackList['registry']);
                $this->_server->tick(60,function (){
                    zookeeper_dispatch();
                });
            }
        });
    }

    public function onReceive()
    {
        $this->_server->on('Receive', function (Server $server, int $fd, int $reactor_id, string $data) {
            $filterProvider = new FilterProvider($this->_registry);
            $provider = $filterProvider->find_provider($data);
            $server->send($fd, $provider . "\r\n\r\n");
        });
    }

    public function setPidHandle($fp)
    {
        $this->_pidHandle = $fp;
    }

}