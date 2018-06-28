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
namespace com\fenqile\fsof\consumer\proxy;

use com\fenqile\fsof\common\url\FSOFUrl;
use com\fenqile\fsof\common\log\FSOFSystemUtil;
use com\fenqile\fsof\common\config\FSOFConstants;
use com\fenqile\fsof\common\config\FSOFCommonUtil;
use com\fenqile\fsof\registry\automatic\ConsumerProxy;


final class ProxyFactory
{
	/**
	 * @var  App加载的配置文件路径
	 */
	protected static $appConfigFile = '';

	/**
	 * @var  p2p模式开关
	 */
	protected static $p2pMode = FALSE;

    /**
     * @var app所属的group
     */
    protected static $appGroup = FSOFConstants::FSOF_SERVICE_GROUP_ANY;

    /**
     * @var app所属的version
     */
    protected static $appVersion = FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT;
        
    /**
     * @var  appname
     */
    protected static $appName = 'consumer';

	/**
	 * @var  instance array
	 */
    protected static $serviceInstances = array();

    /**
	 * @var  *.consumer中配置的service信息
	 */
	protected static $serviceConsumers = array();

    private static $logger;

	public static function setConsumerConfig($configData, $consumerConfigFile, $initSettings)
	{
        self::$logger = \Logger::getLogger(__CLASS__);

		//App名字
		self::$appName = $initSettings['app_name'];

		//获取app加载配置文件;输出日志方便问题定位
		self::$appConfigFile = $consumerConfigFile;

		//是否开启p2p模式, p2p模式下, 将使用[consumer_services]下的信息进行路由
		if(isset($configData['consumer_config']['p2p_mode']) && $configData['consumer_config']['p2p_mode'])
		{
			self::$p2pMode = $configData['consumer_config']['p2p_mode'];
		}

		if (isset($configData['consumer_config']['group']))
		{
			self::$appGroup = $configData['consumer_config']['group'];
		}

		if(isset($configData['consumer_config']['version']))
		{
			self::$appVersion = $configData['consumer_config']['version'];
		}

		if(isset($configData['consumer_services']))
		{
			self::$serviceConsumers = $configData['consumer_services'];
		}
	}

    //use automatic registry
    private static function getInstancByRedis($service, $ioTimeOut, $version, $group)
	{
        $ret = NULL;
        $providerInfo = ConsumerProxy::instance()->getProviders($service, $version, $group);
        if(!empty($providerInfo))
        {
            $cacheKey = $service.':'.$version.':'.$group;
            if(empty(self::$serviceInstances[$cacheKey]))
            {
                $ret = Proxy::newProxyInstance($service,  self::$appName, $group);
                self::$serviceInstances[$cacheKey] = $ret;
            }
            else
            {
                $ret = self::$serviceInstances[$cacheKey];
            }

            //设置io超时时间
            $ret->setIOTimeOut($ioTimeOut);
			
            //设置地址列表
            $ret->setAddress($providerInfo);
        }
        else
        {
            self::$logger->error("not find providers form redis for $service:$version:$group");
        }
        
        return $ret;     
    }

    //use p2p mode
    private static function getInstanceByP2P($service, $ioTimeOut, $version, $group)
    {
        $ret = NULL;
        if(array_key_exists($service, self::$serviceConsumers))
        {
            $serviceProperty = self::$serviceConsumers[$service];
			if (isset($serviceProperty['url']))
			{
				$ret = Proxy::newProxyInstance($service, self::$appName, $group);

				//设置io超时时间
				$ret->setIOTimeOut($ioTimeOut);

				//设置所用服务的ip地址列表
				$serviceAddr = explode(",",$serviceProperty['url']);
				$serviceUrls = array();
				foreach ($serviceAddr as $index => $addr)
				{
					$tmpUrl = $addr.'/'."{$service}?version={$version}&group={$group}";
					$serviceUrls[] = new FSOFUrl($tmpUrl);
				}
				$ret->setAddress($serviceUrls);
			}
			else
			{
                self::$logger->warn(self::$appName.'.consumer not exist url');
			}
        }
        else 
        {
            self::$logger->warn('service not found on p2p|consumer_app:'.self::$appName.'|provider_service:'.$service);
        }
        
        return $ret;         
    }

    public static function getInstance($consumerInterface, $ioTimeOut = 3)
    {
    	$ret = NULL;
        $route = '';
        $addressList = 'null';

		//app级组和版本信息
		$group = self::$appGroup;
		$versionList = self::$appVersion;

		if (array_key_exists($consumerInterface, self::$serviceConsumers))
		{
            $serviceProperty = self::$serviceConsumers[$consumerInterface];
			if(isset($serviceProperty['group']))
			{
				$group = $serviceProperty['group'];
			}
			if(isset($serviceProperty['version']))
			{
				$versionList = $serviceProperty['version'];
			}
		}

		try
		{

			//依据配置权重选取版本号
			$version = FSOFCommonUtil::getVersionByWeight($versionList);

			//p2p模式
			if (self::$p2pMode)
			{
				$ret = self::getInstanceByP2P($consumerInterface, $ioTimeOut, $version, $group);
				$route = 'p2p';
			}

			if (empty($ret))
			{
				//registry 模式
				$ret = self::getInstancByRedis($consumerInterface, $ioTimeOut, $version, $group);
				$route = 'auto registry';
			}

			if (empty($ret))
			{
				$errMsg = "current_address:".FSOFSystemUtil::getLocalIP()."|".$consumerInterface;
                throw new \Exception($errMsg);
			}
			else
			{
                $addressList = $ret->getAddressStr();
			}
            self::$logger->debug('consumer_app:'.self::$appName.'|app_config_file:'.self::$appConfigFile.
                '|version:'.$version.'|group:'.$group.'|provider_service:'.$consumerInterface.'|route:'.$route.'|addr_list:'.$addressList.'|timeout:'.$ioTimeOut);
        }
        catch (\Exception $e)
        {
            self::$logger->error('consumer_app:'.self::$appName.'|app_config_file:'.self::$appConfigFile.
                '|version:'.$version.'|group:'.$group.'|provider_service:'.$consumerInterface.'|errmsg:'. $e->getMessage().'|exceptionmsg:'.$e);
        }
    	return $ret;
    }
}