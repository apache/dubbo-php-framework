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

class ServiceMonitor
{
	const SERVICE_NAME = 'service_name';
	const SERVICE_VERSION = 'service_version';
	const SERVICE_GROUP = 'service_group';
	const SERVICE_SET = 'service_set';
	const SERVICE_METHOD = 'service_method';
	
	const AVR_HANDLE_NUM = 'avr_handle_num';
	const AVR_ERROR_NUM = 'avr_error_num';
	const AVR_COST_TIME = 'avr_cost_time';
	const FASTEST_COST_TIME = 'fastest_cost_time';
	const SLOWEST_COST_TIME = 'slowest_cost_time';

	//swoole_table表项的长度
	const SWOOLE_TABLE_STRING = 256;
	const SWOOLE_TABLE_VERSION = 32;
	const SWOOLE_TABLE_INT = 16;

	protected $ServiceMonitorTable;
	
	protected $appName;
	
	protected $appConfig;
	
	protected $curEnv = 'prod';
	
	protected $swooleServer;
	
	public function __construct($appName, $appConfig)
	{
		$this->appName = $appName;
		$this->appConfig = $appConfig;
		if(isset($appConfig['fsof_setting']['environment']))
		{
			$this->curEnv = $appConfig['fsof_setting']['environment'];
		}

		$this->ServiceMonitorTable = new \swoole_table(1024);
		$this->ServiceMonitorTable->column(self::SERVICE_NAME, \swoole_table::TYPE_STRING, self::SWOOLE_TABLE_STRING);
		$this->ServiceMonitorTable->column(self::SERVICE_VERSION, \swoole_table::TYPE_STRING, self::SWOOLE_TABLE_VERSION);
		$this->ServiceMonitorTable->column(self::SERVICE_GROUP, \swoole_table::TYPE_STRING, self::SWOOLE_TABLE_STRING);
		$this->ServiceMonitorTable->column(self::SERVICE_SET, \swoole_table::TYPE_STRING, self::SWOOLE_TABLE_STRING);
		$this->ServiceMonitorTable->column(self::SERVICE_METHOD, \swoole_table::TYPE_STRING, self::SWOOLE_TABLE_STRING);
		$this->ServiceMonitorTable->column(self::AVR_HANDLE_NUM, \swoole_table::TYPE_INT, self::SWOOLE_TABLE_INT);
		$this->ServiceMonitorTable->column(self::AVR_ERROR_NUM, \swoole_table::TYPE_INT, self::SWOOLE_TABLE_INT);
		$this->ServiceMonitorTable->column(self::AVR_COST_TIME, \swoole_table::TYPE_INT, self::SWOOLE_TABLE_INT);
		$this->ServiceMonitorTable->column(self::FASTEST_COST_TIME, \swoole_table::TYPE_INT, self::SWOOLE_TABLE_INT);
		$this->ServiceMonitorTable->column(self::SLOWEST_COST_TIME, \swoole_table::TYPE_INT, self::SWOOLE_TABLE_INT);
		$this->ServiceMonitorTable->create();
		
		$this->reset();
	}
	
	public function setServer($swooleServer)
	{
		$this->swooleServer = $swooleServer;
	}
	
	public function reset()
	{
		foreach($this->ServiceMonitorTable as $row)
		{
	    	$key = $this->generateRowKey($row);
	    	$this->ServiceMonitorTable->set($key,array(self::AVR_HANDLE_NUM=>0,self::AVR_ERROR_NUM=>0,self::AVR_COST_TIME=>0,self::FASTEST_COST_TIME=>0,self::SLOWEST_COST_TIME=>0));
		}
	}
	
	public function uploadMonitorData()
	{
		foreach($this->ServiceMonitorTable as $row)
		{
		    $avrCost = $row[self::AVR_COST_TIME];
			$avrHandleNum = $row[self::AVR_HANDLE_NUM];
			if($avrHandleNum > 0)
			{
			    $avrCost = $avrCost/$avrHandleNum;
			} 
			else 
			{
			    $avrCost = 0;
			}
			
	    	$msg = sprintf("%s|%s|%s|%s|%s|%s|%d|%s|%d|%d|%d|%d|%d|%s",
				date('Y-m-d H:i:s'),
                $this->appName,
                $row[self::SERVICE_NAME],
                $row[self::SERVICE_VERSION],
                $row[self::SERVICE_GROUP],
                $this->curEnv,
                $this->appConfig['server']['listen'][0],
                $row[self::SERVICE_METHOD],
                $row[self::AVR_HANDLE_NUM],
                $row[self::AVR_ERROR_NUM],
                $avrCost,
                $row[self::SLOWEST_COST_TIME],
                $row[self::FASTEST_COST_TIME],
				$row[self::SERVICE_SET]);
            \Logger::getLogger(__CLASS__)->info($msg);
        }

		$this->reset();
	}

	public function onRequest(DubboRequest $request)
	{
		$serviceLen = strlen($request->getService());
		$versionLen = strlen($request->getVersion());
		$groupLen = strlen($request->getGroup());
		$methodLen = strlen($request->getMethod());
		if($serviceLen>self::SWOOLE_TABLE_STRING || $versionLen>self::SWOOLE_TABLE_VERSION || $groupLen>self::SWOOLE_TABLE_STRING
            || $methodLen>self::SWOOLE_TABLE_STRING )
		{
            \Logger::getLogger(__CLASS__)->error("Set swoole_table failed, More than the length of the table:".$request->getService().
                " len:".$serviceLen."|".$request->getVersion()." len:".$versionLen."|".$request->getGroup()." len:".$groupLen."|".$request->getMethod().
                " len:".$methodLen);
			return ;
		}

		$key = $this->generateRequestKey($request);
		if(!$this->ServiceMonitorTable->exist($key))
		{
			$this->ServiceMonitorTable->set($key, array(self::SERVICE_NAME => $request->getService(),
														self::SERVICE_VERSION => $request->getVersion(),
														self::SERVICE_GROUP => $request->getGroup(),
														self::SERVICE_METHOD => $request->getMethod()));
		}
	}

	public function onResponse(DubboRequest $request)
	{
		$key = $this->generateRequestKey($request);
		$data = $this->ServiceMonitorTable->get($key);
		if($data)
		{
			//当前这次请求耗时
			$thisCost = (int)(($request->endTime - $request->startTime)*1000000);
			
			//最快耗时
			if (0 == $data[self::FASTEST_COST_TIME])
			{
				$data[self::FASTEST_COST_TIME] = $thisCost;
			}
			else if($data[self::FASTEST_COST_TIME] > $thisCost)
			{
				$data[self::FASTEST_COST_TIME] = $thisCost;
			}
			
			//最慢耗时
			if($data[self::SLOWEST_COST_TIME] < $thisCost)
			{
				$data[self::SLOWEST_COST_TIME] = $thisCost;
			}
			
			//平均耗时  = $data[self::AVR_COST_TIME]/$data[self::AVR_HANDLE_NUM]
			$avrCost = $data[self::AVR_COST_TIME];
			$data[self::AVR_COST_TIME] = $avrCost + $thisCost;
			$this->ServiceMonitorTable->set($key, array(self::AVR_COST_TIME => $data[self::AVR_COST_TIME],
														self::FASTEST_COST_TIME => $data[self::FASTEST_COST_TIME],
														self::SLOWEST_COST_TIME => $data[self::SLOWEST_COST_TIME]));	
													
			$this->ServiceMonitorTable->incr($key,self::AVR_HANDLE_NUM);
		}
	}
	
	public function onError(DubboRequest $request)
	{
		$key = $this->generateRequestKey($request);
		if($this->ServiceMonitorTable->exist($key))
		{
			$this->ServiceMonitorTable->incr($key,self::AVR_ERROR_NUM);		
		}
	}
	
	private function generateRowKey($row)
	{
		$key = $row[self::SERVICE_NAME].':'.$row[self::SERVICE_VERSION].':'.$row[self::SERVICE_GROUP].':'.$row[self::SERVICE_METHOD];
		return $key;
	}
	
	private function generateRequestKey($req)
	{
		$key = $req->getService().':'.$req->getVersion().':'.$req->getGroup().':'.$req->getMethod();
		return $key;
	}
}