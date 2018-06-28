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
namespace com\fenqile\fsof\provider\core\protocol;

use com\fenqile\fsof\common\config\FSOFConstants;

abstract class BaseProtocol implements IProtocol
{
	protected  $server;
	protected  $swoole_server;
	
	function __construct()
	{
        $this->init();
	}

    abstract public function init();
        
    public function setServer($server) 
    {
        $this->server = $server;
    }
    
    public function getAppConfig()
    {
    	return $this->server->getAppConfig();
    }

	public function getAppName()
	{
		return $this->server->getAppName();
	}

    public function getAppRunTimeEnv()
    {
    	return $this->server->getAppRunTimeEnv();
    }
    
    public function onStart($server, $workerId)
    {
    	$this->swoole_server = $server;
    	
    	//监控app重加载时间
        $this->server->getAppMonitor()->onAppReload();
        
        //当worker_id为0时添加定时器,驱动app监控信息上报
//		if($workerId == 0)
//		{
//			$this->swoole_server->addtimer(FSOFConstants::FSOF_MONITOR_TIMER);	//5分钟监控一次数据5*60*1000
//		}

        \Logger::getLogger(__CLASS__)->debug("protocol onStart():{$workerId}");
    }
    
    public function onConnect($server, $fd, $fromId)
    {

    }
    
    public function onReceive($server,$clientId, $fromId, $data, $reqInfo = null)
    {
    	
    }

    public function onClose($server, $fd, $fromId)
    {

    }
    
    public function onShutdown($serv, $workerId)
    {
    	
    }

 	public function onTask($serv, $taskId, $fromId, $data)
 	{
 		
 	}
 	
    public function onFinish($serv, $taskId, $data)
    {
    	
    }
    
    public function onTimer($serv, $interval)
    {
        switch( $interval ) 
        {
            case FSOFConstants::FSOF_MONITOR_TIMER:
            {
            	$this->server->getAppMonitor()->uploadMonitorData();
                break;
            }
        }
    }
    
    public function onRequest($request, $response)
    {
    	
    }
}