<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */
namespace com\fenqile\fsof\common\protocol\fsof;

class DubboResponse
{
    const OK = 20;
    const SERVICE_ERROR = 70;      //服务端框架层有异常

    private $fullData;				//返回报文（包含消息头）

    //包头字段
    private $sn;				//请求序号
    private $status = self::OK;
    private $heartbeatEvent = false;
    private $serialization;
    private $len;            //数据部分长度

    //包体字段
    private $responseBody;    //包体数据
    private $result;           //调用$method的结果
    private $errorMsg;

    /**
     * @return mixed
     */
    public function getFullData()
    {
        return $this->fullData;
    }

    /**
     * @param mixed $fullData
     */
    public function setFullData($fullData)
    {
        $this->fullData = $fullData;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return mixed
     */
    public function getLen()
    {
        return $this->len;
    }

    /**
     * @param mixed $len
     */
    public function setLen($len)
    {
        $this->len = $len;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return boolean
     */
    public function isHeartbeatEvent()
    {
        return $this->heartbeatEvent;
    }

    /**
     * @param boolean $heartbeatEvent
     */
    public function setHeartbeatEvent($heartbeatEvent)
    {
        $this->heartbeatEvent = $heartbeatEvent;
    }




    /**
     * @return mixed
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * @param mixed $errorMsg
     */
    public function setErrorMsg($errorMsg)
    {
        $this->errorMsg = $errorMsg;
    }

    /**
     * @return mixed
     */
    public function getSn()
    {
        return $this->sn;
    }

    /**
     * @param mixed $sn
     */
    public function setSn($sn)
    {
        $this->sn = $sn;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * @return mixed
     */
    public function getSerialization()
    {
        return $this->serialization;
    }

    /**
     * @param mixed $serialization
     */
    public function setSerialization($serialization)
    {
        $this->serialization = $serialization;
    }

    /**
     * @return mixed
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * @param mixed $responseBody
     */
    public function setResponseBody($responseBody)
    {
        $this->responseBody = $responseBody;
    }



	public function __toString()
	{
		$ret = sprintf("%s->%s",  $this->sn,$this->responseBody);
		return $ret;
	}		
}