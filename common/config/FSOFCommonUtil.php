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

class FSOFCommonUtil
{
	public static function parseWeightInfo($versionWeightList)
	{
		$versionLists = array();
		$versionWeight = explode(",", $versionWeightList);
		foreach ($versionWeight as $index => $value)
		{
			$versionWeight = explode(":" ,$value);
			if (count($versionWeight) == 1)
			{
				$versionLists[$value] = 0;
			}
			else
			{
				$versionLists[$versionWeight[0]] = $versionWeight[1];
			}
		}
		return $versionLists;
	}

	public static function getWeightSum($versionLists)
	{
		$weightSum = 0;
		foreach ($versionLists as $key => $value)
		{
			$weightSum += $value;
		}
		return $weightSum;
	}

	public static function getVersionByWeight($versionWeightList)
	{
		$version = $versionWeightList;
		$versionLists = self::parseWeightInfo($versionWeightList);
		$weightSum = self::getWeightSum($versionLists);
		if ($weightSum > 1)
		{
			$startValue = 0;
			$dstValue = mt_rand(0, $weightSum-1);
			foreach ($versionLists as $key => $value)
			{
				if($dstValue >= $startValue && $dstValue < $value + $startValue)
				{
					$version = $key;
					break;
				}
				else
				{
					$startValue += $value;
				}
			}
		}
		return $version;
	}
}
