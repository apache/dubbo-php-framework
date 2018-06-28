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
namespace com\fenqile\fsof\consumer\proxy;

use com\fenqile\fsof\common\protocol\fsof\DubboRequest;
use com\fenqile\fsof\consumer\fsof\FSOFProcessor;


final class Proxy
{
    private $appName;

	private $group;

    private $serviceInterface;

    private $serviceAddress = array();

    private $fsofProcessor;

	//默认超时时间为3s
    private $ioTimeOut = 3;

    private  $logger;

    public static function newProxyInstance($serviceInterfaces,  $appName, $group)
    {
        return new Proxy($serviceInterfaces, $appName, $group);
    }

    public function __construct($serviceInterfaces, $appName, $group)
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        $this->serviceInterface = $serviceInterfaces;
        $this->appName = $appName;
		$this->group = $group;
        $this->fsofProcessor = new FSOFProcessor();
    }

    public function setIOTimeOut($ioTimeOut)
    {
        $this->ioTimeOut = $ioTimeOut;
    }

    public function setAddress($serviceAddr = array())
    {
		//删除旧的地址
        unset($this->serviceAddress);
        $this->serviceAddress = $serviceAddr;
    }

	public function getAddressStr()
    {
        $ret = "";
        foreach ($this->serviceAddress as $index => $url)
        {
            $ret .= $url->getHost().':'.$url->getPort().';';
        }
        return $ret;
    }
    
    protected function generatePackageSN()
    {
        srand((double)microtime() * 1000000);
        $rand_number = rand();
        return $rand_number;
    }

    protected function generateParamType($num)
    {
        $types = array();
        for($i = 0; $i < $num; $i++)
        {
            $types[] = 'Ljava/lang/Object;';
        }
        return $types;
    }

    protected function trimParams($params)
    {
        if(count($params) == 1 && empty($params[0]))
        {
            return null;
        }
        return $params;
    }

    public function __call($name, $args)
    {
        $result = NULL;
		$method = null;
        $providerAddress = NULL;
        $request = new DubboRequest();
		//取到微秒
        $begin_time = microtime(true);
        $this->logger->debug("in|consumer_app:{$this->appName}|service:{$this->serviceInterface}|timout:{$this->ioTimeOut}|name:{$name}");
        try {
            $request->setSn($this->generatePackageSN());
            $request->setService($this->serviceInterface);
            $request->setMethod($args[0]);
            array_shift($args);
            $request->setParams($args);
            $request->setTypes($this->generateParamType(count($request->getParams())));
            $result = $this->fsofProcessor->executeRequest($request, $this->serviceAddress, $this->ioTimeOut, $providerAddress);
        }catch (\Exception $e) {
            $cost_time = (int)((microtime(true) - $begin_time) * 1000000);
            //记录consumer接口告警日志
            $this->setAccLog($request, $cost_time, $e->getMessage());
            throw $e;
        }
        $cost_time = (int)((microtime(true) - $begin_time) * 1000000);
        //记录consumer接口告警日志
        $this->setAccLog($request, $cost_time, "ok");
		return $result;
    }

    protected function setAccLog($request, $costTime, $errMsg='ok')
    {
		//时间|服务名|耗时（us）|返回码|应用名|方法名|目标服务group|目标服务version|目标机器ip:port|备注
		$accLog = sprintf("%s|%d|%d|%s|%s|%s|%s|%s", $request->getService(), $costTime,
			$this->appName,
			$request->getMethod(),
			$request->getGroup(),
			$request->getVersion(),
			$request->host . ':' . $request->port,
			$errMsg);
        $this->logger->debug($accLog);
    }
}