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
namespace com\fenqile\fsof\common\config;

class FSOFConfigManager
{

	public static function getFSOFIni()
	{
		$fsof_config_file = FSOF_INI_CONFIG_FILE_PATH.DIRECTORY_SEPARATOR.'fsof.ini';
		$fsof_config_data = FSOFConfigUtil::loadConfigFile($fsof_config_file);
		return 	$fsof_config_data;
	}

	public static function getDeployFile($name)
	{
		$env = self::getCurrentEnvironment();
		return FSOF_PHP_CONFIG_ROOT_PATH.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'conf'.DIRECTORY_SEPARATOR.$env.DIRECTORY_SEPARATOR.'provider'.DIRECTORY_SEPARATOR.$name.'.deploy';
	}
	
	public static function getCurrentEnvironment()
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			$currentEnv = 'dev';
			return $currentEnv;
		}
		$currentEnv = 'pro';
		$fsof_config_data = self::getFSOFIni();
		if(isset($fsof_config_data['fsof_setting']['environment']))
		{
			$currentEnv = $fsof_config_data['fsof_setting']['environment'];
		}
		else
		{
            \Logger::getLogger(__CLASS__)->error('fsof.ini is not set environment, default is pro');
		}
		return 	$currentEnv;
	}

	public static function getKeepConnect()
	{
		$keep_connect = false;
		$fsof_config_data = self::getFSOFIni();
		if(isset($fsof_config_data['fsof_setting']['keep_connect']))
		{
			$keep_connect = $fsof_config_data['fsof_setting']['keep_connect'];
		}
		return 	$keep_connect;
	}

	public static function isExistProviderDeploy($appName)
	{
		$deployFile = self::getDeployFile($appName);
		if(file_exists($deployFile))
		{
			return $deployFile;
		}

		\Logger::getLogger(__CLASS__)->error('not deploy '.$appName.":".$deployFile);
		return null;
	}

	public static function isExistProviderFile($name)
	{
		$appBootFile = self::getProviderAppRoot($name);
		if (isset($appBootFile) && file_exists($appBootFile) && is_file($appBootFile))
		{
			return true;
		}
		else
		{
            \Logger::getLogger(__CLASS__)->error('not process ' . $name . ":" . $appBootFile);
		}
		return false;
	}

	public static function isProviderAppDeploy($appName)
	{
		$deployFile = self::getDeployFile($appName);
   		if(file_exists($deployFile))
   		{
   		    $appBootFile = self::getProviderAppRoot($appName);
            if(isset($appBootFile) && file_exists($appBootFile) && is_file($appBootFile))
            {
                return TRUE;
            }
            else
            {
                \Logger::getLogger(__CLASS__)->error("{$appName}  bootstrap not exist:".$appBootFile);
            }
   		}
		else
		{
            \Logger::getLogger(__CLASS__)->error("{$appName}  deploy file not exist:".$deployFile);
		}
   		return FALSE;
	}
	
	public static function getProviderAppDeploy($appName)
	{
		$deployFile = self::getDeployFile($appName);
   		$deployFileData = parse_ini_file($deployFile, TRUE);	
   		return 	$deployFileData;
	}
	
	public static function getProviderAppRoot($appName)
	{
		$deployFile = self::getDeployFile($appName);
   		if(file_exists($deployFile))
   		{
   		    $config = parse_ini_file($deployFile, TRUE);
   		    if(isset($config['server']['root']))
   		    {
    		    $appBootFile = $config['server']['root'];
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
                {
                	$appBootFile = str_replace('/', '\\', $appBootFile);
                }
				if(strpos($appBootFile, DIRECTORY_SEPARATOR) > 0)
				{
					//对于相对路径,需要拼接成完整路径
					$appBootFile = FSOF_PHP_CONFIG_ROOT_PATH.DIRECTORY_SEPARATOR.$appBootFile;
				}
                return $appBootFile;
   		    }
   		}
   		return NULL;
	}

    public static function trimall($str)
    {
    	//删除空格，换行符及其它
        $qian=array(" ","　","\t","\n","\r");
        $hou=array("","","","","");
        return str_replace($qian,$hou,$str);
    }

	public static function isVersion($version)
	{
		if(empty($version))
		{
			return FALSE;
		}
		$match_times = preg_match('/^(dev\.|prod\.|)\d+\.\d+\.\d+/', $version);
		if($match_times > 0)
		{
			return TRUE;
		}
		return FALSE;
	}

    public static function getDeployVersion($rootPath) 
    {
        $ret = NULL;
        $versionFilePath = dirname(dirname($rootPath)).DIRECTORY_SEPARATOR.'version.config';
        if (file_exists($versionFilePath)) 
        {
            $ret = self::trimall(file_get_contents($versionFilePath));
			if(!self::isVersion($ret))
			{
				$ret = NULL;
                \Logger::getLogger(__CLASS__)->error("version.config: version is error:".$ret);
			}
        }
        return $ret;
    }

    public static function selectDeployVersion($bootFile, $version)
    {
        //插入发布版本信息
        if(empty($version) || empty($bootFile))
        {
            return $bootFile;
        }
        $rootPath = explode(DIRECTORY_SEPARATOR, $bootFile);
        $num = count($rootPath);
        $tmp = array($num+1);
        for($i = 0; $i < $num; $i++)
        {
            if($i < $num-2)
            {
                $tmp[$i] = $rootPath[$i];
            }
            else if($i == $num-2)
            {
                $tmp[$i] = $version;
                $tmp[$i + 1] = $rootPath[$i];
            }
            else
            {
                $tmp[$i + 1] = $rootPath[$i];
            }
        }
        $bootFile = implode(DIRECTORY_SEPARATOR, $tmp);
        return $bootFile;
    }
    
	public static function getProviderAppList()
	{
		//遍历所有的deploy文件，所有appname采用'*'
		$deployDir = self::getDeployFile('*');
		$deploylist = glob($deployDir);
		return 	$deploylist;
	}

	public static function getRunProviderList()
	{
		$pid = FSOF_PROVIDER_PID_PATH.'*.master.pid';
		$pidList = glob($pid);
		return $pidList;
	}
}
