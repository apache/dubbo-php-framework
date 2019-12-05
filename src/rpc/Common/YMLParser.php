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

namespace Dubbo\Common;

use Dubbo\Common\DubboException;

class YMLParser
{

    private $_swoole_settings;
    private $_parameter;
    private $_application;
    private $_protocol;
    private $_registry;
    private $_monitor;
    private $_provider;
    private $_service;
    private $_discoverer;
    private $_reference;
    private $_configFile;

    public function __construct(string $configFile, string $cacheFile = null)
    {
        $config = null;
        if (is_file($cacheFile)) {
            $config = include $cacheFile;
        }
        if (!$config) {
            $this->_configFile = $configFile;
            $config = yaml_parse_file($configFile);
            $reference = [];
            foreach ($config['reference'] ?? [] as $key => $value) {
                $service_name = $value['service_name'] ?? '';
                if (!$service_name) {
                    throw new DubboException("Please set 'reference.[{$key}].service_name' in the configuration file\n");
                }
                unset($value['service_name']);
                $reference[$service_name] = $value;
            }
            if ($reference) {
                $config['reference'] = $reference;
            }
            if ($cacheFile && $config) {
                file_put_contents($cacheFile, '<?php return ' . var_export($config, true) . ';');
            }
        }
        if (!$config) {
            throw new DubboException(" '{$configFile}' parsing failed");
        }
        $this->_swoole_settings = $config['swoole_settings'] ?? [];
        $this->_parameter = $config['parameter'] ?? [];
        $this->_application = $config['application'] ?? [];
        $this->_protocol = $config['protocol'] ?? [];
        $this->_registry = $config['registry'] ?? [];
        $this->_monitor = $config['monitor'] ?? [];
        $this->_provider = $config['provider'] ?? [];
        $this->_service = $config['service'] ?? [];
        //consumer
        $this->_reference = $config['reference'] ?? [];
        $this->_discoverer = $config['discoverer'] ?? [];
    }

    public function providerRequired()
    {
        $required_key = $this->getProtocolHost() ? '' : ' protocol.host ';
        $required_key .= $this->getProtocolPort() ? '' : ' protocol.port ';
        $required_key .= $this->getRegistryAddress() ? '' : ' registry.address ';
        $required_key .= $this->getApplicationName() ? '' : ' application.name ';
        $required_key .= $this->getServiceNamespace() ? '' : ' service.namespace ';
        $required_key .= $this->getApplicationPidFile() ? '' : ' application.pid_file ';
        if ($required_key) {
            throw new DubboException("Please set '{$required_key}' in the configuration file\n");
        }
    }

    public function consumerRequired()
    {
        $required_key = $this->getDiscovererHost() ? '' : ' discoverer.host ';
        $required_key .= $this->getDiscovererPort() ? '' : ' discoverer.port ';
        if ($required_key) {
            throw new DubboException("Please set '{$required_key}' in the configuration file\n");
        }
    }

    public function getProtocolHost()
    {
        return $this->_protocol['host'];
    }

    public function getProtocolPort()
    {
        return $this->_protocol['port'];
    }

    public function getRegistryAddress()
    {
        return $this->_registry['address'];
    }

    public function getRegistryProtocol()
    {
        return $this->_registry['protocol'];
    }

    public function getServiceNamespace()
    {
        return $this->_service['namespace'];
    }

    public function getApplicationName()
    {
        return $this->_application['name'];
    }

    public function getDubboVersion()
    {
        return $this->_parameter['dubbo_version'];
    }

    public function getProtocolSerialization()
    {
        return $this->_protocol['serialization'];
    }

    public function getProviderGroup()
    {
        return $this->_provider['group'];
    }

    public function getProviderVersion()
    {
        return $this->_provider['version'];
    }

    public function getMonitorProtocol()
    {
        return $this->_monitor['protocol'] ?? '';
    }

    public function getMonitorAddress()
    {
        return $this->_monitor['address'] ?? '';
    }

    public function getMonitorService($default = null)
    {
        return $this->_parameter['service'] ?? $default;
    }

    public function getParameterClientIp()
    {
        return $this->_parameter['client_ip'] ?? swoole_get_local_ip();
    }

    public function getApplicationLogDir($default = './logs')
    {
        return $this->_application['log_dir'] ?? $default;
    }

    public function getApplicationLoggerLevel($default = 'INFO')
    {
        return $this->_application['log_level'] ?? $default;
    }

    public function getApplicationPidFile()
    {
        return $this->_application['pid_file'];
    }

    public function getDiscovererHost()
    {
        return $this->_discoverer['host'];
    }

    public function getDiscovererPort()
    {
        return $this->_discoverer['port'];
    }

    public function getDiscovererRetry()
    {
        return max(($this->_discoverer['retry'] ?? 0), 0);
    }

    public function getDiscovererUnixSocket()
    {
        return $this->_discoverer['unixsocket'] ?? '';
    }

    public function getReference()
    {
        return $this->_reference;
    }

    public function getParameter()
    {
        return $this->_parameter;
    }

    public function getSwooleSettings()
    {
        return $this->_swoole_settings;
    }

    public function getConfigFile()
    {
        return $this->_configFile;
    }

    public function generateCacheFile($cacheFile)
    {

    }


}