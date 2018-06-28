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


class ZookeeperClient
{
	/**
	 * @var Zookeeper
	 */
	private $zookeeper = null;
	private $address;
	private $func = null;
	private $ephemeral = false;
	private $zkFile = null;
    private $waiteForConnectStateTimes = 20;
    private $logger;

    public function __construct()
    {
        $this->logger = \Logger::getLogger(__CLASS__);
    }

	public function __destruct()
	{
		$this->closeLog();
		unset($this->zookeeper);
        $this->logger->info('zookeeperClient destruct');
	}

	public function connect_cb($type, $event, $string)
	{
		// Watcher gets consumed so we need to set a new one
        $this->logger->debug("connect state:{$event}");
		$params = array($type, $event, $string);
		if(isset($this->func))
		{
            $this->logger->warn("call connect state");
			call_user_func_array($this->func, $params);
		}
        $this->logger->debug("connect_cb end");
	}

	public function connectZk($address, $ephemeral = false)
	{
		$ret = true;
		$this->address = $address;
		$this->ephemeral = $ephemeral;
		try
		{
			if ($ephemeral)
			{
				$this->zookeeper = new \Zookeeper($this->address, array($this,'connect_cb'));
			}
			else
			{
				$this->zookeeper = new \Zookeeper($this->address);
				if($this->zookeeper)
				{
					while(\Zookeeper::CONNECTED_STATE != $this->zookeeper->getState()
							&&($this->waiteForConnectStateTimes > 0))
					{
						//等待连接状态
						$this->waiteForConnectStateTimes--;
                        $this->logger->debug("wait for connect state");
						usleep(50000);
					}
				}
			}

			if(empty($this->zookeeper))
			{
				$ret = false;
			}
		}
		catch (\Exception $e)
		{
			$ret = false;
            $this->logger->error($e->getMessage().'|address:'.$this->address);
		}
		return $ret;
	}

	public function registerCallFunc($func)
	{
		$this->func = $func;
	}

	/**
	 * 创建节点。
	 *
	 * @param path 节点的绝对路径，如/fsof/URL.Encode("com.fenqile.example.calculate.AddService")/providers/URL.Encoder(provider url)。
	 * @param ephemeral,是否是临时节点，所有树干节点都为固定节点，只有叶子节点可以设为临时节点，没有设置，默认为固定节点
	 */
	public function create($path)
	{
		$ret = $this->set($path);
        $this->logger->info('zookeeperClient create|path:'.$path.'|ret:'.$ret);
		return $ret;
	}

	/**
	 * 删除节点，临时节点不需要删除，zookeeper在与对应的 client的连接断开后自动删除
	 * @param path 节点的绝对路径，如/fsof/URL.Encode("com.fenqile.example.calculate.AddService")/providers/URL.Encoder(provider url)。
	 */
	public function delete($path, $version=-1)
	{
		if(isset($this->zookeeper))
		{
			if($this->zookeeper->exists($path))
			{
				$this->zookeeper->delete($path, $version);
			}
		}
		return true;
	}
	
	private function set($path, $value=null) 
	{
		if(isset($this->zookeeper))
		{
			if($this->zookeeper->exists($path))
			{
				$this->zookeeper->set($path, $value);
			}
			else
			{
				$this->makePath($path);
				$this->makeNode($path, $value, $this->ephemeral);
			}
		}
		return true;
	}

	/**
	 * 获取指定节点的所有childe节点
	 *
	 * @param path 节点的绝对路径，如/fsof/URL.Encode("com.fenqile.example.calculate.AddService")/providers。
	 */
	public function getChildren($path)
	{
		if((strlen($path) > 1) && preg_match('@/$@', $path))
		{
			// remove trailing /
			$path = substr($path, 0, -1);
		}
		return $this->zookeeper->getChildren($path);
	}

	/**
	 * 为指定节点设置一个 childListener,用于监听其child变动情况
	 *
	 * @param path 节点的绝对路径，如/fsof/URL.Encode("com.fenqile.example.calculate.AddService")/providers。
	 * @param ChildListener
	 */
	public function addChildListener($path, $childListener)
	{
	}

	/**
	 * 删除指定节点上的 childListener
	 *
	 * @param path 节点的绝对路径，如/fsof/URL.Encode("com.fenqile.example.calculate.AddService")/providers。
	 * @param ChildListener
	 */
	public function  removeChildListener($path, $childeListener)
	{
	}

	/**
	 * 设置一个client的state状态监听器，如在连接建立或重建时，主动向zookeeper server拉一次数据
	 *
	 * @param StateListener
	 */
	public function addStateListener($stateListener)
	{
	}

	/**
	 * 删除一个client的state状态监听器
	 *
	 * @param StateListener
	 */
	public function removeStateListener($stateListener)
	{
	}

	public function isConnected()
	{
		$ret = $this->zookeeper->getState();
		if(\Zookeeper::CONNECTED_STATE == $ret)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function close()
	{
	}

	public function getUrl()
	{
	}

	/**
	 * Equivalent of "mkdir -p" on ZooKeeper
	 *
	 * @param string $path The path to the node
	 * @param string $value The value to assign to each new node along the path
	 *
	 * @return bool
	 */
	private function makePath($path, $value = '') 
	{
		$parts = explode('/', $path);
		$parts = array_filter($parts);
		$subpath = '';
		while (count($parts) > 1) 
		{
			$subpath .= '/' . array_shift($parts);
			if (!$this->zookeeper->exists($subpath)) 
			{
				$this->makeNode($subpath, $value);
			}
		}
	}

	/**
	 * Create a node on ZooKeeper at the given path
	 *
	 * @param string $path   The path to the node
	 * @param string $value  The value to assign to the new node
	 * @param bool $endNode  The value to assign to the end node
	 * @param array  $params Optional parameters for the Zookeeper node.
	 *                       By default, a public node is created
	 *
	 * @return string the path to the newly created node or null on failure
	 */
	private function makeNode($path, $value, $ephemeral = false, array $params = array()) 
	{
        //$this->logger->debug("makeNode(".$path.",".$value.",".($ephemeral?1:0).",".json_encode($params).")");
		if(empty($params))
		{
			$params = array(
				array(
					'perms'  => \Zookeeper::PERM_ALL,
					'scheme' => 'world',
					'id'     => 'anyone',
				)
			);
		}
		
		if ($ephemeral)
		{
			return $this->zookeeper->create($path, $value, $params , \Zookeeper::EPHEMERAL);
		}
		else 
		{
			return $this->zookeeper->create($path, $value, $params);
		}
	}

	public function setLogFile($file, $logLevel = 2)
	{
		$this->zkFile = fopen($file,"a+");
		if($this->zkFile && $this->zookeeper)
		{
			$this->zookeeper->setDebugLevel($logLevel);
			$this->zookeeper->setLogStream($this->zkFile);
		}
	}

	private function closeLog()
	{
		if(!empty($this->zkFile))
		{
			fclose($this->zkFile);
		}
	}
}