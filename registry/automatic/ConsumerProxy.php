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

use com\fenqile\fsof\common\url\FSOFUrl;
use com\fenqile\fsof\common\file\FSOFRedis;
use com\fenqile\fsof\common\config\FSOFConstants;

class ConsumerProxy
{
    private static $_instance;
    private $logger;

    public static function instance()
    {
        if (!isset(ConsumerProxy::$_instance)) 
        {
            ConsumerProxy::$_instance = new ConsumerProxy();
        }
        return ConsumerProxy::$_instance;
    }

    public function __construct()
    {
        $this->logger = \Logger::getLogger(__CLASS__);
    }

    /**
     * consumer根据查找条件组合，获取相应provider URL信息。ConsumerProxy内部实现共享内存和文件缓存两级缓存，先读共享内存，找不到再度文件缓存
     */
    public function getProviders($service, $version=FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT, $group=FSOFConstants::FSOF_SERVICE_GROUP_ANY)
    {
        try
		{
			//获取路由信息
            $providerInfo = FSOFRedis::instance()->getProviderInfo($service);
			return $this->filterProviderUrls($providerInfo, $version, $group, $service);
        }
        catch (\Exception $e)
        {
			//数据异常关闭连接
			FSOFRedis::instance()->close();
            $this->logger->error('get Provider Info from redis exception:'.$e->getMessage(),$e);
            return NULL;
        }
    }

    private function filterProviderUrls($providerInfo, $version, $group, $service)
	{
		$urls = array();
		if (is_array($providerInfo))
		{
			foreach ($providerInfo as $index => $url)
			{
				try
				{
					$urlObj = new FSOFUrl($url);
					if (!empty($urlObj))
					{
						//服务校验
						if (0 == strncmp($urlObj->getService(), $service, strlen($service)))
						{
							//服务Version强校验
							if ($version == $urlObj->getVersion(FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT))
							{
								if ($group == FSOFConstants::FSOF_SERVICE_GROUP_ANY || $group == $urlObj->getGroup(FSOFConstants::FSOF_SERVICE_GROUP_DEFAULT))
								{
									$urls[] = $urlObj;
                                    if($this->logger->isDebugEnabled()){
                                        $this->logger->debug("find provider form redis for [$service:$version:$group],url:".json_encode($url,true));
                                    }
								}
							}
						}
						else
						{
							//数据出现乱序关闭连接
							FSOFRedis::instance()->close();
                            $this->logger->error('get redis data exception, service:'.$service.'; redis data list:'.json_encode($providerInfo,true));
							break;
						}
					}
				} 
				catch (\Exception $e) 
				{
                    $this->logger->error('error of url:' . $url, $e);
				}
			}
		}
        if (empty($urls))
		{
            $this->logger->warn('version:' .$version. ' group:' .$group. ' service:' .$service. ' get Provider Info from redis is empty.');
		}
		return $urls;
	}
}

