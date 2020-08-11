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

use Dubbo\Common\Logger\LoggerFacade;
use Dubbo\Common\DubboException;
use Dubbo\Common\Serialization\Support\Hessian2;
use Dubbo\Common\Serialization\Support\FastJson;

class DubboProtocol
{

    const PROTOCOL_HEAD_LEN = 0x10;
    const PROTOCOL_MAGIC = 0xdabb;
    const PROTOCOL_SERIALIZATION_MASK = 0x1f;
    const PROTOCOL_FLAG_REQ = 0x80;
    const PROTOCOL_FLAG_TWOWAY = 0x40;
    const PROTOCOL_FLAG_HEARTBEAT_EVENT = 0x20;

    private $_request;

    private $_serializationId = Hessian2::HESSIAN2_SERIALIZATION_ID; //default

    private $_len;

    private $_requestId;

    private $_status;

    private $_variablePart;

    private $_variablePartType;

    private $_heartBeatEvent;

    public function unPackHeader($header)
    {
        $format = 'n1magic/C1flag/C1status/J1requestId/N1len';
        $_arr = unpack($format, $header);
        LoggerFacade::getLogger()->debug('Dubbo header', $_arr);
        $this->setSerializationId($_arr['flag'] & self::PROTOCOL_SERIALIZATION_MASK);
        $this->setHeartBeatEvent($_arr['flag'] & self::PROTOCOL_FLAG_HEARTBEAT_EVENT);
        $this->setStatus($_arr['status']);
        $this->setRequestId($_arr['requestId']);
        $this->setLen($_arr['len']);
    }

    public function packHeader()
    {
        $variablePart = $this->getVariablePart();
        $serialization_id = $this->getSerializationId();
        if ($this->_request) {
            $flag = self::PROTOCOL_FLAG_REQ | self::PROTOCOL_FLAG_TWOWAY | $serialization_id;
            $status = '';
            $format = "n1C1a1J1N1";
        } else {
            $flag = $serialization_id | $this->getHeartBeatEvent();
            $status = (int)$this->getStatus();
            $format = "n1C1C1J1N1";
        }
        return pack($format, self::PROTOCOL_MAGIC, $flag, $status, $this->getRequestId(), strlen($variablePart));
    }

    public function packVariablePart()
    {
        $serialization_class = Hessian2::class;
        if ($this->getSerializationId() == FastJson::FASTJSON_SERIALIZATION_ID) {
            $serialization_class = FastJson::class;
        }
        $serialization_ins = new $serialization_class();
        if ($this->_request) {
            $variablePart = $serialization_ins->serializeRequest($this->_request);
        } else {
            $variablePart = $serialization_ins->serializeResponse($this);
        }
        $this->setVariablePart($variablePart);
        return $variablePart;
    }

    public function unpackVariablePart($variablePart)
    {
        $serialization_class = Hessian2::class;
        if ($this->getSerializationId() == FastJson::FASTJSON_SERIALIZATION_ID) {
            $serialization_class = FastJson::class;
        }
        $serialization_ins = new $serialization_class();
        return $serialization_ins->unserializeRequest($this, $variablePart);
    }

    public function packRequest(DubboRequest $request)
    {
        $this->_request = $request;
        $this->setRequestId($request->getRequestId());
        $serializationId = $request->getChosenDubboUrl()->getSerializationId();
        if (!$serializationId) {
            $serialization = $request->getChosenDubboUrl()->getSerialization();
            throw new DubboException("'{$serialization}' serialization is not supported");
        }
        $this->setSerializationId($serializationId);
        $variablePart = $this->packVariablePart();
        $header = $this->packHeader();
        $data = $header . $variablePart;
        LoggerFacade::getLogger()->debug("Dubbo packRequest", $data);
        return $data;

    }

    public function unpackRequest($data)
    {
        $this->unPackHeader(substr($data, 0, self::PROTOCOL_HEAD_LEN));
        return $this->unpackVariablePart(substr($data, self::PROTOCOL_HEAD_LEN));
    }

    public function unpackResponse($fullData)
    {
        LoggerFacade::getLogger()->debug('Dubbo Response Data.', $fullData);
        $serializationId = $this->getSerializationId();
        if ($serializationId == Hessian2::HESSIAN2_SERIALIZATION_ID) {
            $serializationClass = Hessian2::class;
        } elseif ($serializationId == FastJson::FASTJSON_SERIALIZATION_ID) {
            $serializationClass = FastJson::class;
        } else {
            throw new DubboException("'{$serializationId}' serializationId is not supported");
        }
        $variablePart = substr($fullData, self::PROTOCOL_HEAD_LEN);
        $decoder = new $serializationClass;
        return $decoder->unserializeResponse($this, $variablePart);
    }

    public function packResponse($variablePart)
    {
        $this->setVariablePart($variablePart);
        $variablePart = $this->packVariablePart();
        $header = $this->packHeader();
        return $header . $variablePart;
    }

    public function getVariablePart()
    {
        return $this->_variablePart;
    }

    public function setVariablePart($variablePart)
    {
        $this->_variablePart = $variablePart;
    }

    public function getVariablePartType()
    {
        return $this->_variablePartType;
    }

    public function setVariablePartType($type)
    {
        $this->_variablePartType = $type;
    }

    public function getLen()
    {
        return $this->_len;
    }

    public function setLen($len)
    {
        $this->_len = $len;
    }

    public function getRequestId()
    {
        return $this->_requestId;
    }

    public function setRequestId($requestId)
    {
        $this->_requestId = $requestId;
    }

    public function getStatus()
    {
        return $this->_status;
    }

    public function setStatus($status)
    {
        $this->_status = $status;
    }

    public function getSerializationId()
    {
        return $this->_serializationId;
    }

    public function setSerializationId($serializationId)
    {
        $this->_serializationId = $serializationId;
    }

    public function getHeartBeatEvent()
    {
        return $this->_heartBeatEvent;
    }

    public function setHeartBeatEvent($event)
    {
        $this->_heartBeatEvent = $event;
    }

}