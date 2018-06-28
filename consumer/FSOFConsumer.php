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
namespace com\fenqile\fsof\consumer;

use com\fenqile\fsof\consumer\proxy\ProxyFactory;


class FSOFConsumer
{
    /**
     * @var  boolean  Has [FSOFConsumer::init] been called?
     */
    protected static $_init = FALSE;

    protected static $_initSetting;
    
    public static function init(array $settings = NULL)
    {
        if (self::isConsumerInit())
        {
            // Do not allow execution twice
            return;
        }
        \Logger::getLogger(__CLASS__)->info("consumer cfg:".json_encode($settings, true));

        $consumerRoot = __DIR__;
		$fsofBootPath = dirname($consumerRoot);

        //加载commom
        $fsofCommonPath = $fsofBootPath.DIRECTORY_SEPARATOR.'common';
        require_once($fsofCommonPath.DIRECTORY_SEPARATOR.'BootStrap.php');

        //加载registry
        $fsofRegistryPath = $fsofBootPath.DIRECTORY_SEPARATOR.'registry';
        require_once($fsofRegistryPath.DIRECTORY_SEPARATOR.'BootStrap.php');

        //检查输入参数app_src
        if ((!isset($settings['app_src'])) || (!isset($settings['app_name'])))
		{
            throw new \Exception("FSOFConsumer::init传入的app的src路径参数不准确");
        }        
        $consumerConfigFile = $settings['app_src'];
        $consumerConfigFile = rtrim($consumerConfigFile, DIRECTORY_SEPARATOR);
        $consumerConfigFile = $consumerConfigFile.DIRECTORY_SEPARATOR.'consumer'.DIRECTORY_SEPARATOR.$settings['app_name'].'.consumer';
        if (file_exists($consumerConfigFile))
        {
        	try 
        	{
                $consumerConfig = parse_ini_file($consumerConfigFile, true);     
            } 
            catch (\Exception $e) 
            {
                throw new \Exception("consumer配置文件有误[".$consumerConfigFile."]");
            }
        }
		else
		{
			$consumerConfig = array();
		}

		self::$_initSetting = $settings;

        //注册consumer框架的autoLoader
        self::registerConsumerFrameAutoLoader($consumerRoot);

        //注册consumer的动态代理工厂
        ProxyFactory::setConsumerConfig($consumerConfig, $consumerConfigFile, $settings);

        // FSOFConsumer is now initialized
		self::$_init = TRUE;
    }

    private static function registerConsumerFrameAutoLoader($consumerRoot)
    {
        if (!self::isConsumerInit())
        {
            //注册框架顶层命名空间到自动加载器
            require_once $consumerRoot.DIRECTORY_SEPARATOR.'FrameAutoLoader.php';
            FrameAutoLoader::setRootNS('com\fenqile\fsof\consumer', $consumerRoot);
            FrameAutoLoader::setRootNS('com\fenqile\fsof\consumer\app', $consumerRoot.DIRECTORY_SEPARATOR.'app');
            FrameAutoLoader::setRootNS('com\fenqile\fsof\consumer\fsof', $consumerRoot.DIRECTORY_SEPARATOR.'fsof');
            FrameAutoLoader::setRootNS('com\fenqile\fsof\consumer\proxy', $consumerRoot.DIRECTORY_SEPARATOR.'proxy');
            FrameAutoLoader::setRootNS('com\fenqile\fsof\consumer\client', $consumerRoot.DIRECTORY_SEPARATOR.'client');
            spl_autoload_register(__NAMESPACE__.'\FrameAutoLoader::autoload');
        }
    }

    public static function getInitSetting()
    {
        return  self::$_initSetting;
    }
    
    public static function isConsumerInit()
    {
        return  self::$_init;
    }

}