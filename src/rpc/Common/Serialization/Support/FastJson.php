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

namespace Dubbo\Common\Serialization\Support;

use Dubbo\Common\Protocol\Dubbo\DubboParam;
use Dubbo\Common\Protocol\Dubbo\DubboProtocol;
use Dubbo\Common\Protocol\Dubbo\DubboRequest;


class FastJson
{
    const FASTJSON_SERIALIZATION_ID = 6;
    const FASTJSON_SERIALIZATION_NAME = 'fastjson';

    private $_variablePart;

    public function serializeRequest(DubboRequest $request)
    {
        $dubboUrl = $request->getChosenDubboUrl();
        $args = new DubboParam($request->getArgs());
        $variablePart = json_encode($dubboUrl->getDubboVersion()) . PHP_EOL;
        $variablePart .= json_encode($dubboUrl->getService()) . PHP_EOL;
        $variablePart .= json_encode($dubboUrl->getVersion()) . PHP_EOL;
        $variablePart .= json_encode($request->getMethod()) . PHP_EOL;
        $variablePart .= json_encode($args->typeRefs()) . PHP_EOL;
        foreach ($args->getParams() as $arg) {
            if (is_object($arg)) {
                $arg = $this->universalObjectTostdClass($arg, new \stdClass());
            }
            $variablePart .= json_encode($arg) . PHP_EOL;
        }
        $variablePart .= json_encode([
            'path' => $dubboUrl->getService(),
            'interface' => $dubboUrl->getInterface(),
            'timeout' => $dubboUrl->getTimeout(),
            'group' => $dubboUrl->getGroup(),
            'version' => $dubboUrl->getVersion()
        ]);
        return $variablePart;
    }

    public function universalObjectTostdClass($arg, $result)
    {
        foreach ($arg->object() as $key => $value) {
            $type = gettype($value);
            switch ($type) {
                case 'boolean':
                case 'integer':
                case 'double':
                case 'string':
                case 'array':
                    $result->$key = $value;
                    break;
                case 'object':
                    $anonymousObject = $this->universalObjectTostdClass($value, new \stdClass());
                    $count = count(get_object_vars($anonymousObject));
                    if ($count == 1 && property_exists($anonymousObject, 'value')) {
                        $result->$key = $anonymousObject->value;
                    } else {
                        $result->$key = $anonymousObject;
                    }
                    break;
            }
        }
        return $result;
    }

    public function unserializeRequest(DubboProtocol $protocol, $variablePart)
    {
        $_arr = explode(PHP_EOL, $variablePart);
        $this->setAttachments(json_decode(array_pop($_arr), true));
        $this->setDubboVersion(json_decode($_arr[0] ?? ''));
        $this->setServiceName(json_decode($_arr[1] ?? ''));
        $this->setServiceVersion(json_decode($_arr[2] ?? ''));
        $method = $_arr[3] ?? '';
        $paramIndex = 5;
        if ($method == '$invoke') {
            $method = $_arr[5] ?? '';
            $paramIndex = 6;
        }
        $this->setMethod(json_decode($method));
        $this->setParameterType(json_decode($_arr[4] ?? ''));
        $arguments = [];
        foreach ($_arr as $key => $value) {
            if ($key < $paramIndex) {
                continue;
            }
            $arguments[] = json_decode($value, true);
        }
        $this->setArguments($arguments);
        return $this;
    }

    public function serializeResponse(DubboProtocol $protocol)
    {
        $variablePart = $protocol->getVariablePart();
        return $protocol->getVariablePartType() . PHP_EOL . json_encode($variablePart);
    }

    public function unserializeResponse(DubboProtocol $protocol, $variablePart)
    {
        $_arr = explode(PHP_EOL, $variablePart);
        return [$_arr[0], json_decode($_arr[1], true)];
    }

    private function setDubboVersion($version)
    {
        $this->_variablePart['dubboVersion'] = $version;
    }

    public function getDubboVersion()
    {
        return $this->_variablePart['dubboVersion'];
    }

    private function setServiceName($serviceName)
    {
        $this->_variablePart['serviceName'] = $serviceName;
    }

    public function getServiceName()
    {
        return $this->_variablePart['serviceName'];
    }

    private function setServiceVersion($version)
    {
        $this->_variablePart['serviceVersion'] = $version;
    }

    public function getServiceVersion()
    {
        return $this->_variablePart['serviceVersion'];
    }

    private function setMethod($method)
    {
        $this->_variablePart['method'] = $method;
    }

    public function getMethod()
    {
        return $this->_variablePart['method'];
    }

    private function setParameterType($type)
    {
        $this->_variablePart['parameterType'] = $type;
    }

    public function getParameterType()
    {
        return $this->_variablePart['parameterType'];
    }

    private function setArguments($arguments)
    {
        $this->_variablePart['arguments'] = $arguments;
    }

    public function getArguments()
    {
        return $this->_variablePart['arguments'];
    }

    private function setAttachments($attachments)
    {
        $this->_variablePart['attachments'] = $attachments;
    }

    public function getAttachments()
    {
        return $this->_variablePart['attachments'];
    }


}