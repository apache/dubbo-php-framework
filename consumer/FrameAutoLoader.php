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
namespace com\fenqile\fsof\consumer;

class FrameAutoLoader
{
    /**
     * 命名空间的路径
     */
    private static $nsPath = Array();

    /**
     * 自动载入类
     * @param $class
     */
    public static function autoload($class)
    {
    	$frameRootPathDepth = count(explode('\\', __NAMESPACE__));
        $root = explode('\\', trim($class, '\\'));
        $pathDepth = count($root);
        if($pathDepth > $frameRootPathDepth)
        {
        	$key = $className = $class;
	        if($pathDepth > $frameRootPathDepth + 1)
	        {
	        	$root = explode('\\', trim($class, '\\'), $frameRootPathDepth + 2);
	        	$className = $root[$frameRootPathDepth + 1];
	        	unset($root[$frameRootPathDepth + 1]);
	        	$key = implode($root, '\\');
	        }
	        else if($pathDepth == $frameRootPathDepth + 1)
	        {
	        	$root = explode('\\', trim($class, '\\'), $frameRootPathDepth + 1);
	        	$className = $root[$frameRootPathDepth];
	        	unset($root[$frameRootPathDepth]);
	        	$key = implode($root, '\\');
	        }
	        
        	if(isset(self::$nsPath[$key]))
	        {
	        	include_once self::$nsPath[$key] . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
	        }
        }
    }

    /**
     * 设置根命名空间
     * @param $root
     * @param $path
     */
    public static function setRootNS($root, $path)
    {
        self::$nsPath[$root] = $path;
    }
}