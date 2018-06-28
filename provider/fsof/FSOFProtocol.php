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
namespace com\fenqile\fsof\provider\fsof;

use com\fenqile\fsof\common\log\FSOFSystemUtil;
use com\fenqile\fsof\common\context\FSOFContext;
use com\fenqile\fsof\common\protocol\fsof\DubboParser;
use com\fenqile\fsof\common\protocol\fsof\DubboRequest;
use com\fenqile\fsof\common\protocol\fsof\DubboResponse;
use com\fenqile\fsof\provider\core\protocol\BufferedProtocol;


class FSOFProtocol extends BufferedProtocol
{
	protected $parser;
    //缓存各consumer请求包数据，直到请求包数据完整接收到
    protected $buffer_header = array();

	//定义swoole work 运行状态
	const FSOF_SWOOLE_STATUS_OK = 0;
	const FSOF_SWOOLE_STATUS_OVERLOAD = 1;
    private $logger;

    public function init()
    {
        $this->logger = \Logger::getLogger(__CLASS__);
        $this->parser = new DubboParser();
    }

    private function checkHeader($client_id, $fsof_data)
    {
    	$request = NULL;
        if (!isset($this->requests[$client_id]))
		{
			//新连接
            $this->logger->debug("new request from {$client_id}");
            if (!empty($this->buffer_header[$client_id]))
			{
                $fsof_data = $this->buffer_header[$client_id].$fsof_data;
            }
            $this->buffer_header[$client_id] = $fsof_data;

            //数据长度还不够
            if (strlen($fsof_data) < DubboParser::PACKAGE_HEDA_LEN)
			{
                return false;
            }
			else
			{
                unset($this->buffer_header[$client_id]);
                $request = new DubboRequest();
                $request->setFullData($fsof_data);
                $request = $this->parser->parseRequestHeader($request);
                //解析失败
                if ($request == false)
				{
                    $this->logger->error("parse request Header fail. fsof_data=" . $fsof_data);
                    return false;
                }
                //保存请求
                $this->logger->debug("create one request for {$client_id}");
                $this->requests[$client_id] = $request;
            }
        }
		else
		{
            $this->logger->debug("append request data for {$client_id}");
            $request = $this->requests[$client_id];
            $request->setFullData($fsof_data);
        }
        return $request;
    }

    public function checkBuffer($client_id, $data)
    {
        //检测头
        $request = $this->checkHeader($client_id, $data);
        //错误的http头
        if ($request === false)
		{
            if (empty($this->buffer_header[$client_id]))
			{
                $this->logger->error("fsof header err.");
                return self::STATUS_ERROR;
            }
			else
			{
                $this->logger->debug("wait head data. fd={$client_id}");
                return self::STATUS_WAIT;
            }
        }

        if ($request->getRequestLen() <= strlen($request->getFullData()))
		{
			if($this->parser->isHearBeatRequest($request))
			{
				//心跳机制
				return self::STATUS_FINISH;
			}
			else
			{
				if ($this->parser->parseRequestBody($request))
				{
                    $this->logger->debug("parse request ok!");
					return self::STATUS_FINISH;
				}
				else
				{
                    $this->logger->error("fsof body err.");
					return self::STATUS_ERROR;
				}
			}
        }
		else
		{
            $this->logger->debug("wait body data. fd={$client_id}");
            return self::STATUS_WAIT;
        }
    }

	private function checkSwooleStatus($request, $host, $port)
	{
		$status = self::FSOF_SWOOLE_STATUS_OK;
		$appConfig = $this->getAppConfig();
		if(false == $appConfig['fsof_setting']['overload_mode'])
		{
			return $status;
		}
		$reqInQueueTime = DubboParser::getReqInQueueTime($request);
		if($reqInQueueTime)
		{
			$appName = $this->getAppName();
			//waiting time inqueue(ms)
			$waitingTime = $appConfig['fsof_setting']['waiting_time'];
			//Number of packet overload in a row
			$overloadNumber = $appConfig['fsof_setting']['overload_number'];
			$lossNumber = $appConfig['fsof_setting']['loss_number'];
            $this->logger->debug($host.":".$port.', waitingTime = '.$waitingTime.', overloadNumber = '.$overloadNumber.', lossNumber = '.$lossNumber);
			$curTimestamp = round(microtime(TRUE)*1000);
			$reqInQueueTime_ms = round($reqInQueueTime / 1000);
			if(($curTimestamp - $reqInQueueTime_ms) >= $waitingTime)
			{
				//swoole_table记录过载的次数新增1
				$this->server->getOverloadMonitor()->overloadIncr();
                $this->logger->warn($host.':'.$port.'|' .$appName.'|服务过载|inQueue:'.$reqInQueueTime_ms.'; curTime:'.$curTimestamp.';waitingTime:'.$waitingTime);
				//判断连续过载次数是否达到开启丢包请求阀值
				if ($this->server->getOverloadMonitor()->getoverloadNum() >= $overloadNumber)
				{
					//重置过载次数,开启过载丢包模式
					$this->server->getOverloadMonitor()->resetOverloadNum_setLossNum($lossNumber);
                    $this->logger->error($host.':'.$port.'|' .$appName.'|服务连续过载,开启丢消息模式');
				}
				$status = self::FSOF_SWOOLE_STATUS_OVERLOAD;
			}
			else
			{
				//清理swoole_table过载记录数量
				$this->server->getOverloadMonitor()->clear();
			}
		}
		return $status;
	}

	private function requestProcessor($client_id, $request)
	{
		//开始执行时间
		$request->startTime = microtime(true);
		//监控请求数量
		$this->server->getAppMonitor()->onRequest($request);

		//设置traceContext, 增加本地的IP地址及APP的端口
		$appConfig = $this->getAppConfig();
		$localIP = FSOFSystemUtil::getLocalIP();
		$appPort = $appConfig['server']['listen'][0];

		$params = $request->__toString();
		if (mb_strlen($params, 'UTF-8') >= 512)
		{
			$params = mb_substr($params, 0, 512, 'UTF-8').' ...';
		}
        $this->logger->debug("in|".$params);

        $businessError = false;
        $frameError = false;
		$result = null;
		//业务处理状态
		$requestFlag = false;
		//返回给客户端执行结果信息
		//$errMsg = 'ok';		//异常信息

		//消息在队列等待时间
		$wait_InQueueTime = 0;
		$inQueueTime = DubboParser::getReqInQueueTime($request);
		if($inQueueTime)
		{
			$wait_InQueueTime = round(microtime(true)*1000000) - $inQueueTime;
		}

		//处理前先检测连接是否仍正常，如己断开则不进行处理
		if(!$this->swoole_server->exist($client_id))
		{
			//执行结束时间
			$request->endTime = microtime(true);
			$cost_time = (int)(($request->endTime - $request->startTime)* 1000000);
			goto END_TCP_CLOSE;
		}

		$status = $this->checkSwooleStatus($request, $localIP, $appPort);
		if(self::FSOF_SWOOLE_STATUS_OK == $status)
		{
			if($this->server->serviceExist($request->getService(),  $request->getGroup(), $request->getVersion()))
			{
				$serviceInstance = $this->server->getServiceInstance($request->getService(), $request->getGroup(), $request->getVersion());
				if (null != $serviceInstance)
				{
					try
					{
						$serviceReflection = new \ReflectionObject($serviceInstance);
						if ($serviceReflection->hasMethod($request->getMethod()))
						{
							$method = $serviceReflection->getmethod($request->getMethod());
							//允许invoke protected方法
							$method->setAccessible(true);
                            $params = $request->getParams();
                            if($params == NULL)
                            {
                                $params = array();
                            }
							$result = $method->invokeArgs($serviceInstance, $params);
							$requestFlag = true;
						}
						else
						{
                            $businessError = true;
							$result = 'function not found:'.$request->getMethod().' in '.$request->getService();
                            $this->logger->error("[{$request->getMethod()}] function not found:".$request->getService());
						}
					}
					catch (\Exception $e)
					{
                        $this->logger->error($e);
                        $frameError = true;
                        $result = $e->getMessage().' in '.$e->getFile().'|'.$e->getLine();
					}

					//如果provider service有状态，则$serviceInstance用完后unset,下次请求重新new, 防止内存泄漏; 对于无状态的service,AppContext会复用$serviceInstance
					if (!$this->server->isStateless())
					{
						unset($serviceInstance);
					}
					unset($method);
					unset($serviceReflection);
				}
				else
				{
                    $frameError = true;
					$result ='get instance failed! | '.$request->getService();
                    $this->logger->error(json_encode($result));
				}
			}
			else
			{
                $frameError = true;
				$result = 'service not found:'.$request->getGroup()."/".$request->getService().":".$request->getVersion();
                $this->logger->error(json_encode($result));
			}
		}
		else
		{
            $frameError = true;
			$result = 'provider过载, 请求消息在队列等待时间超过阀值';
		}

		$request->endTime = microtime(true);//执行结束时间
		$cost_time = (int)(($request->endTime - $request->startTime)* 1000000);
				
		if($this->swoole_server->exist($client_id))
		{
			//发送response
			$response = $this->packResponse($client_id, $request, $result, $businessError,$frameError);
			$msg = $response->__toString();
			if (mb_strlen($msg, 'UTF-8') >= 512)
			{
				$msg = mb_substr($msg, 0, 512, 'UTF-8').' ...('.strlen($msg).')';
			}

            $this->logger->debug(sprintf("out|%s|invokeCostTime:%dus|waitInQueueTime:%dus", $msg, $cost_time, $wait_InQueueTime));
		}
		else
		{
			END_TCP_CLOSE:
			$errMsg = "socket closed by consumer, provider discard response data";
            $this->logger->error("out|{$errMsg}|invokeCostTime:{$cost_time}us| waitInQueueTime:{$wait_InQueueTime}us");
			$requestFlag = false;
		}

		if($requestFlag)
		{
			//监控请求正常处理数量
			$this->server->getAppMonitor()->onResponse($request);
		}
		else
		{
			//监控请求错误处理数量
			$this->server->getAppMonitor()->onError($request);
		}
	}

	public function onOneRequest($client_id, $request)
	{

		if($this->parser->isHearBeatRequest($request))
		{
			//心跳请求,不需要数据回送
		}
		else if($this->parser->isNormalRequest($request) || $this->parser->isOneWayRequest($request))
		{
			//获取配置信息
			$appConfig = $this->getAppConfig();
			
			//Number of packet loss in a row
			$lossNumber = $appConfig['fsof_setting']['loss_number'];
			
			//判断是否开启过载丢包模式
			$restOfLostNum = $this->server->getOverloadMonitor()->getLossNum();
			if($restOfLostNum > 0)
			{
				// 加入对过载丢失数据的统计
				$this->server->getAppMonitor()->onRequest($request);
				
				//回复客户端
				if($restOfLostNum >= $lossNumber)
				{
					if($this->swoole_server->exist($client_id))
					{
						$result = 'provider连续过载, 开启丢消息模式';
						$this->packResponse($client_id, $request, $result, false, true);
					}
				}
				else
				{
					if($this->swoole_server->exist($client_id))
					{
						$result = 'provider开启丢消息模式, 连续丢包中...';
						$this->packResponse($client_id, $request, $result, false, true);
					}
				}
				
				//递减丢包数量
				$this->server->getOverloadMonitor()->lossNumDecr();

				//监控请求错误处理数量
				$this->server->getAppMonitor()->onError($request);
			}
			else
			{
				$this->requestProcessor($client_id,$request);
			}
		}
		else
		{
            $this->logger->error("invalid request = $request");
		}
	}

	public function packResponse($client_id, $request, $data, $businessError,$frameError)
	{
		$response = new DubboResponse();
		$response->setSn($request->getSn());
        if($frameError){
            $response->setStatus(DubboResponse::SERVICE_ERROR);
            $response->setErrorMsg($data);
        }
        if($businessError){
            $response->setErrorMsg($data);
        }
		$response->setResult($data);
		$this->sendResponse($response, $client_id);

		return $response;
	}

    public function sendResponse(DubboResponse $response, $client_id)
    {
        try
		{
            $send_data = $this->parser->packResponse($response);
            $send_len = strlen($send_data);
    
            //默认所有server的response最大5M,每个分包允许重发2次，预计最多10次循环，防止网络出错导致server直接挂死在循环中
            $cnt = (($send_len / DubboParser::RESPONSE_TCP_SEGMENT_LEN) + 1) * 2;
            $tmp_len = $send_len;
            for ($i = 0; $i < $cnt; $i++)
			{
                if ($tmp_len > DubboParser::RESPONSE_TCP_SEGMENT_LEN)
				{
                    //大于1M 分段发送
                    $tmp_data = substr($send_data, 0, DubboParser::RESPONSE_TCP_SEGMENT_LEN);
                    if ($this->swoole_server->send($client_id, $tmp_data))
					{
                        $tmp_len -= DubboParser::RESPONSE_TCP_SEGMENT_LEN;
                        $send_data = substr($send_data, DubboParser::RESPONSE_TCP_SEGMENT_LEN);
                    }
					else
					{
                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                        {
                            $last_error_no = $this->swoole_server->errno();
                        }
                        else
                        {
                            $last_error_no = swoole_errno();
                        }
                        if (0 == $last_error_no)
						{
                            //表示该连接己关闭
                            $this->logger->error("当前连接己关闭，发送失败");
                            break;
                        }
						else
						{
                            $this->logger->error('send response split package fail one time!');
                        }
                    }
                    $this->logger->warn('the length of response: '.$send_len.'; send split package '.$i.'/'.$cnt);
                }
				else
				{
                    //小于1M一次性发完
                    if ($this->swoole_server->send($client_id, $send_data))
					{
                        break;
                    }
					else
					{
                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
                        {
                            $last_error_no = $this->swoole_server->errno();
                        }
                        else
                        {
                            $last_error_no = swoole_errno();
                        }
                        if (0 == $last_error_no)
						{
                            //表示该连接己关闭
                            $this->logger->error("当前连接己关闭，发送失败");
                            break;
                        }
						else
						{
                            $this->logger->error('send response last package fail one time!');
                        }
                    }
                }
            }
        }
		catch (\Exception $e)
		{
            $this->logger->error($e->getMessage(), $e);
        }

        return $send_len;
    }
}