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
namespace com\fenqile\fsof\provider;

require_once(FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'FrameAutoLoader.php');

//加载commom
$fsofCommonPath = FSOF_FRAMEWORK_ROOT_PATH.DIRECTORY_SEPARATOR.'common';
require_once($fsofCommonPath.DIRECTORY_SEPARATOR.'BootStrap.php');

//加载registry
$fsofRegistryPath = FSOF_FRAMEWORK_ROOT_PATH.DIRECTORY_SEPARATOR.'registry';
require_once $fsofRegistryPath.DIRECTORY_SEPARATOR.'BootStrap.php';

//注册顶层命名空间到自动载入器
FrameAutoLoader::setRootNS('com\fenqile\fsof\provider', FSOF_PROVIDER_ROOT_PATH);
FrameAutoLoader::setRootNS('com\fenqile\fsof\provider\core', FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'core');
FrameAutoLoader::setRootNS('com\fenqile\fsof\provider\fsof', FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'fsof');
FrameAutoLoader::setRootNS('com\fenqile\fsof\provider\shell', FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'shell');
FrameAutoLoader::setRootNS('com\fenqile\fsof\provider\common', FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'common');
FrameAutoLoader::setRootNS('com\fenqile\fsof\provider\monitor', FSOF_PROVIDER_ROOT_PATH.DIRECTORY_SEPARATOR.'monitor');
//provider启动时初始化consumer
FrameAutoLoader::setRootNS('com\fenqile\fsof\consumer', FSOF_FRAMEWORK_ROOT_PATH.DIRECTORY_SEPARATOR.'consumer');
spl_autoload_register(__NAMESPACE__.'\FrameAutoLoader::autoload');

//注册全局异常捕获器
function exceptionHandler($exception)
{
	$exceptionHash = array(
    	'className' => 'Exception',
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => array(),
    );

	$traceItems = $exception->getTrace();
    foreach ($traceItems as $traceItem) 
    {
        $traceHash = array(
            'file' => isset($traceItem['file']) ? $traceItem['file'] : 'null',
            'line' => isset($traceItem['line']) ? $traceItem['line'] : 'null',
            'function' => isset($traceItem['function']) ? $traceItem['function'] : 'null',
            'args' => array(),
        );

        if (!empty($traceItem['class'])) 
        {
            $traceHash['class'] = $traceItem['class'];
        }

        if (!empty($traceItem['type'])) 
        {
            $traceHash['type'] = $traceItem['type'];
        }

        if (!empty($traceItem['args'])) 
        {
            foreach ($traceItem['args'] as $argsItem) 
            {
                $traceHash['args'][] = \var_export($argsItem, true);
            }
        }

        $exceptionHash['trace'][] = $traceHash;
    }

    \Logger::getLogger(__CLASS__)->error(print_r($exceptionHash, true));
}
set_exception_handler(__NAMESPACE__ . '\exceptionHandler');