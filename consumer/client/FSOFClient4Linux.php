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
namespace com\fenqile\fsof\consumer\client;

class FSOFClient4Linux extends IFSOFClient
{
    private  $logger;
    public function __construct()
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        if (extension_loaded('swoole'))
        {
            try 
            {
				$this->client = new \swoole_client(SWOOLE_SOCK_TCP);
                $this->client->set(array(
                    'open_length_check' => TRUE,
                    'package_length_offset' => 12,       //第N个字节是包长度的值
                    'package_body_offset' => 16,       //第几个字节开始计算长度
                    'package_length_type' => 'N',
                    'package_max_length' => 1024 * 1024 * 5, //TCP协议最大长度为2M,暂定5M
                ));
            } 
            catch (\Exception $e) 
            {
                $this->logger->error("not installed swoole extension", $e);
                throw new \Exception("该环境未安装swoole扩展");
            }
        }
        else
        {
            $this->logger->error("not installed swoole extension");
            throw new \Exception("该环境未安装swoole扩展");
        }
    }

    public function connect($ipAddr, $port, $ioTimeOut)
    {
        try 
        {
            return $this->client->connect($ipAddr, $port, $ioTimeOut);
        } 
        catch (\Exception $e) 
        {
            $this->logger->error("connect FSOF server[".$ipAddr.":".$port ."] failed", $e);
            return false;
        }
    }

    public function send($data, $len)
    {
        return $this->client->send($data);
    }

    public function recv($len = 65535)
    {
        return $this->client->recv($len, true);
    }

    public function close($force = false)
    {
        try 
        {
            $this->client->close($force);
        } 
        catch (\Exception $e) 
        {
            $this->logger->error("close exception", $e);
        }
    }

    public function getlasterror()
    {
        return $this->client->errCode;
    }
}