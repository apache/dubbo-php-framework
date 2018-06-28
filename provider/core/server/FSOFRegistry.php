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

namespace com\fenqile\fsof\provider\core\server;

use com\fenqile\fsof\common\url\FSOFUrl;
use com\fenqile\fsof\common\file\FSOFRedis;
use com\fenqile\fsof\common\log\FSOFSystemUtil;
use com\fenqile\fsof\common\config\FSOFConstants;
use com\fenqile\fsof\registry\automatic\RegistryServiceFactory;

class FSOFRegistry 
{
	//设置zookeeper的日志文件及日志级别（1.error; 2.warn; 3.info; 4.debug)
	const ZOOKEEPER_LOG_NO = 0;
	const ZOOKEEPER_LOG_ERROR = 1;
	const ZOOKEEPER_LOG_WARN = 2;
	const ZOOKEEPER_LOG_INFO = 3;
	const ZOOKEEPER_LOG_DEBUG = 4;

	protected $appName;
	protected $port;
	protected $config;
	protected $serverProviders;
	protected $localIp = '127.0.0.1';
	protected $ephemeral = false;

	//zookeeper相关
	protected $zkService = null;
	protected $fsofUrlList = array();

	protected $start_without_registry;

	private static $_instance;

    private $logger;

	public static function instance()
	{
		if (empty(FSOFRegistry::$_instance))
		{
			FSOFRegistry::$_instance = new FSOFRegistry();
		}
		return FSOFRegistry::$_instance;
	}
	
	public function __construct()
	{
        $this->logger = \Logger::getLogger(__CLASS__);
	}

	public function setParams($appName, $appConfig, $port, $serverProviders, $registry = true)
	{
        $this->logger->info("serverProviders:".json_encode($serverProviders));
		$this->appName = $appName;
		$this->port = $port;
		$this->config = $appConfig;
		$this->serverProviders = $serverProviders;
		$this->start_without_registry = $registry;

		try
		{
			$this->localIp = FSOFSystemUtil::getServiceIP();
		}
		catch (\Exception $e)
		{
            $this->logger->error('The server network configuration errors',$e);
			//当获取IP失败时，禁止往zk注册
			$this->start_without_registry = true;
		}
	}

	protected function createZookeeperService()
	{
		$ret = false;
		if(isset($this->config['fsof_setting']['zk_url_list']))
		{
			try
			{
				$zkUrlList = $this->config['fsof_setting']['zk_url_list'];
				$zkUrlArr = explode(',', $this->config['fsof_setting']['zk_url_list']);
				$registryUrl = array();
				foreach ($zkUrlArr as $zkUrl)
				{
					$url = new FSOFUrl($zkUrl);
					$registryUrl[] = $url;
				}

				//创建与zookeeper连接用来上报和注销service信息
				$this->zkService = RegistryServiceFactory::getRegistry($registryUrl);
				//动态通过回调进行注册
				$this->zkService->registerCallFunc(array($this,'watcherCallFunc'));
				//连接zookeeper
				$ret = $this->zkService->connectZk($this->ephemeral);
				if($ret == false)
				{
					//重新连接一次
					$ret = $this->zkService->connectZk($this->ephemeral);
				}
				if($ret == false)
				{
                    $this->logger->error('connect zookeeper failed|app:' . $this->appName . '|zkurl:' . $zkUrlList);
				}
			}
			catch (\Exception $e)
			{
                $this->logger->error('connect zookeeper failed|app:'.$e->getMessage(),$e);
			}
		}
		return $ret;
	}

	protected function ServiceSerialize()
	{
		try 
		{
			unset($this->fsofUrlList);
			if (!empty($this->serverProviders))
			{
				if ($this->ephemeral)
				{
					//注册时间
					//$this->config["service_properties"]["timestamp"] = (int)(microtime(true) * 1000);
					$this->config["service_properties"]["dynamic"] = "true";
				} 
				else
				{
					$this->config["service_properties"]["dynamic"] = "false";
				}

				$services = $this->serverProviders;
				foreach ($services as $interface => $serviceInfo)
				{
					//合并全局配置
					if (isset($this->config["service_properties"]))
					{
						//接口配置优先级高于全局配置，所以$serviceInfo放后面
						$serviceInfo = array_merge($this->config["service_properties"], $serviceInfo);
					}

					//不用上报的信息去掉
					unset($serviceInfo['service']);
					unset($serviceInfo['p2p_mode']);

					if (empty($serviceInfo["version"]))
					{
						$serviceInfo['version'] = FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT;
					}
					
					//与dubbo兼容处理
					$serviceInfo['interface'] = $interface;//dubbo_admin需要使用
                    //序列化方式
                    $serviceInfo['serialization']= "fastjson";

					ksort($serviceInfo);//参数排序，与dubbo兼容
					
					$urlPara = array(
						'scheme' => 'dubbo',
						'host' => $this->localIp,
						'port' => $this->port,
						'path' => '/' . $interface,
						//http_build_query会进行urlencode导致query参数被多编码一次，使用urldecode抵消
						'query' => urldecode(http_build_query($serviceInfo)),
					);
                    $this->logger->debug("serviceInfo:" . json_encode($serviceInfo) . "|urlPara:" . json_encode($urlPara));
					try
					{
						$fsofUrl = new FSOFUrl($urlPara);
					}
					catch (\Exception $e)
					{
                        $this->logger->error('init url failed|app:' . $this->appName . '|urlPara:' . json_encode($urlPara));
					}
					$this->fsofUrlList[] = $fsofUrl;
				}
			}
		}
		catch(\Exception $e)
		{
			$errMsg = $e->getMessage();
            $this->logger->error('ServiceSerialize:'.$errMsg, $e);
		}
	}

	protected function registerServiceToZk()
	{
		$ret = false;
		
		if(!empty($this->fsofUrlList))
		{
			foreach($this->fsofUrlList as $fsofUrl)
			{
				try
				{
					$ret = $this->zkService->register($fsofUrl);
				}
				catch(\Exception $e)
				{
					$ret = false;
					$errMsg = $e->getMessage();
                    $this->logger->error('register|app:'.$this->appName.'|url:'.$fsofUrl->getOriginUrl().'|path:'.$fsofUrl->getZookeeperPath().'|errMsg:'.$errMsg);
				}
			}
		}
		
		return $ret;
	}

	protected function unRegisterServiceFromZk()
	{
		$ret = false;
		if(!empty($this->fsofUrlList))
		{
			foreach($this->fsofUrlList as $fsofUrl)
			{
				try
				{
					//清理服务注册数据
					$ret = $this->zkService->unregister($fsofUrl);
					//清理服务脏数据
					$host = $fsofUrl->getHost();
					$port = $fsofUrl->getPort();
					$service = $fsofUrl->getService();
					$language = $fsofUrl->getParams('language');
					$application = $fsofUrl->getParams('application');
					$providerInfo =  FSOFRedis::instance()->getProviderInfo($service);
					if(is_array($providerInfo))
					{
						foreach($providerInfo as $index => $url)
						{
							$urlObj = new FSOFUrl($url);
							if(!empty($urlObj))
							{
								if($service  == $urlObj->getService() && $host == $urlObj->getHost() && $port == $urlObj->getPort()
									&& $application == $urlObj->getParams('application') && $language == $urlObj->getParams('language'))
								{
									$ret = $this->zkService->unregister($urlObj);
                                    $this->logger->info('unRegister|app:'.$this->appName.'|url:'.$urlObj->getOriginUrl());
								}
							}
						}
					}
				}
				catch(\Exception $e)
				{
					$ret = false;
					$errMsg = $e->getMessage();
                    $this->logger->error('unRegister|app:'.$this->appName.'|url:'.$fsofUrl->getOriginUrl().'|path:'.$fsofUrl->getZookeeperPath().'|errMsg:'.$errMsg);
				}
			}
			//断开redis连接
			FSOFRedis::instance()->close();
		}
		return $ret;
	}

	protected function setWatcherCallFunc()
	{
		$this->zkService->registerCallFunc(array($this,'watcherCallFunc'));
	}

	protected function setZkLog()
	{
		//设置zookeeper日志
		$zkLog_level = isset($this->config['fsof_setting']['zklog_level'])?$this->config['fsof_setting']['zklog_level']:self::ZOOKEEPER_LOG_NO;
		if ($zkLog_level > self::ZOOKEEPER_LOG_NO)
		{
			//开启zookeeper日志输出开关
			if ($zkLog_level > self::ZOOKEEPER_LOG_DEBUG)
			{
				$zkLog_level = self::ZOOKEEPER_LOG_DEBUG;
			}

			//设置zookeeper的日志文件及日志级别（1.error; 2.warn; 3.info; 4.debug)
            if(isset($this->config['fsof_setting']['zklog_path']) && !empty($this->config['fsof_setting']['zklog_path'])){
                $this->zkService->setLogFile($this->config['fsof_setting']['zklog_path'], $zkLog_level);
            }else{
                $this->zkService->setLogFile("/var/fsof/provider/zookeeper.log", $zkLog_level);
            }
		}
	}

	protected function inventZkService()
	{
		unset($this->zkService);
	}

	/*
	 * 提供给外部调用函数
	 *
	 */
	public function registerZk()
	{
		if(!$this->start_without_registry)
		{
			try
			{
                $this->logger->info("init zk start...");
				//生成service urls
				$this->ServiceSerialize();
				$this->createZookeeperService();
				$this->setZkLog();

				if (!$this->ephemeral)
				{
					//静态注册模式
					$this->registerServiceToZk();
					$this->inventZkService();
				}
				//连接成功后，通过注入watcherCallFunc函数进行注册
                $this->logger->info("init zk end...");
			}
			catch (\Exception $e)
			{
                $this->logger->error($e->getMessage(),$e);
			}
		}
	}

	public function unRegisterZk()
	{
		if(!$this->start_without_registry)
		{
			try
			{
				if (!$this->ephemeral)
				{
					//静态注册模式重新连接
					$this->createZookeeperService();
				}
				$this->unRegisterServiceFromZk();
				$this->inventZkService();
                $this->logger->info("unRegisterZk");
			}
			catch (\Exception $e)
			{
                $this->logger->error($e->getMessage(),$e);
			}
		}
	}

	public function watcherCallFunc()
	{
		$args = func_get_args();
        $this->logger->info("watcherCallFunc:".json_encode($args,true));
		if(\Zookeeper::CONNECTED_STATE == $args[1])
		{
            $this->logger->info("connect to zk:OK");
			$this->registerServiceToZk();
		}
		else if(\Zookeeper::SESSIONEXPIRED == $args[1])
		{
            $this->logger->error("zk's session expired, reconnect to zk");
			$this->registerZk();
		}
		else
		{
            $this->logger->error("zk's connect expired, errorCode:%d", $args[1]);
		}
	}
}