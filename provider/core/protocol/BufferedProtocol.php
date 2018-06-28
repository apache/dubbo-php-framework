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


abstract class BufferedProtocol extends BaseProtocol
{
    protected $requests;

   	const STATUS_FINISH = 1; //完成，进入处理流程
    const STATUS_WAIT   = 2; //等待数据
    const STATUS_ERROR  = 3; //错误，丢弃此包
    
    public function onReceive($server, $clientId, $fromId, $data, $reqInfo = null)
    {
    	// 检查buffer
        $ret = $this->checkBuffer($clientId, $data);
        \Logger::getLogger(__CLASS__)->debug("ret = ${ret}");

        switch($ret)
        {
            case self::STATUS_ERROR:
            	unset($this->requests[$clientId]);
                return true;           // 错误的请求
            case self::STATUS_WAIT:
                return true;          //数据不完整，继续等待
            default:
                break;                 // 完整数据
        }

        $request = $this->requests[$clientId];
		if (!empty($reqInfo))
		{
			$request->reqInfo = $reqInfo;
		}
        $this->server->setRequest($request);
        $this->onOneRequest($clientId, $request);
        unset($this->requests[$clientId]);
    }

    abstract public function checkBuffer($client_id, $data);

    abstract public function onOneRequest($client_id, $request);
    
    public function onClose($server, $fd, $fromId)
    {
        unset($this->requests[$fd]);
    }
}