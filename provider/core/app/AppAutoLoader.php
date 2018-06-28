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
namespace com\fenqile\fsof\provider\core\app;

class AppAutoLoader 
{
	private static $root_path = array();
	private static $class_autoload_path = array();

    /**
     * [set_include_path 完成根目录下目录的扫描，支持多层目录嵌套]
     * @param [type] $dir [路径]
     * @return array
     */
	private static function set_include_path($dir)
	{
		$include_paths = array();
		$include_paths[] = $dir;
		
		//读取所有的文件夹目录
		$arr = scandir($dir);		
		$len = count($arr);
		for ($i=0; $i < $len; $i++) 
		{
			//.和..去掉
		    if (('.' == $arr[$i]) ||  ('..' == $arr[$i]))
            {
                continue;
            }
			if(is_dir($dir.DIRECTORY_SEPARATOR.$arr[$i]))
			{
				$include_paths =  array_merge($include_paths, self::set_include_path($dir.DIRECTORY_SEPARATOR.$arr[$i]));
			}
		}
		
		return array_unique($include_paths);
	}

	/**
	 * [auto_load]
	 * @param  [type] $className [文件名]
	 * @return [type]            [description]
	 */
	public static function auto_load($className)
	{
		$newClassName = str_replace('\\', DIRECTORY_SEPARATOR, trim($className, '\\'));
		foreach (self::$class_autoload_path as $key => $path) 
		{
			$class_file = $path.DIRECTORY_SEPARATOR.$newClassName.".php";
			if (is_file($class_file)) 
			{
				require_once($class_file);
				break;
			}
		}
	}

	/**
	 * [setRoot 设置root根目录，可以同时添加多个]
	 * @param array $root [array]
	 */
	public static function setRoot($rootArr = array())
	{
		if(is_array($rootArr))
		{
			self::$root_path = array_merge(self::$root_path, $rootArr);
			
			foreach (self::$root_path as $key => $value) 
			{
				self::$class_autoload_path = array_merge(self::$class_autoload_path,self::set_include_path($value));
			}
		}
	}

	/**
	 * [addRoot 添加root节点，可以多节点实现auto_load]
	 * @param [type] $root [description]
	 */
	public static function addRoot($root)
	{
        \Logger::getLogger(__CLASS__)->debug('addRoot() in '.$root);
		if(isset($root))
		{
			self::$root_path[] = $root;
			foreach (self::$root_path as $key => $value)
			{
				self::$class_autoload_path = array_merge(self::$class_autoload_path,self::set_include_path($value));
			}
		}
        \Logger::getLogger(__CLASS__)->debug('addRoot() out '.print_r(self::$class_autoload_path, true));
	}

	/**
	 * [getFatherPath 获取父级目录路径]
	 * @param  [type]  $path [description]
	 * @param  integer $num  [父级的级数，默认是当前目录的上一级目录]
	 * @return [type]        [路径字符串]
	 */
	public static function getFatherPath($path, $num = 1)
	{
		if (empty($path)) 
		{
			return "";
		}
		
		for ($i = 0; $i < $num; $i++) 
		{
			$path = substr($path,0,strrpos($path ,DIRECTORY_SEPARATOR));
		}
		return $path;
	}
}

spl_autoload_register(array(__NAMESPACE__ . '\AppAutoLoader','auto_load'));