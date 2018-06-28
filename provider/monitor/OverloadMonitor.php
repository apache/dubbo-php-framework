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

/**
 * @property \swoole_table appMonitorTable
 */
class OverloadMonitor
{
	const CUR_OVERLOAD_NUM = 'cur_packet_overload_num';
	const CUR_MUST_LOSS_NUM = 'cur_must_loss_packet_num';

	protected $overloadMonitorTable;
	protected $appName;
	
	public function __construct($appName)
	{
		$this->appName = $appName;
		$this->overloadMonitorTable = new \swoole_table(2048);
		$this->overloadMonitorTable->column(self::CUR_OVERLOAD_NUM, \swoole_table::TYPE_INT, 8);
		$this->overloadMonitorTable->column(self::CUR_MUST_LOSS_NUM, \swoole_table::TYPE_INT, 8);
		$this->overloadMonitorTable->create();
		$this->clear();
	}

	public function __destruct()
	{
		if(!empty($this->overloadMonitorTable))
		{
			$this->overloadMonitorTable->del($this->appName);
			unset($this->overloadMonitorTable);
		}
	}

	public function clear()
	{
		$this->overloadMonitorTable->set($this->appName, array(self::CUR_OVERLOAD_NUM => 0, self::CUR_MUST_LOSS_NUM => 0));
	}

	public function resetOverloadNum_setLossNum($lossNum)
	{
		$this->overloadMonitorTable->set($this->appName, array(self::CUR_OVERLOAD_NUM => 0, self::CUR_MUST_LOSS_NUM => $lossNum));
	}

	public function getLossNum()
	{
		$ret = 0;
		$data = $this->overloadMonitorTable->get($this->appName);
		if($data)
		{
			$ret = $data[self::CUR_MUST_LOSS_NUM];
		}
		return $ret;
	}

	public function getoverloadNum()
	{
		$ret = 0;
		$data = $this->overloadMonitorTable->get($this->appName);
		if($data)
		{
			$ret = $data[self::CUR_OVERLOAD_NUM];
		}
		return $ret;
	}

	public function overloadIncr()
	{
		$this->overloadMonitorTable->incr($this->appName, self::CUR_OVERLOAD_NUM);
	}
	
	public function lossNumDecr()
	{
		$this->overloadMonitorTable->decr($this->appName, self::CUR_MUST_LOSS_NUM);
	}
}