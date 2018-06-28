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
namespace com\fenqile\fsof\provider\monitor;

use com\fenqile\fsof\common\protocol\fsof\DubboRequest;

class AppMonitor
{
	const APP_START_TIME = 'app_start_time';
	const APP_RELOAD_TIME = 'app_reload_time';
	
	const CUR_CONNECT_NUM = 'cur_connect_num';
	const CUR_HANDLE_NUM = 'cur_handle_num';
	const CUR_WAIT_NUM = 'cur_wait_num';

	protected $appMonitorTable;
	
	protected $appName;
	
	protected $appConfig;
	
	protected $curEnv;
	
	protected $swooleServer;
	
	protected $serviceMonitor;
	
	public function __construct($appName, $appConfig)
	{
		$this->appName = $appName;
		$this->appConfig = $appConfig;
		$this->curEnv = $appConfig['fsof_setting']['environment'];
		
		$this->serviceMonitor = new ServiceMonitor($this->appName, $this->appConfig);
				
		$this->appMonitorTable = new \swoole_table(8);
		$this->appMonitorTable->column(self::APP_START_TIME, \swoole_table::TYPE_STRING, 32);
		$this->appMonitorTable->column(self::APP_RELOAD_TIME, \swoole_table::TYPE_STRING, 32);
		$this->appMonitorTable->column(self::CUR_HANDLE_NUM, \swoole_table::TYPE_INT, 8);
		$this->appMonitorTable->create();
		
		$this->reset();
	}
	
	public function setServer($swooleServer)
	{
		$this->serviceMonitor->setServer($swooleServer);
		$this->swooleServer = $swooleServer;
	}
	
	public function reset()
	{
		$this->appMonitorTable->set($this->appName, array(self::CUR_HANDLE_NUM => 0));
	}
		
	public function uploadMonitorData()
	{	
		$data = $this->appMonitorTable->get($this->appName);
        $stats = $this->swooleServer->stats();

		// 增加当前排队的任务数，更改当前连接的数目,今天连接的总数更新为服务启动以前连接的总数
        $msg = sprintf("%s|%s|%s|%d|%s|%s|%d|%d|%d",
        				date('Y-m-d H:i:s'),
        				$this->appName,
        				$this->curEnv,
        				$this->appConfig['server']['listen'][0],
        				$data[self::APP_START_TIME],
        				$data[self::APP_RELOAD_TIME],
        				$stats['connection_num'],
        				$data[self::CUR_HANDLE_NUM],
        				$stats['tasking_num']);
        \Logger::getLogger(__CLASS__)->info($msg);
        
        $this->reset();
        
        $this->serviceMonitor->uploadMonitorData();
	}
	
	public function onAppStart()
	{
		$startTime = date('Y-m-d H:i:s');
		$this->appMonitorTable->set($this->appName, array(self::APP_START_TIME => $startTime));
	}
	
	public function onAppReload()
	{
		$reloadTime = date('Y-m-d H:i:s');
		$this->appMonitorTable->set($this->appName, array(self::APP_RELOAD_TIME => $reloadTime));		
	}
		
	public function onRequest(DubboRequest $request)
	{
		$this->appMonitorTable->incr($this->appName,self::CUR_HANDLE_NUM);
		$this->serviceMonitor->onRequest($request);
	}
	
	public function onResponse(DubboRequest $request)
	{
		$this->appMonitorTable->decr($this->appName,self::CUR_HANDLE_NUM);
		$this->serviceMonitor->onResponse($request);
	}
	
	public function onError(DubboRequest $request)
	{
		$this->appMonitorTable->decr($this->appName,self::CUR_HANDLE_NUM);
		$this->serviceMonitor->onResponse($request);
		$this->serviceMonitor->onError($request);
	}
}