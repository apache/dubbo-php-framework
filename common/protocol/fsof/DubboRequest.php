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

class DubboRequest
{
    //包头字段
    private $twoWay = true;
    private $heartbeatEvent = false;
    private $sn;                    //请求序号
    private $serialization;
    private $fullData;                   //报文（包含消息头）
    private $dataLen;    //数据报文长度
    private $requestLen; //请求报文总长度(即消息头+消息体的长度)

    //包体字段
    private $dubboVersion = "2.0.0";       //dubbo 版本
    private $service;                      //服务名称
    private $version;                      //服务版本
    private $group;             //服务group
    private $method;                //方法名
    private $timeout;             //超时时间(ms)
    private $types;              //参数类型
    private $paramNum = 0;        //参数个数
    private $params = array();    //调用$method时的参数
    private $attach = array();

    /**
     * @return boolean
     */
    public function isTwoWay()
    {
        return $this->twoWay;
    }

    /**
     * @param boolean $twoWay
     */
    public function setTwoWay($twoWay)
    {
        $this->twoWay = $twoWay;
    }

    /**
     * @return mixed
     */
    public function getDataLen()
    {
        return $this->dataLen;
    }

    /**
     * @param mixed $dataLen
     */
    public function setDataLen($dataLen)
    {
        $this->dataLen = $dataLen;
    }

    /**
     * @return mixed
     */
    public function getRequestLen()
    {
        return $this->requestLen;
    }

    /**
     * @param mixed $requestLen
     */
    public function setRequestLen($requestLen)
    {
        $this->requestLen = $requestLen;
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
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param mixed $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }



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
     * @return string
     */
    public function getDubboVersion()
    {
        return $this->dubboVersion;
    }

    /**
     * @param string $dubboVersion
     */
    public function setDubboVersion($dubboVersion)
    {
        $this->dubboVersion = $dubboVersion;
    }

    /**
     * @return mixed
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param mixed $service
     */
    public function setService($service)
    {
        $this->service = $service;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param mixed $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param mixed $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param mixed $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return mixed
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param mixed $types
     */
    public function setTypes($types)
    {
        $this->types = $types;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function getAttach()
    {
        return $this->attach;
    }

    /**
     * @param array $attach
     */
    public function setAttach($attach)
    {
        $this->attach = $attach;
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
     * @return int
     */
    public function getParamNum()
    {
        return $this->paramNum;
    }

    /**
     * @param int $paramNum
     */
    public function setParamNum($paramNum)
    {
        $this->paramNum = $paramNum;
    }



    //用于监控service中各方法的性能
    public $startTime;            //开始处理请求的时间
    public $endTime;            //请求处理结束的时间

    public $host;                //记录acc日志中的目标IP
    public $port;                //记录acc日志中的目标端口

    public $reqInfo = null;            //swoole框架记录的过载信息

    public $environment = "prod";


    public function __toString()
    {
        $ret = sprintf("%s:%s:%s->%s", $this->service, $this->version, $this->group, $this->method);
        return $ret;
    }
}