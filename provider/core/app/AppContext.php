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


final class AppContext
{
    private $instances = array();

    private $stateless = FALSE;//默认所有服务都是有状态的，每次使用都重新new
    
    private $server;
    
    public function setStateless($stateless, $server)
    {
    	$this->stateless = $stateless;
    	$this->server = $server;	
    }
    
    public function isStateless()
    {
        return $this->stateless;
    }
    
    public function getInstance($className, $params = null)
    {
		if($this->stateless)
		{
            \Logger::getLogger(__CLASS__)->debug("get stateless instance for $className");
	        if (isset($this->instances[$className])) 
	        {
	            return $this->instances[$className];
	        }
	        
	        if (!class_exists($className,true)) 
	        {
	            throw new \Exception("no class {$className}");
	        }
	        
	        if (empty($params)) 
	        {
	            $this->instances[$className] = new $className();
	        } 
	        else 
	        {
	            $this->instances[$className] = new $className($params);
	        }
	        
	        return $this->instances[$className];
        }
        else
        {
            \Logger::getLogger(__CLASS__)->debug("get new instance for $className");
        	if (!class_exists($className,true)) 
	        {
	            throw new \Exception("no class {$className}");
	        }
	        
        	if (empty($params)) 
	        {
	            return new $className();
	        } 
	        else 
	        {
	            return new $className($params);
	        } 
	    }
    }
}