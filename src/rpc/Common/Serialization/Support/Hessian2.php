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

namespace dubbo\common\serialization\Support;

use Dubbo\Common\DubboException;
use Dubbo\Common\Protocol\Dubbo\DubboRequest;
use Dubbo\Common\Protocol\Dubbo\DubboParam;
use Dubbo\Common\Protocol\Dubbo\DubboProtocol;
use Dubbo\Common\Protocol\Dubbo\DubboResponse;
use Icecave\Flax\Serialization\Encoder as HessionEncoder;
use Icecave\Flax\Serialization\Decoder as HessionDecoder;

class Hessian2
{
    const HESSIAN2_SERIALIZATION_ID = 2;
    const HESSIAN2_SERIALIZATION_NAME = 'hessian2';

    public function serializeRequest(DubboRequest $request)
    {
        $dubboUrl = $request->getChosenDubboUrl();
        $args = new DubboParam($request->getArgs());
        $encoder = new HessionEncoder();
        $variablePart = $encoder->encode($dubboUrl->getDubboVersion());
        $variablePart .= $encoder->encode($dubboUrl->getService());
        $variablePart .= $encoder->encode($dubboUrl->getVersion());
        $variablePart .= $encoder->encode($request->getMethod());
        $variablePart .= $encoder->encode($args->typeRefs());
        foreach ($args->getParams() as $arg) {
            if (is_object($arg) && ($arg instanceof DubboParam)) {
                $arg = $arg->object;
            }
            $variablePart .= $encoder->encode($arg);
        }
        $variablePart .= $encoder->encode([
            'path' => $dubboUrl->getService(),
            'interface' => $dubboUrl->getInterface(),
            'timeout' => $dubboUrl->getTimeout(),
            'group' => $dubboUrl->getGroup(),
            'version' => $dubboUrl->getVersion()
        ]);
        return $variablePart;
    }

    public function serializeResponse(DubboProtocol $protocol)
    {
        $variablePart = $protocol->getVariablePart();
        $variablePartType = $protocol->getVariablePartType();
        $encoder = new HessionEncoder();
        return $encoder->encode($variablePartType) . '' . $encoder->encode($variablePart);

    }

    public function unserializeRequest(DubboProtocol $protocol, $variablePart)
    {
        if ($variablePart === '') {
            throw new DubboException("The request 'variablePart' cannot be empty!");
        }
        $decoder = new HessionDecoder();
        $decoder->feed($variablePart);
        $_arr = $decoder->finalize() ?: [];
        $this->setAttachments(DubboResponse::toArray(array_pop($_arr), true));
        $this->setDubboVersion($_arr[0] ?? '');
        $this->setServiceName($_arr[1] ?? '');
        $this->setServiceVersion($_arr[2] ?? '');
        $method = $_arr[3] ?? '';
        $paramIndex = 5;
        if ($method == '$invoke') {
            $method = $_arr[5] ?? '';
            $paramIndex = 6;
        }
        $this->setMethod($method);
        $this->setParameterType($_arr[4] ?? '');
        $arguments = [];
        foreach ($_arr as $key => $value) {
            if ($key < $paramIndex) {
                continue;
            }
            $arguments[] = DubboResponse::toArray($value);
        }
        $this->setArguments($arguments);
        return $this;
    }

    public function unserializeResponse(DubboProtocol $protocol, $variablePart)
    {
        if ($variablePart === '') {
            throw new DubboException("The returned 'variablePart' cannot be empty!");
        }
        $decoder = new HessionDecoder();
        $decoder->feed($variablePart);
        $result = $decoder->finalize();
        if ($protocol->getLen() == 1) {
            return [$result, null];
        }
        return $result;
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