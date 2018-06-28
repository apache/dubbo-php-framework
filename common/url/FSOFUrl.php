<?php
namespace com\fenqile\fsof\common\url;

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
 
class FSOFUrl
{
    const URL_SCHEME = 'scheme';
    const URL_HOST = 'host';
    const URL_PORT = 'port';
    const URL_QUERY = 'query';
    const URL_PATH = 'path';
    
    const URL_VERSION = 'version';
    const URL_GROUP = 'group';
	const URL_SET = 'set';
    const URL_SERVICE = 'service';

    const URL_APPLICATION = 'application';
    const URL_CATEGORY = 'category';

	const URL_WEIGHT = 'weight';

    private $originUrl = NULL;
    private $encodedUrl = NULL;
    private $zkPath = NULL;

    private $query = NULL;
    private $schema = NULL;
    private $host = NULL;
    private $port = NULL;
    private $group = NULL;
	private $set = NULL;
    private $service = NULL;
    private $version = NULL;
    private $params = NULL;
	private $weight = NULL;

    private $logger;

    public function __construct($urlPara)
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        if(is_string($urlPara))
        {
            if(!$this->initByStr($urlPara))
            {
                $this->logger->error('FSOFUrl initByStr failed|urlPara:'.$urlPara);
                throw new \Exception("传入URL非法, FSOFUrl初始化失败");
            }
        }
        else if(is_array($urlPara))
        {
            if(!$this->initByArr($urlPara))
            {
                $this->logger->error('FSOFUrl initByArr failed|urlPara:'.json_encode($urlPara));
                throw new \Exception("传入URL非法, FSOFUrl初始化失败");
            }
        }
        else
        {
            throw new \Exception("传入URL非法, FSOFUrl初始化失败");
        }
    }

    private function  initByStr($urlPara)
    {
        $this->originUrl = $urlPara;

        $ret = $this->parse($this->originUrl);
        if(!$ret)
        {
            return FALSE;
        }

        return $this->initByArr($ret);
    }

    private function initByArr($urlPara)
    {
        if( !isset($urlPara[self::URL_SCHEME]) ||
            !isset($urlPara[self::URL_HOST]) ||
            !isset($urlPara[self::URL_PORT]))
        {
            return FALSE;
        }

        $this->params = $urlPara;
        $this->schema = $urlPara[self::URL_SCHEME];
        $this->host =  $urlPara[self::URL_HOST];
        $this->port =  $urlPara[self::URL_PORT];
        $getArgs = null;

        if(isset($urlPara[self::URL_QUERY]))
        {
            $this->query = $urlPara[self::URL_QUERY];
            parse_str($this->query,$getArgs);
            $this->params = array_merge($this->params,$getArgs);
        }

		if (isset ($getArgs))
		{
			if(isset($getArgs[self::URL_VERSION]))
			{
				$this->version = $getArgs[self::URL_VERSION];
			}

			if(isset($getArgs[self::URL_GROUP]))
			{
				$this->group = $getArgs[self::URL_GROUP];
			}

			if (array_key_exists(self::URL_WEIGHT,$getArgs))
			{
				$this->weight = intval($getArgs[self::URL_WEIGHT]);
			}
			else
			{
				$this->weight = null;
			}

			if(isset($getArgs[self::URL_SET]))
			{
				$this->set = $getArgs[self::URL_SET];
			}

			if(isset($urlPara[self::URL_PATH]))
			{
				$this->service = ltrim($urlPara[self::URL_PATH], '/');
			}
			else if(isset($getArgs[self::URL_SERVICE]))
			{
				$this->service = $getArgs[self::URL_SERVICE];
			}
		}

        $this->joinUrlStr();

        return true;
    }

    private function joinUrlStr()
    {
        if(empty($this->originUrl))
        {
            $this->originUrl = $this->schema.'://'.$this->host.':'.$this->port.'/'.$this->service.'?'.$this->query;
            $this->encodedUrl = urlencode($this->originUrl);
        } 
        else 
        {
            $this->encodedUrl = urlencode($this->originUrl);
        }
        
        return true;
    }

    public function getOriginUrl()
    {
        return $this->originUrl;
    }

    public function getEncodedUrl()
    {
        return $this->encodedUrl;
    }

    public  function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

	public function getGroup($defaultValue = NULL)
	{
		if(empty($this->group))
		{
			return $defaultValue;
		}
		else
		{
			return $this->group;
		}
	}

    public function getSet($defaultValue = NULL)
    {
        if(empty($this->set))
        {
            return $defaultValue;
        }
        else
        {
            return $this->set;
        }
    }

    public function getVersion($defaultValue = NULL)
    {
        if(empty($this->version))
        {
            return $defaultValue;
        }
        else
        {
            return $this->version;
        }
    }

    public function getService()
    {
        return $this->service;
    }

    public function getZookeeperPath()
    {
        $root = '/dubbo';
        $providers = 'providers';
        $this->zkPath = $root.'/'.$this->getService().'/'.$providers.'/'.$this->getEncodedUrl();
        return $this->zkPath;
    }

    public function getApplication()
    {
        return $this->getparam(self::URL_APPLICATION);
    }

    public function getCategory()
    {
        return $this->getparam(self::URL_CATEGORY);
    }

	public function getDefaultWeight($weight)
	{
		if(null === $this->weight)
		{
			return $weight;
		}
		else
		{
			return $this->weight;
		}
	}

	public function getWeight()
	{
		return $this->weight;
	}

	public function setWeight($weight)
	{
		$this->weight = $weight;
	}

	public function getParams($key)
	{
		return $this->getparam($key);
	}

    private function parse($url)
    {
        return parse_url($url);
    }

    private function getparam($key)
    {
        if(isset($this->params[$key]))
        {
            return $this->params[$key];
        }
        else
        {
            return NULL;
        }
    }
}