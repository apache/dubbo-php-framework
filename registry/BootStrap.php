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
namespace com\fenqile\fsof\registry;

// 定义registry根目录
define('FSOF_REGISTRY_ROOT_PATH', dirname(__FILE__));
require_once(FSOF_REGISTRY_ROOT_PATH.DIRECTORY_SEPARATOR.'FrameAutoLoader.php');

//注册顶层命名空间到自动载入器
FrameAutoLoader::setRootNS('com\fenqile\fsof\registry', FSOF_REGISTRY_ROOT_PATH);
FrameAutoLoader::setRootNS('com\fenqile\fsof\registry\automatic', FSOF_REGISTRY_ROOT_PATH.DIRECTORY_SEPARATOR.'automatic');

spl_autoload_register(__NAMESPACE__.'\FrameAutoLoader::autoload');