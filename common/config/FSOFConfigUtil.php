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

class FSOFConfigUtil
{
    public static function loadConfigFile($configFile)
    {
		if (! file_exists($configFile))
		{
            \Logger::getLogger(__CLASS__)->error($configFile." can not be loaded");
			return array();
		}
		$config = parse_ini_file($configFile, true);
        return $config;
    }

    public static function get($configFile, $key, $default = NULL)
    {
    	$config = self::loadConfigFile($configFile);
        $result = isset($config[$key]) ? $config[$key] : $default;        
        return $result;
    }

    public static function getField($configFile, $key, $filed, $default = NULL)
    {
    	$config = self::loadConfigFile($configFile);
        $result = isset($config[$key][$filed]) ? $config[$key][$filed] : $default;
        return $result;
    }

	public static function parse_ini_file_multi($file, $process_sections = false, $scanner_mode = INI_SCANNER_NORMAL) 
	{
	    $explode_str = '.';
	    $escape_char = "'";
	    // load ini file the normal way
	    $data = parse_ini_file($file, $process_sections, $scanner_mode);
	    if (!$process_sections) 
	    {
	        $data = array($data);
	    }
	    foreach ($data as $section_key => $section) 
	    {
	        // loop inside the section
	        foreach ($section as $key => $value) 
	        {
	            if (strpos($key, $explode_str)) 
	            {
	                if (substr($key, 0, 1) !== $escape_char) 
	                {
	                    // key has a dot. Explode on it, then parse each subkeys
	                    // and set value at the right place thanks to references
	                    $sub_keys = explode($explode_str, $key);
	                    $subs =& $data[$section_key];
	                    foreach ($sub_keys as $sub_key) 
	                    {
	                        if (!isset($subs[$sub_key])) 
	                        {
	                            $subs[$sub_key] = array();
	                        }
	                        $subs =& $subs[$sub_key];
	                    }
	                    // set the value at the right place
	                    $subs = $value;
	                    // unset the dotted key, we don't need it anymore
	                    unset($data[$section_key][$key]);
	                }
	                else 
	                {
	                	// we have escaped the key, so we keep dots as they are
	                    $new_key = trim($key, $escape_char);
	                    $data[$section_key][$new_key] = $value;
	                    unset($data[$section_key][$key]);
	                }
	            }
	        }
	    }
	    if (!$process_sections) 
	    {
	        $data = $data[0];
	    }
	    return $data;
	}	
}