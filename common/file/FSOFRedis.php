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
namespace com\fenqile\fsof\common\file;

use com\fenqile\fsof\common\config\FSOFConstants;


class FSOFRedis
{

    private static $_instance;
        
    private $m_redis = null;

    private $logger;

    private $connect_timeout = 1;

    private $read_timeout = 2;

    private $retry_count = 1;

    private $connect_type = FSOFConstants::FSOF_SERVICE_REDIS_CONNECT_TYPE_TCP;

    private $hosts = [
        [FSOFConstants::FSOF_SERVICE_REDIS_HOST, FSOFConstants::FSOF_SERVICE_REDIS_PORT],
    ];
        
    public static function instance($config = [])
    {
        if (extension_loaded('redis'))
        {
            if (!isset(FSOFRedis::$_instance))
            {
                FSOFRedis::$_instance = new FSOFRedis($config);
                FSOFRedis::$_instance->get_redis();
            }
            return FSOFRedis::$_instance;
        }
        else
        {
            \Logger::getLogger(__CLASS__)->error("not installed redis extension");
        }
        return NULL;
    }
    
    public function  __construct($config = [])
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        if(isset($config['redis_hosts']))
        {
            $this->hosts = [];
            $address = explode(',', $config['redis_hosts']);
            foreach ($address as $node){
                list($host, $port) = explode(':', $node);
                $this->hosts[] = [$host, $port??FSOFConstants::FSOF_SERVICE_REDIS_PORT];
            }
        }
        if(isset($config['redis_connect_timeout']))
        {
            $this->connect_timeout = $config['redis_connect_timeout'];
        }
        if(isset($config['redis_read_timeout']))
        {
            $this->read_timeout = $config['redis_read_timeout'];
        }
        if(isset($config['redis_connect_type']))
        {
            $this->connect_type = $config['redis_connect_type'];
        }
        if(isset($config['redis_retry_count']))
        {
            $this->retry = min($config['redis_retry_count'], 1);
        }
    }

    public function get_redis()
    {
        if (!isset($this->m_redis))
        {
            $hosts_count = count($this->hosts);
            $retry = $this->retry_count;
            $rand_num = rand() % $hosts_count;
            $ret = false;
            do{
                try{
                    $redis_cli = new \Redis();
                    if($this->connect_type == FSOFConstants::FSOF_SERVICE_REDIS_CONNECT_TYPE_TCP)
                    {
                        $node = $this->hosts[$rand_num];
                        $ret = $redis_cli->connect($node[0],$node[1],$this->connect_timeout);
                        $redis_cli->setOption(\Redis::OPT_READ_TIMEOUT, $this->read_timeout);
                        $rand_num = ($rand_num + 1)%$hosts_count;
                        if (!$ret)
                        {
                            $this->logger->warn("connect redis failed[{$node[0]}:{$node[1]}]");
                        }
                    }else{
                        $ret = $redis_cli->connect("/var/fsof/redis.sock",-1,FSOFConstants::FSOF_SERVICE_REDIS_PORT,$this->connect_timeout);
                    }
                    if($ret)
                    {
                        break;
                    }
                }catch (\Exception $e){
                    $this->logger->error('connect redis excepiton:'.$e->getMessage().', errno:' . $e->getCode());
                }
            }while($retry-- > 0);
            if($ret)
            {
                $this->m_redis = $redis_cli;
            }
            else
            {
                $this->logger->error('connect redis failed:|errno:' . $redis_cli->getLastError());
                throw new \Exception("连接redis异常");
            }
        }

        return $this->m_redis;
    }

    public function getProviderInfo($key)
    {
        return $this->getlist($key);
    }

    public function get($key)
    {
        if (!empty($key) && isset($this->m_redis))
        {
            return $this->m_redis->get($key);
        } 
        else 
        {
            return NULL;
        }
    }

	public function getlRange($key)
	{
		if (!empty($key) && isset($this->m_redis))
		{
			return $this->m_redis->lRange($key, 0, -1);
		}
		else
		{
            $this->logger->warn('not object of redis:'.$key);
			return NULL;
		}
	}

    public function getlist($key)
    {
        if (!empty($key) && isset($this->m_redis))
        {
            try{
                return $this->getlRange($key);
            }catch (\Exception $e){
                $this->logger->warn('redis current connect excepiton'.' |errcode:'.$e->getCode().' |errmsg:'.$e->getMessage());
                $this->close();
                //重试一次防止连接成功后，连接断开
                $this->get_redis();
                return $this->getlRange($key);
            }
        }
        else
        {
            return null;
        }
    }

    public function set($key, $value)
    {
        if (!empty($key) && isset($this->m_redis))
        {
            return $this->m_redis->set($key, $value);
        } 
        else 
        {
            return null;
        }
    }

    /**
     * Description: 关闭redis连接
     */
    public function close()
    {
        try
        {
            if (isset($this->m_redis)) 
            {
                $this->m_redis->close();
                unset($this->m_redis);
            }
        } 
        catch (\Exception $e)
        {
			unset($this->m_redis);
            $this->logger->error('close redis error:'.$e->getMessage(),$e);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
