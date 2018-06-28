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

class FSOFConstants
{
	//任何服务分组
	const FSOF_SERVICE_GROUP_ANY = '*';
    //默认服务分组
    const FSOF_SERVICE_GROUP_DEFAULT = 'default';
    //默认服务版本
    const FSOF_SERVICE_VERSION_DEFAULT = '1.0.0';

	//monitor定时监控时间，暂定5分钟
	const FSOF_MONITOR_TIMER = 300000;

	//所使用的redis端口号
	const FSOF_SERVICE_REDIS_PORT =6379;
}