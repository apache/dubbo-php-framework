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
namespace com\fenqile\fsof\provider\core\app;

use com\fenqile\fsof\provider\fsof\FSOFProtocol;

//加载Provider框架代码
require_once(FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'FSOFProvider.php');

class AppLauncher
{    
    public static function  createApplication($appBootLoader)
    {
    	/**
    	 * 执行app的初始化操作，所有初始化逻辑都在$appBootLoader中定义
    	 * 先加载app的初始化文件，这样app的自定义autoload机制优先级高于AppLauncher
      	**/
    	require_once $appBootLoader;
    	
    	//autoload 用户的所有代码
    	$appRoot = AppAutoLoader::getFatherPath($appBootLoader, 1);
        AppAutoLoader::addRoot($appRoot);
        return new FSOFProtocol();
    }
}