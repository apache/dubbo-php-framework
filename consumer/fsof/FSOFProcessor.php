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
namespace com\fenqile\fsof\consumer\fsof;

use com\fenqile\fsof\common\config\FSOFConstants;
use com\fenqile\fsof\common\protocol\fsof\DubboParser;
use com\fenqile\fsof\common\protocol\fsof\DubboRequest;
use com\fenqile\fsof\common\protocol\fsof\DubboResponse;
use com\fenqile\fsof\consumer\client\FSOFClient4Linux;

class FSOFProcessor
{
    const FSOF_CONNECTION_RESET = 104;

    const FSOF_ETIMEOUT = 110;
    const FSOF_EINPROGRESS = 115;
    const FSOF_ECONNREFUSED = 111;

    protected $parser;

    private  $logger;

    public function __construct()
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        $this->parser = new DubboParser();
    }

    public function executeRequest(DubboRequest $request, $svrAddr, $ioTimeOut, &$providerAddr)
    {
        //计算服务端个数
        $svrNum = count($svrAddr);
        //连接异常重试次数最多2次
        $connect_try_times = ($svrNum > 2) ? 2 : $svrNum;
        $client = NULL;
        for ($i = 0; $i < $connect_try_times; $i++)
        {
            try
            {
                //获取路由下标
                $col = mt_rand(0, $svrNum-1);
                $svrUrl = $svrAddr[$col];
                $host = $svrUrl->getHost();
                $port = $svrUrl->getPort();

                //记录路由信息
                $providerAddr = $host.':'.$port;

                //透传到服务端字段
                $request->host = $host;
                $request->port = $port;
                $request->setGroup($svrUrl->getGroup(FSOFConstants::FSOF_SERVICE_GROUP_ANY));
                $request->setVersion( $svrUrl->getVersion(FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT));
                $request->setTimeout($ioTimeOut * 1000);

                $client = $this->connectProvider($host, $port, $ioTimeOut);
                if(empty($client))
                {
                    //记录连接错误日志
                    $this->logger->error("connect FSOF server[".$host.":".$port ."] failed");
                    //删除无用地址信息
                    $svrAddr[$col] = NULL;
                    $svrAddr = array_filter($svrAddr);
                    if(self::FSOF_ECONNREFUSED == $this->lastErrorNo)
                    {
                        //连接拒绝
                        continue;
                    }
                    else if(self::FSOF_ETIMEOUT == $this->lastErrorNo || self::FSOF_EINPROGRESS == $this->lastErrorNo)
                    {
                        //连接超时
                        break;
                    }
                    else
                    {
                        //其他错误
                        continue;
                    }
                }
                else
                {
                    break;
                }
            }
            catch (\Exception $e)
            {
                if (!empty($client))
                {
                    unset($client);
                }
                $this->logger->error($e->getMessage(), $e);
            }
        }

        //与服务端进行交互
        $ret = NULL;
        if(isset($client))
        {
            try
            {
                $data = $this->parser->packRequest($request);
                $dataLen = strlen($data);
                if(!$client->send($data, $dataLen))
                {
                    $client->close(true);
                    unset($client);
                    $msg = json_encode($request->__toString(), JSON_UNESCAPED_UNICODE);
                    if (mb_strlen($msg, 'UTF-8') >= 512)
                    {
                        $msg = mb_substr($msg, 0, 512, 'UTF-8').' ...(len:'.strlen($msg).")";
                    }
                    $this->logger->error("send date failed：" . $msg);
                    throw new \Exception("发送请求数据失败");
                }
            }
            catch (\Exception $e)
            {
                $client->close(true);
                unset($client);
                $msg = json_encode($request->__toString(), JSON_UNESCAPED_UNICODE);
                if (mb_strlen($msg, 'UTF-8') >= 512)
                {
                    $msg = mb_substr($msg, 0, 512, 'UTF-8').' ...(len:'.strlen($msg).")";
                }
                $this->logger->error("send date failed：" . $msg, $e);
                throw new \Exception("发送请求数据失败");
            }

            try
            {
                $ret = $this->recvDataFromProvider($client, $request);
                $client->close();
                unset($client);
            }
            catch (\Exception $e)
            {
                $client->close(true);
                unset($client);
                throw $e;
            }
        }
        else
        {
            throw new \Exception("与服务器建立连接失败");
        }
        return $ret;
    }

    protected function connectProvider($host, $port, $iotimeout)
    {
        try
        {
            $start_time = microtime(true);//取到微秒

			$client = new FSOFClient4Linux();

            if ($client->connect($host,$port,$iotimeout))
            {
                $this->logger->debug('connect to server['.$host.":".$port."] success,timeout:".$iotimeout);
            }
            else
            {
                //记录错误码
                $this->lastErrorNo = $client->getlasterror();
                $cost_time = (int)((microtime(true) - $start_time) * 1000000);
                $this->logger->error('connect to server['.$host.":".$port."] failed,timeout:".$iotimeout."|".$cost_time."us".'|errcode:'.$this->lastErrorNo);
                unset($client);
            }
        }
        catch (\Exception $e)
        {
            unset($client);
            $this->logger->error("Connect provider exception:",$e);
        }

        if (isset($client))
        {
            return $client;
        }
        else
        {
            return NULL;
        }
    }

    protected function recvDataFromProvider($socket, DubboRequest $request)
    {
        $fsof_data = $this->Recv($socket, DubboParser::PACKAGE_HEDA_LEN);
        if (!$fsof_data)
        {
            if (0 == $socket->getlasterror())
            {
                throw new \Exception("provider端己关闭网络连接");
            }
            else
            {
                throw new \Exception("接收应答数据超时");
            }
        }

        //解析头
        $response = new DubboResponse();
        $response->setFullData($fsof_data);
        $response = $this->parser->parseResponseHeader($response);
        if (($response) && ($response->getSn() != $request->getSn()))
        {
            $this->logger->error("response sn[{$response->getSn()}] != request sn[{$request->getSn()}]");
            throw new \Exception("请求包中的sn非法");
        }

        //接收消息体
        $resData = substr($response->getFullData(), DubboParser::PACKAGE_HEDA_LEN);
        if ($resData)
        {
            $resDataLen = strlen($resData);
        }
        else
        {
            $resDataLen = 0;
        }

        if ($resDataLen < $response->getLen())
        {
            //取到微秒
            $start_time = microtime(true);
            //如果长度超过1M，则分包处理,以1M为单位分包
            $resv_len = $response->getLen() - $resDataLen;
            $cur_len = 0;
            $recv_data = '';
            do
            {
                if (DubboParser::MAX_RECV_LEN > $resv_len)
                {
                    $cur_len = $resv_len;
                }
                else
                {
                    $cur_len = DubboParser::MAX_RECV_LEN;
                }
                $tmpdata = $this->Recv($socket, $cur_len);
                if ($tmpdata)
                {
                    $recv_data = $recv_data . $tmpdata;
                    $resv_len -= $cur_len;
                }
                else
                {
                    if (0 == $socket->getlasterror())
                    {
                        throw new \Exception("provider端己关闭网络连接");
                    }
                    else
                    {
                        throw new \Exception("接收应答数据超时");
                    }
                }
                //如果超过15秒就当超时处理
                if ((microtime(true) - $start_time) > 15)
                {
                    $this->logger->error("Multi recv {$resv_len} bytes data timeout");
                    throw new \Exception("接收应答数据超时");
                }
            } while ($resv_len > 0);

            $response->setFullData($response->getFullData() . $recv_data);
        }

        if ($this->parser->parseResponseBody($response))
        {
            if(DubboResponse::OK != $response->getStatus())
            {
                throw new \Exception($response->getErrorMsg());
            }
            else
            {
                return $response->getResult();
            }
        }
        else
        {
            $this->logger->error("parse response body err:".$response->__toString());
            throw new \Exception("未知异常");
        }
    }

    protected function Recv($socket, $len)
    {
        try
        {
            $resv_len = $len;
            $_data = '';
            $cnt = 20;//最多循环20次，防止provider端挂掉时，consumer陷入死循环
            do
            {
                $cnt--;
                $tmp_data = $socket->recv($resv_len);
                if (!$tmp_data)
                {
                    $this->logger->warn("socket->recv faile:$resv_len");
                    break;
                }
                $_data = $_data . $tmp_data;
                $resv_len -= strlen($tmp_data);
            } while (($resv_len > 0) && ($cnt > 0));

            if ($resv_len > 0)
            {
                $this->logger->error("Recv $len data fail!");
                return FALSE;
            }

            return $_data;
        }
        catch (\Exception $e)
        {
            $this->logger->error('recv data exception',$e);
            if(self::FSOF_CONNECTION_RESET == $e->getCode())
            {
                throw new \Exception("未知异常");
            }
            else
            {
                throw new \Exception("接收应答数据超时");
            }
        }
    }
}