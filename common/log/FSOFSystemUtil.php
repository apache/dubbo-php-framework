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

namespace com\fenqile\fsof\common\log;

class FSOFSystemUtil
{
	private static $localHost = null;
	private static $defaultHost = '127.0.0.1';

	public static function getLocalIP()
	{
		if (!isset(FSOFSystemUtil::$localHost))
		{
			if(extension_loaded('swoole'))
			{
				$ipList = swoole_get_local_ip();
				foreach($ipList as $key => $local)
				{
                    if ($local != self::$defaultHost)
                    {
                        FSOFSystemUtil::$localHost = $local;
                        break;
                    }
				}

				if (empty(FSOFSystemUtil::$localHost))
				{
					FSOFSystemUtil::$localHost = self::$defaultHost;
				}
			}
			else
			{
				FSOFSystemUtil::$localHost = self::$defaultHost;
			}
		}
		return FSOFSystemUtil::$localHost;
	}

    public static function getServiceIP()
    {
        $result = self::getLocalIP();

		if ($result == self::$defaultHost)
		{
            throw new \Exception("本服务器网络地址错误出错");
		}

        return $result;
    }
}
