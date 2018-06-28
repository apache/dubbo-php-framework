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

use com\fenqile\fsof\consumer\FSOFConsumer;
use com\fenqile\fsof\consumer\proxy\ProxyFactory;

class FSOFApi
{
	private static $__FSOF_CONSUMER_APP_NAME__ = NULL;
	private static $__FSOF_CONSUMER_APP_SRC_PATH__ = NULL;

    /**
     * 设置Consumer运行上下文环境，在使用fsof_consumer时，该函数要最先调用
     * @param $appName consumer app的名字
     * @param $appSrcPath consumer app src源码的绝对路径
     */
	public static function configure($appName, $appSrcPath)
	{
		self::$__FSOF_CONSUMER_APP_NAME__ = $appName;
		self::$__FSOF_CONSUMER_APP_SRC_PATH__ = $appSrcPath;
	}

    /**
     *
     *    获取指定provider服务的proxy对象
     * @param $service  service配置文件中的key 例：com.alibaba.test.TestService      必选
     * @param int $ioTimeout 连接超时时间和接收数据超时时间 单位 s;支持小数0.5 500ms
     * @return \com\fenqile\fsof\consumer\proxy\Proxy|null
     */
	public static function newProxy($service ,$ioTimeout = 3)
	{
		$s = NULL;
		if(empty(self::$__FSOF_CONSUMER_APP_NAME__) || empty(self::$__FSOF_CONSUMER_APP_SRC_PATH__))
		{
            \Logger::getLogger(__CLASS__)->error("appName or appSrcPath not set,please use FSOFApi::configure() for set.");
            return null;
		}
		else
		{
			if(!is_numeric($ioTimeout) || $ioTimeout <= 0)
			{
				$ioTimeout = 3;
			}
            $consumerFile = dirname(__DIR__).DIRECTORY_SEPARATOR.'consumer'.DIRECTORY_SEPARATOR.'FSOFConsumer.php';
            //加载FSOFConsumer
            require_once($consumerFile);
            $app_setting = array('app_name' => self::$__FSOF_CONSUMER_APP_NAME__, 'app_src' => self::$__FSOF_CONSUMER_APP_SRC_PATH__);
            FSOFConsumer::init($app_setting);
            $s = ProxyFactory::getInstance($service, $ioTimeout);
		}
		return $s;
	}
}