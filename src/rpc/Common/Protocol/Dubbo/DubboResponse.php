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

use Dubbo\Common\DubboException;
use dubbo\common\serialization\Support\Hessian2;
use dubbo\common\serialization\Support\FastJson;
use Icecave\Collections\Map;
use Icecave\Collections\Vector;

class DubboResponse
{

    const RESPONSE_WITH_EXCEPTION = 0;
    const RESPONSE_VALUE = 1;
    const RESPONSE_NULL_VALUE = 2;

    const STATUS_OK = 20;
    const SERVICE_ERROR = 70;

    private $_protocol;
    private $_responseData;

    public function __construct(DubboProtocol $protocol, $responseData)
    {
        $this->_protocol = $protocol;
        $this->_responseData = $responseData;
    }

    public function getContents()
    {
        $status = $this->_protocol->getStatus();
        if ($status != self::STATUS_OK) {
            throw new DubboException("Dubbo returns exception. Exception:{$this->_responseData}", $status);
        }
        list($status, $content) = $this->_responseData;
        switch ($status) {
            case self::RESPONSE_WITH_EXCEPTION:
                throw new DubboException("Dubbo returns variable part status error. status:" . (string)$status . ',content:' . json_encode($content));
                break;
        }
        return $this->toArray($content);
    }

    public static function toArray($content)
    {
        if (!is_object($content) && !is_array($content)) {
            return $content;
        }
        $property = [];
        if (is_object($content)) {
            if ($content instanceof Map) {
                foreach ($content->elements() as $value) {
                    $property[$value[0]] = $value[1];
                }
            } elseif ($content instanceof Vector) {
                $property = $content->elements();
            } else {
                $property = get_object_vars($content);
            }
        } else {
            $property = $content;
        }
        foreach ($property as &$item) {
            if (is_object($item) || is_array($item)) {
                $item = self::toArray($item);
            }
        }
        return $property;
    }


}