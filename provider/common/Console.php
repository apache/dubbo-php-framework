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
namespace com\fenqile\fsof\provider\common;

class Console
{
    /**
     * 改变进程的用户ID
     * @param $user
     */
    public static function changeUser($user)
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

    public static function setProcessName($name)
    {
        if (function_exists('cli_set_process_title'))
        {
            cli_set_process_title($name);
        }
        else if(function_exists('swoole_set_process_name'))
        {
            swoole_set_process_name($name);
        }
        else
        {
            trigger_error(__METHOD__." failed. require cli_set_process_title or swoole_set_process_name.");
        }
    }
}