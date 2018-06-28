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
namespace com\fenqile\fsof\provider\core\server;

interface IServer
{
    function run($setting);
    function send($client_id, $data);
    function close($client_id);
    function setProtocol($protocol);
    //当前app是否提供了满足条件的服务
    function serviceExist($serviceName,  $group, $version);
    //获取app中满足条件的服务实例，一个app提供的所有服务都以单实例的形式存于内存中
    function getServiceInstance($serviceName,  $group, $version);
    //获取app的监控器
    function getAppMonitor();
}