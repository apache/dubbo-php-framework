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

class YMLParser
{

    private $_application;
    private $_registry;
    private $_server;
    private $_swooleTable;
    private $_watchNodes;
    private $_reference = [];
    private $_filename;

    public function __construct($filename)
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new DubboAgentException(" '{$filename}' is not a file or unreadable");
        }
        $_arr = yaml_parse_file($filename);
        if (!$_arr) {
            throw new DubboAgentException(" '{$filename}' parsing failed");
        }
        $this->required($_arr);
        $consumerConfigFile = $this->getApplicationConsumerConfigFile();
        if (!is_file($consumerConfigFile) || !is_readable($consumerConfigFile)) {
            throw new DubboAgentException("consumer_config_file: '{$consumerConfigFile}' is not a file or unreadable");
        }
        $_arr = yaml_parse_file($consumerConfigFile);
        if (!$_arr) {
            throw new DubboAgentException("consumer_config_file: '{$consumerConfigFile}' parsing failed");
        }
        $unixSocket = $this->getServerUnixSocket();
        $fp = null;
        if (!file_exists($unixSocket) && !($fp = @fopen($unixSocket, 'w'))) {
            throw new DubboAgentException("Cannot create unixsocket file: '{$unixSocket}'");

        }
        if ($fp) {
            fclose($fp);
        }
        foreach ($_arr['reference'] ?? [] as $value){
            if($value['service_name']??''){
                $this->_reference[] = $value['service_name'];
            }

        }
        $this->_filename = $filename;
    }

    public function required($_arr)
    {
        $required_key = '';
        if (!isset($_arr['application']['name']) || !$_arr['application']['name']) {
            $required_key .= 'application.name,';
        }
        if (!isset($_arr['application']['consumer_config_file']) || !$_arr['application']['consumer_config_file']) {
            $required_key .= 'application.consumer_config_file,';
        }
        if (!isset($_arr['registry']['address']) || !$_arr['registry']['address']) {
            $required_key .= 'registry.address,';
        }
        if (!isset($_arr['server']['host']) || !$_arr['server']['host']) {
            $required_key .= 'server.host,';
        }
        if (!isset($_arr['server']['port']) || !$_arr['server']['port']) {
            $required_key .= 'server.port,';
        }
        if (!isset($_arr['server']['pid_file']) || !$_arr['server']['pid_file']) {
            $required_key .= 'server.pid_file,';
        }
        if ($required_key) {
            throw new DubboAgentException("Please set '{$required_key}' in the configuration file");
        }
        $this->_application = $_arr['application'];
        $this->_registry = $_arr['registry'];
        $this->_server = $_arr['server'];
        $this->_swooleTable = $_arr['swoole_table'] ?? [];
        $this->_watchNodes = $_arr['watch_nodes'] ?? [];
    }

    public function getApplicationName()
    {
        return $this->_application['name'];
    }

    public function getApplicationLogLevel()
    {
        return $this->_application['log_level'];
    }

    public function getApplicationLogDir()
    {
        return $this->_application['log_dir'];
    }

    public function getApplicationConsumerConfigFile()
    {
        return $this->_application['consumer_config_file'];
    }

    public function getRegistryProtocol()
    {
        return $this->_registry['protocol'];
    }

    public function getRegistryAddress()
    {
        return $this->_registry['address'];
    }

    public function getServerHost($default = 0)
    {
        if (!isset($this->_server['host']) || !$this->_server['host']) {
            return $default;
        }
        return $this->_server['host'];
    }

    public function getServerPort($default = 0)
    {
        if (!isset($this->_server['port']) || !$this->_server['port']) {
            return $default;
        }
        return $this->_server['port'];
    }

    public function getServerDaemonize($default = 0)
    {
        if (!isset($this->_server['daemonize']) || !$this->_server['daemonize']) {
            return $default;
        }
        return $this->_server['daemonize'];
    }

    public function getServerUnixSocket()
    {
        return $this->_server['unixsocket'] ?? '';
    }

    public function getServerPidFile()
    {
        return $this->_server['pid_file'] ?? '';
    }

    public function getSwooleTableSize($default = null)
    {
        if (!isset($this->_swooleTable['size']) || !$this->_swooleTable['size']) {
            return $default;
        }
        return $this->_swooleTable['size'];
    }

    public function getSwooleTableColumnSize($default = null)
    {
        if (!isset($this->_swooleTable['column_size']) || !$this->_swooleTable['column_size']) {
            return $default;
        }
        return $this->_swooleTable['column_size'];
    }

    public function getWatchNodes()
    {
        return array_unique(array_merge($this->_reference, $this->_watchNodes));
    }

    public function getFilename()
    {
        return $this->_filename;
    }

}