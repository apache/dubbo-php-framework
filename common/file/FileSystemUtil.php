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
namespace com\fenqile\fsof\common\file;

class FileSystemUtil
{
    /**
     * 递归创建目录
     * @param $dir
     * @param int $mode
     * @return bool
     */
    public static function makeDir($dir, $mode = 0777)
    {
        if (is_dir($dir) || mkdir($dir, $mode, true)) 
        {
            return true;
        }
        
        if (!self::makeDir(dirname($dir), $mode)) 
        {
            return false;
        }
        return mkdir($dir, $mode);
    }

    /**
     * 递归获取目录下的文件
     * @param $dir
     * @param string $filter
     * @param array $result
     * @param bool $deep
     * @return array
     */
    public static function treeDir($dir, $filter = '', &$result = array(), $deep = false)
    {
        $files = new \DirectoryIterator($dir);
        foreach ($files as $file) 
        {
            if ($file->isDot()) 
            {
                continue;
            }
            
            $filename = $file->getFilename();

            if ($file->isDir()) 
            {
                self::treeDir($dir . DIRECTORY_SEPARATOR . $filename, $filter, $result, $deep);
            } 
            else 
            {
                if(!empty($filter) && !preg_match($filter, $filename))
                {
                    continue;
                }
                
                if ($deep) 
                {
                    $result[$dir][] = $filename;
                } 
                else 
                {
                    $result[] = $dir . DIRECTORY_SEPARATOR . $filename;
                }
            }
        }
        
        return $result;
    }

    /**
     * 递归删除目录
     * @param $dir
     * @param $filter
     * @return bool
     */
    public static function deleteDir($dir, $filter = '')
    {
        $files = new \DirectoryIterator($dir);
        foreach ($files as $file) 
        {
            if ($file->isDot()) 
            {
                continue;
            }
            
            $filename = $file->getFilename();
            
            if ($file->isDir()) 
            {
                self::deleteDir($dir . DIRECTORY_SEPARATOR . $filename);
            } 
            else 
            {
            	if (!empty($filter) && !preg_match($filter, $filename)) 
            	{
                	continue;
            	}
            	
                unlink($dir . DIRECTORY_SEPARATOR . $filename);
            }
        }
        return rmdir($dir);
    }

    public static function require_file($path)
    {
        $bRet = false;
        if(is_file($path)) {
            require_once($path);
            $bRet = true;
        }

        return $bRet;
    }
}
