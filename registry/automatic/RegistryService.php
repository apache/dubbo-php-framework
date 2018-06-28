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
namespace com\fenqile\fsof\registry\automatic;

class RegistryService
{
    private $zookeeperClient = null;
	private $zookeeperAddr = null;
    private $logger;

    public function __construct($registryUrl)
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        $zkHostStr = '';
        foreach($registryUrl as $fsofUrl) 
        {
            $zkHostStr .= $fsofUrl->getHost().':'.$fsofUrl->getPort().',';
        }
        $this->zookeeperAddr = rtrim($zkHostStr,',');//去掉最后的,
        
        try
        {
			$this->zookeeperClient = new ZookeeperClient();
        }
        catch (\Exception $e) 
        {
            throw new \Exception("连接zookeeper失败".$e->getMessage());
        }
    }

	public function __destruct()
	{
		unset($this->zookeeperClient);
	}

	public function connectZk($ephemeral)
	{
		return $this->zookeeperClient->connectZk($this->zookeeperAddr, $ephemeral);
	}

    public function register($url)
    {
        $ret = false;

        $this->logger->info('registryService::register|url:'.$url->getOriginUrl().'|path:'.$url->getZookeeperPath());
        try
        {
            $ret = $this->zookeeperClient->create($url->getZookeeperPath());
        }
        catch (\Exception $e) 
        {
            $ret = false;
            throw new \Exception("注册service到zookeeper失败".$e->getMessage());
        }

        return $ret;
    }

    public function unregister($url)
    {
        $path = $url->getZookeeperPath();
        return  $this->zookeeperClient->delete($path);
    }

    public function subscribe($url, $listener)
    {
    }


    public function unsubscribe($url, $listener)
    {
    }


    public function lookup($url)
    {
    }

	public function registerCallFunc($func)
	{
		$this->zookeeperClient->registerCallFunc($func);
	}

	public function setLogFile($file, $level = 2)
	{
		$this->zookeeperClient->setLogFile($file, $level);
	}
}