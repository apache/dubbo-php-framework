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
namespace com\fenqile\fsof\provider\core\protocol;

interface IProtocol
{
	function setServer($server);
    function getAppConfig();
    function onStart($server, $workerId);
    function onConnect($server, $client_id, $from_id);
    function onReceive($server,$client_id, $from_id, $data, $reqInfo = null);
    function onClose($server, $client_id, $from_id);
    function onShutdown($server, $worker_id);
    function onTask($serv, $task_id, $from_id, $data);
    function onFinish($serv, $task_id, $data);
    function onTimer($serv, $interval);
    function onRequest($request, $response);
}