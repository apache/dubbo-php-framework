#!/usr/bin/php
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

define("BIN_DIR", __DIR__);
define('MYROOT',BIN_DIR."/..");

require_once MYROOT . '/vendor/autoload.php';
require_once MYROOT . '/vendor/apache/log4php/src/main/php/Logger.php';

use com\fenqile\fsof\common\config\FSOFConfigManager;

define('FSOF_ROOT_PATH', __DIR__);
define('FSOF_PROVIDER_PID_PATH', '/var/fsof/provider/');

if(!defined("SIG_DFL")) define("SIG_DFL", 0);

//加载commom
$fsofCommonPath = dirname(FSOF_ROOT_PATH).DIRECTORY_SEPARATOR.'common';
require_once($fsofCommonPath.DIRECTORY_SEPARATOR.'BootStrap.php');

if(!defined("MASTER_PID_FILE_FORMAT")) define("MASTER_PID_FILE_FORMAT",'.master.pid');
if(!defined("MANAGER_PID_FILE_FORMAT")) define("MANAGER_PID_FILE_FORMAT",'.manager.pid');

if(!defined("FILTER_PROCESS_STATUS")) define("FILTER_PROCESS_STATUS",1);
if(!defined("PS_CMD_WORK_PROCESS")) define("PS_CMD_WORK_PROCESS",2);

if(!defined("PROVIDER_STATE_SUCCESS")) define("PROVIDER_STATE_SUCCESS",0);
if(!defined("PROVIDER_STATE_FAILED")) define("PROVIDER_STATE_FAILED",1);
if(!defined("PROVIDER_STATE_RUNNING")) define("PROVIDER_STATE_RUNNING",2);

//检查swoole及zookeeper、redis是否安装
if(!extension_loaded('swoole'))
{
	fwrite(STDERR,"\033[31;40m [ERROR] \033[0m no swoole extension.\n".PHP_EOL);
	exit(1);
}
if (!extension_loaded('zookeeper'))
{
	fwrite(STDERR,"\033[31;40m [ERROR] \033[0m no zookeeper extension.\n".PHP_EOL);
	exit(1);
}
if (!extension_loaded('redis'))
{
	fwrite(STDERR,"\033[31;40m [ERROR] \033[0m no redis extension.\n".PHP_EOL);
	exit(1);
}

//php app_admin.php ServerName start
if($argc == 3)
{
	$name = $argv[1];
	$cmd = $argv[2];
}
else if (2 == $argc)
{
	$cmd = $argv[1];
}
else
{
	printInfo();
	exit(1);
}

//新命令格式：app_admin cmd appname
$cmds = array('start','stop','reload','status','restart','list','startall','shutdown','reloadall','extstart','extrestart');
$one_cmds = array('start','stop','reload','restart','extstart','extrestart');
$all_cmds = array('list','startall','shutdown','reloadall');
if (!in_array($cmd,$cmds ) || (empty($name)&& in_array($cmd,$one_cmds)) || (!empty($name) && in_array($cmd,$all_cmds)))
{
	printInfo();
	exit(1);
}

if(3 == $argc)
{
    echo "--------app_admin.php $name $cmd--------".PHP_EOL;
}
else
{
    echo "--------app_admin.php $cmd--------".PHP_EOL;
}

if (!empty($name))
{
	$config = FSOFConfigManager::getProviderAppDeploy($name);
	if(isset($config['server']['log_cfg_file_path']) && !empty($config['server']['log_cfg_file_path']))
	{
		\Logger::configure($config['server']['log_cfg_file_path']);
		date_default_timezone_set('PRC');
	}
}

//执行所有的控制命令
if($cmd == 'list')
{
	$configArr = FSOFConfigManager::getProviderAppList();
	echo "---------your server list：---------".PHP_EOL;
	foreach($configArr as $k => $v)
	{
		echo basename($v, '.deploy').PHP_EOL;
	}
	echo '----------------------------'.PHP_EOL;
	exit(1);
}
else if ("startall" == $cmd)
{
	$providerList = FSOFConfigManager::getProviderAppList();
	foreach($providerList as $k => $v)
	{
		$name = basename($v, '.deploy');
		startProvider($name, "start");
	}
}
else if ("reloadall" == $cmd || "shutdown" == $cmd)
{
	$providerList = FSOFConfigManager::getRunProviderList();
	foreach($providerList as $k => $v)
	{
		$name = basename($v, MASTER_PID_FILE_FORMAT);
		if ("reloadall" == $cmd)
		{
			reloadProvider($name);
		}
		else
		{
			stopProvider($name);
			echo "server {$name} stop  \033[32;40m [SUCCESS] \033[0m".PHP_EOL;
		}
	}
}
else if ("reload" == $cmd)
{
	reloadProvider($name);
}
else if ("start" == $cmd || "extstart" == $cmd)
{
	$ret = startProvider($name, $cmd);
	if (!$ret)
	{
		exit(1);
	}
}
else if ("restart" == $cmd || "extrestart" == $cmd)
{
	$ret = restartProvider($name, $cmd);
	if (!$ret)
	{
		exit(1);
	}
}
else if ("stop" == $cmd )
{
	stopProvider($name);
	echo "server {$name} stop  \033[32;40m [SUCCESS] \033[0m".PHP_EOL;
}
else if("status" == $cmd)
{
	if(empty($name))
	{
		$providerList = FSOFConfigManager::getProviderAppList();
		foreach($providerList as $k => $v)
		{
			$name = basename($v, '.deploy');
			getProviderStatus($name);
		}
	}
	else
	{
		getProviderStatus($name);
	}
}

function startServ($cmd, $name)
{
	$startUpPath = dirname(FSOF_ROOT_PATH)."/provider/shell/StartUp.php";
	if(!file_exists($startUpPath))
	{
        echo "{$cmd} server {$name} \033[31;40m [FAIL] \033[0m; no find:{$startUpPath}".PHP_EOL;
		return false;
	}

	initByFSOFConfig();;
	$process = new \swoole_process(function(\swoole_process $worker) use($startUpPath, $cmd, $name)
	{
		$worker->exec(PHP_BIN_PATH, array($startUpPath, $name, $cmd));
	}, false);
	$process->start();
	$exeRet = \swoole_process::wait();
	if($exeRet['code'])
	{
		//创建失败
        echo "{$cmd} server {$name} \033[31;40m [FAILED] \033[0m".PHP_EOL;
        return false;
	}
	else
	{
        echo "{$cmd} server {$name}  \033[32;40m [SUCCESS] \033[0m".PHP_EOL;
        return true;
	}
}

function getProviderMasterPid($name)
{
	$providerFile = FSOF_PROVIDER_PID_PATH.$name.MASTER_PID_FILE_FORMAT;
	if (file_exists($providerFile))
	{
		$pid = file_get_contents($providerFile);
		if (!empty($pid))
		{
			if (posix_kill(intval($pid), SIG_DFL))
			{
				return $pid;
			}
		}
		else
		{
			$master_name = "{$name}_master_process";
			return filterProcess($master_name, PS_CMD_WORK_PROCESS);
		}
	}
	return null;
}

function getProviderManagerPid($name)
{
	$providerFile = FSOF_PROVIDER_PID_PATH.$name.MANAGER_PID_FILE_FORMAT;
	if (file_exists($providerFile))
	{
		$pid = file_get_contents($providerFile);
		if (!empty($pid))
		{
			if (posix_kill(intval($pid), SIG_DFL))
			{
				return $pid;
			}
		}
		else
		{
			$manager_name = "{$name}_manager_process";
			return filterProcess($manager_name, PS_CMD_WORK_PROCESS);
		}
	}
	return null;
}

function checkMasterProcessExist($name)
{
	$pid = getProviderMasterPid($name);
	if (empty($pid))
	{
		return false;
	}
	else
	{
        echo "the pid of {$name} is :".$pid.PHP_EOL;
		return true;
	}
}

function checkProviderInfo($name)
{
	$providerDeploy = FSOFConfigManager::isExistProviderDeploy($name);
	if (empty($providerDeploy))
	{
		fwrite(STDERR,"\033[31;40m [ERROR] \033[0m your server {$name} not deploy".PHP_EOL);
		return false;
	}

	$phpStart = FSOFConfigManager::isExistProviderFile($name);
	if(!$phpStart)
	{
		fwrite(STDERR,"\033[31;40m [ERROR] \033[0m {$name} root path not exist".PHP_EOL);
		return false;
	}
	return true;
}

function start( $name, $cmd)
{
	$isRun = checkMasterProcessExist($name);
	if ($isRun)
	{
		return PROVIDER_STATE_RUNNING;
	}
	else
	{
		if(startServ($cmd,$name))
		{
			return PROVIDER_STATE_SUCCESS;
		}
		else
		{
			return PROVIDER_STATE_FAILED;
		}
	}
}

function startProvider($name, $cmd)
{
	if (!checkProviderInfo($name))
	{
		return false;
	}

	$ret = start($name, $cmd);
	if ( PROVIDER_STATE_SUCCESS == $ret)
	{
		echo "server {$name} start \033[32;40m [SUCCESS] \033[0m".PHP_EOL;
	}
	else if (PROVIDER_STATE_RUNNING == $ret)
	{
		echo "{$name} is already running".PHP_EOL;
	}
	else
	{
		fwrite(STDERR, "server {$name} start \033[31;40m [FAIL] \033[0m".PHP_EOL);
		return false;
	}
	return true;
}

function restartProvider($name, $cmd)
{
	if (!checkProviderInfo($name))
	{
		return false;
	}
	stopProvider($name);
	$startCmd = "start";
	if ("extrestart" == $cmd)
	{
		$startCmd = "extstart";
	}
	$ret = start($name, $startCmd);
	if (PROVIDER_STATE_FAILED != $ret)
	{
		echo "restart server {$name} \033[32;40m [SUCCESS] \033[0m" . PHP_EOL;
	}
	else
	{
		fwrite(STDERR, "restart server {$name} \033[31;40m [FAIL] \033[0m" . PHP_EOL);
		return false;
	}
	return true;
}

function reloadProvider($name)
{
	$pid = getProviderMasterPid($name);
	if (!posix_kill(intval($pid), SIGUSR1))
	{
		echo $name."server {$name} reload \033[31;40m [FAIL] \033[0m".PHP_EOL;
	}
	else
	{
		fwrite(STDERR, "server {$name} reload \033[32;40m [SUCCESS] \033[0m".PHP_EOL);
	}
}

function waitProcessEnd($name)
{
	/*
	* 先检测该master进程是否存在，检测时间为10s，不存在则退出，如一直存在，则强制杀掉子进程（发送SIGKILL信号）,目的为了manager进程能正常退出, 从zookeeper注销服务
	* ,等待2s后重新检测，如仍存在,则强制杀除master与manager进程
	*/
	$work_name= "{$name}_event_worker_process";
	$stime = time();

	while(time() - $stime < 10)
	{
		$pid = getProviderMasterPid($name);
		if (empty($pid))
		{
			break;
		}
		sleep(1);
	}

	$workPid = trim(filterProcess($work_name, PS_CMD_WORK_PROCESS));
	if (!empty($workPid))
	{
		$pids = explode("\n", $workPid);
		foreach ($pids as $key => $wPid)
		{
			posix_kill(intval($wPid), SIGKILL);
			usleep(10000);
		}
	}

	sleep(1);
	$pid = getProviderMasterPid($name);
	if (!empty($pid))
	{
		$managerPid = getProviderManagerPid($name);
		if(!empty($managerPid))
		{
			posix_kill(intval($managerPid), SIGKILL);
		}
		posix_kill(intval($pid), SIGKILL);
	}
}

function stopProvider($name)
{
	$masterPid = getProviderMasterPid($name);
	if (empty($masterPid))
	{
		waitProcessEnd($name);
	}
	else
	{
		if (!posix_kill(intval($masterPid), SIGTERM))
		{
			fwrite(STDERR, "end signal to {$name}: {$masterPid} failed" );
		}
		waitProcessEnd($name);
	}

	$masterFile = FSOF_PROVIDER_PID_PATH.$name.MASTER_PID_FILE_FORMAT;
	$managerFile = FSOF_PROVIDER_PID_PATH.$name.MANAGER_PID_FILE_FORMAT;
	if (file_exists($masterFile))
	{
		unlink($masterFile);
	}
	if (file_exists($managerFile))
	{
		unlink($managerFile);
	}
    echo "stop server {$name} \033[32;40m [SUCCESS] \033[0m".PHP_EOL;
}

function getProviderStatus($name)
{
	echo "######################\033[32;40m {$name} server status\033[0m##################" . PHP_EOL;
	if(getProviderMasterPid($name))
	{
		$psRet = filterProcess($name, FILTER_PROCESS_STATUS);
		if (empty($psRet))
		{
			echo PHP_EOL . "\033[32;40m {$name} is [NO RUNNING] \033[0m-------------" . PHP_EOL;
		}
		else
		{
			print_r($psRet);
			echo PHP_EOL . "\033[32;40m {$name} is [RUNNING] \033[0m-------------" . PHP_EOL;
		}
	}
	else
	{
		echo PHP_EOL . "\033[32;40m {$name} is [NO RUNNING] \033[0m-------------" . PHP_EOL;
	}
	echo "-------------------------------------------------------------------------------------" . PHP_EOL;
}

function filterProcess($process_name,$type)
{
	if(FILTER_PROCESS_STATUS == $type)
	{
		$shell_cmd = "ps auxf | grep {$process_name} |  grep -v grep | grep -v app_admin";
	}
	elseif(PS_CMD_WORK_PROCESS == $type)
	{
		$shell_cmd = "ps auxf | grep -w {$process_name} |  grep -v grep | awk '{print $2}'";
	}
	else
	{
		return "usage: type(1/2) name";
	}

	$file = popen($shell_cmd, 'r');
	if(empty($file))
	{
        //echo "$shell_cmd.', return null'" . PHP_EOL;
		return null;
	}

	$ret = fread($file,10240);
	pclose($file);

	$ret = trim(print_r($ret,TRUE));
    //echo $shell_cmd.",return ".$ret. PHP_EOL;
	return $ret;
}

function analyseFSOFConfig()
{
	$fsofConfigPath = FSOF_INI_CONFIG_FILE_PATH.DIRECTORY_SEPARATOR.'fsof.ini';
	if (!file_exists($fsofConfigPath))
	{
		fwrite(STDERR,"\033[31;40m [ERROR] \033[0m " . $fsofConfigPath . " can not be loaded");
		return null;
	}
	return parse_ini_file($fsofConfigPath, true);
}

function initByFSOFConfig()
{
	$globalFSOFConfig = analyseFSOFConfig();
	if(!empty($globalFSOFConfig))
	{
		if(isset($globalFSOFConfig['fsof_container_setting']))
		{
			$globalConfig = $globalFSOFConfig['fsof_container_setting'];
		}
	}

	if(strlen(PHP_BINDIR) > 0)
	{
		if(!defined("PHP_BIN_PATH"))
		{
			define('PHP_BIN_PATH', PHP_BINDIR.DIRECTORY_SEPARATOR.'php');
		}
	}
	else
	{
		if(!defined("PHP_BIN_PATH"))
		{
			if (isset($globalConfig['php']))
			{
				define('PHP_BIN_PATH',$globalConfig['php']);
			}
			else
			{
				define('PHP_BIN_PATH','/usr/bin/php');
			}
		}
	}

	// set user
	if (isset($globalConfig['user']))
	{
		$user = $globalConfig['user'];
	}
	else
	{
		$user = 'root';
	}

	changeConsoleUser($user);
}

function changeConsoleUser($user)
{
	if (!function_exists('posix_getpwnam'))
	{
		trigger_error(__METHOD__.": require posix extension.");
		return;
	}
	$user = posix_getpwnam($user);
	if($user)
	{
		posix_setuid($user['uid']);
		posix_setgid($user['gid']);
	}
}

//用于和守护进程进行通信
function printInfo()
{
	echo "usage: app_admin.php  cmd  ||  app_admin.php  app_name  cmd".PHP_EOL;
	echo "example: app_admin startall\t\t\t[\033[32;40m启动所有服务\033[0m]".PHP_EOL;
	echo "         app_admin shutdown\t\t\t[\033[32;40m关闭所有服务\033[0m]".PHP_EOL;
	echo "         app_admin reloadall\t\t\t[\033[32;40m热更新所有服务\033[0m]".PHP_EOL;
	echo "         app_admin list\t\t\t\t[\033[32;40m查看服务\033[0m]".PHP_EOL;
	echo "         app_admin status\t\t\t[\033[32;40m查看所有服务运行状态\033[0m]".PHP_EOL;
	echo "         app_admin app_name status\t\t[\033[32;40m查看app服务运行状态\033[0m]".PHP_EOL;
	echo "         app_admin app_name start\t\t[\033[32;40m启动app服务\033[0m]".PHP_EOL;
	echo "         app_admin app_name restart\t\t[\033[32;40m重启app服务\033[0m]".PHP_EOL;
	echo "         app_admin app_name reload\t\t[\033[32;40m热更新app服务\033[0m]".PHP_EOL;
	echo "         app_admin app_name stop\t\t[\033[32;40m停止app服务\033[0m]".PHP_EOL;
	echo "         app_admin app_name extstart\t\t[\033[32;40m启动扩展的app服务,不注册配置中心\033[0m]".PHP_EOL;
	echo "         app_admin app_name extrestart\t\t[\033[32;40m重启扩展的app服务,不注册配置中心\033[0m]".PHP_EOL;
	exit(1);
}

