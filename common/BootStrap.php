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
namespace com\fenqile\fsof\common;

//定义common根目录
$fsof_common_root_path = __DIR__;
require_once($fsof_common_root_path.DIRECTORY_SEPARATOR.'FrameAutoLoader.php');

// 定义config模块的根路径
if(!defined('FSOF_CONFIG_ROOT_PATH')) define('FSOF_CONFIG_ROOT_PATH', dirname($fsof_common_root_path));

//fsof.ini文件路径
$fsofIniConfigFilePath = FSOF_CONFIG_ROOT_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'global'.DIRECTORY_SEPARATOR.'conf';
if(!defined('FSOF_INI_CONFIG_FILE_PATH')) define('FSOF_INI_CONFIG_FILE_PATH', $fsofIniConfigFilePath);

//app.deploy文件路径
$fsofPhpConfigPath = FSOF_CONFIG_ROOT_PATH.DIRECTORY_SEPARATOR.'config';
if(!defined('FSOF_PHP_CONFIG_ROOT_PATH')) define('FSOF_PHP_CONFIG_ROOT_PATH', $fsofPhpConfigPath);

//注册顶层命名空间到自动载入器
FrameAutoLoader::setRootNS('com\fenqile\fsof\common', $fsof_common_root_path);
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\log', $fsof_common_root_path.DIRECTORY_SEPARATOR.'log');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\url', $fsof_common_root_path.DIRECTORY_SEPARATOR.'url');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\file', $fsof_common_root_path.DIRECTORY_SEPARATOR.'file');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\config', $fsof_common_root_path.DIRECTORY_SEPARATOR.'config');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\context', $fsof_common_root_path.DIRECTORY_SEPARATOR.'context');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\protocol', $fsof_common_root_path.DIRECTORY_SEPARATOR.'protocol');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\fuse', $fsof_common_root_path.DIRECTORY_SEPARATOR.'fuse');
FrameAutoLoader::setRootNS('com\fenqile\fsof\common\report', $fsof_common_root_path.DIRECTORY_SEPARATOR.'report');

spl_autoload_register(__NAMESPACE__.'\FrameAutoLoader::autoload');

