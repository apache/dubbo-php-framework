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

use Dubbo\Common\Serialization\Support\Hessian2;
use Dubbo\Common\Serialization\Support\FastJson;

class DubboUrl
{

    //reference :http://dubbo.apache.org/schema/dubbo/dubbo.xsd
    private $_query_args_name = [
        'dubbo' => '2.5.3',
        'group' => '',
        'version' => '',
        'interface' => '',
        'methods' => '',
        'timeout' => 0,
        'weight' => 1,
        'side' => 'provider',
        'application' => '',
        'anyhost' => 'true',
        'serialization' => 'hessian2'
    ];

    private $_serializationIds = [
        Hessian2::HESSIAN2_SERIALIZATION_NAME => Hessian2::HESSIAN2_SERIALIZATION_ID,
        FastJson::FASTJSON_SERIALIZATION_NAME => FastJson::FASTJSON_SERIALIZATION_ID
    ];

    private $_host = null;

    private $_port = 20880;

    private $_service = '';

    private $_scheme = 'dubbo';

    public function __construct($url = null)
    {
        if (!is_null($url)) {
            $this->parseUrl(urldecode($url));
        }
    }

    public function parseUrl($url)
    {
        $parts = parse_url($url);
        if (!$parts || (($parts['scheme'] ?? '') != $this->_scheme) || !isset($parts['host']) || !isset($parts['port']) || !isset($parts['path'])) {
            throw new DubboException("'{$url}' is not a valid dubbo url");
        }
        $this->_service = substr($parts['path'], 1);
        $this->_host = $parts['host'];
        $this->_port = $parts['port'];
        parse_str($parts['query'], $query);
        foreach ($this->_query_args_name as $field => $default) {
            if (isset($query[$field])) {
                $this->_query_args_name[$field] = $query[$field];
            }
        }
    }

    public function buildUrl()
    {
        $url = $this->_scheme . '://' . $this->_host . ':' . $this->_port . '/' . $this->_service . '?' . http_build_query($this->_query_args_name) . '&timestamp=' . getMillisecond();
        return urlencode($url);
    }

    public function getDubboVersion()
    {
        return $this->_query_args_name['dubbo'];
    }

    public function setDubboVersion($dubbo)
    {
        $this->_query_args_name['dubbo'] = $dubbo;
    }

    public function getGroup()
    {
        return $this->_query_args_name['group'];
    }

    public function setGroup($group)
    {
        $this->_query_args_name['group'] = $group;
    }

    public function getVersion()
    {
        return $this->_query_args_name['version'];
    }

    public function setVersion($version)
    {
        $this->_query_args_name['version'] = $version;
    }

    public function getInterface()
    {
        return $this->_query_args_name['interface'];
    }

    public function setInterface($interface)
    {
        $this->_query_args_name['interface'] = $interface;
    }

    public function getMethods()
    {
        return $this->_query_args_name['methods'];
    }

    public function setMethods($methods)
    {
        $this->_query_args_name['methods'] = $methods;
    }

    public function getWeight()
    {
        return $this->_query_args_name['weight'];
    }

    public function setWeight($weight)
    {
        $this->_query_args_name['weight'] = $weight;
    }

    public function getSerialization()
    {
        return $this->_query_args_name['serialization'];
    }

    public function setSerialization($serialization)
    {
        $this->_query_args_name['serialization'] = $serialization;
    }

    public function getTimeout()
    {
        return $this->_query_args_name['timeout'];
    }

    public function setTimeout($timeout)
    {
        $this->_query_args_name['timeout'] = $timeout;
    }

    public function setApplication($application)
    {
        $this->_query_args_name['application'] = $application;
    }

    public function getApplication()
    {
        return $this->_query_args_name['application'];
    }

    public function getHost()
    {
        return $this->_host;
    }

    public function setHost($host)
    {
        $this->_host = $host;
    }

    public function getPort()
    {
        return $this->_port;
    }

    public function setPort($port)
    {
        $this->_port = $port;
    }

    public function getService()
    {
        return $this->_service;
    }

    public function setService($service)
    {
        $this->_service = $service;
    }

    public function getSerializationId()
    {
        return $this->_serializationIds[$this->getSerialization()] ?? null;
    }

}
