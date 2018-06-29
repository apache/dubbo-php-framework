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
namespace com\fenqile\fsof\provider\shell;

use com\fenqile\fsof\common\config\FSOFConfigManager;
use com\fenqile\fsof\provider\core\server\TcpServer;

define("BIN_DIR", __DIR__);
define('MYROOT',BIN_DIR."/../..");

require_once MYROOT . '/vendor/autoload.php';
require_once MYROOT . '/vendor/apache/log4php/src/main/php/Logger.php';

// 定义provider shell目录
define('FSOF_PROVIDER_SHELL_PATH', __DIR__);
// 定义provider 根目录
define('FSOF_PROVIDER_ROOT_PATH', dirname(FSOF_PROVIDER_SHELL_PATH));
//定义FSOF框架根目录
define('FSOF_FRAMEWORK_ROOT_PATH', dirname(FSOF_PROVIDER_ROOT_PATH));

//提取cmd和 name
$name = $argv[1];
$cmd = $argv[2];
if (empty($cmd) || empty($name))
{
    echo("please input cmd and server name!");
    exit(1);
}

//log系统初始化需要用到appName,暂时以常量方式处理
if(!defined('FSOF_PROVIDER_APP_NAME')) define('FSOF_PROVIDER_APP_NAME', $name);
//载入Provider 框架代码
require_once FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'FSOFProvider.php';

//读取conf目录下的$name.deploy文件,然后启动对应的server
if (($cmd != 'stop' && $cmd != 'reload' ) && !FSOFConfigManager::isProviderAppDeploy($name))
{
    echo "{$name} can not find deploy file or bootstrap.php".PHP_EOL;
	exit(1);
}

$config = FSOFConfigManager::getProviderAppDeploy($name);
if(isset($config['server']['log_cfg_file_path']) && !empty($config['server']['log_cfg_file_path']))
{
    \Logger::configure($config['server']['log_cfg_file_path']);
    date_default_timezone_set('PRC');
}

\Logger::getLogger(__CLASS__)->info("input {$cmd} {$name}");
$server = new TcpServer($name);
//加载app root目录下的bootstrap.php和provider/$name.provider文件
$server->setRequire(FSOFConfigManager::getProviderAppRoot($name));

//app/conf目录下的$name.deploy文件
$server->loadConfig($config);
//全局fsof.ini文件
$server->loadConfig(FSOFConfigManager::getFSOFIni());

//设置swoole扩展日志文件
$server->setSwooleLogFile($cmd);

//provider启动时初始化consumer
$server->initConsumer();

//初始化server资源
$server->initRunTime('/var/fsof/provider');

//启动
$server->run($cmd);