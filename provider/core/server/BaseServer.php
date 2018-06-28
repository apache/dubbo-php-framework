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
namespace com\fenqile\fsof\provider\core\server;

use com\fenqile\fsof\common\log\FSOFSystemUtil;
use com\fenqile\fsof\common\file\FileSystemUtil;
use com\fenqile\fsof\common\config\FSOFConstants;
use com\fenqile\fsof\common\config\FSOFConfigManager;
use com\fenqile\fsof\common\protocol\fsof\DubboParser;
use com\fenqile\fsof\common\protocol\fsof\DubboResponse;
use com\fenqile\fsof\consumer\FSOFConsumer;
use com\fenqile\fsof\provider\common\Console;
use com\fenqile\fsof\provider\core\app\AppLauncher;
use com\fenqile\fsof\provider\core\app\AppContext;
use com\fenqile\fsof\provider\core\protocol\IProtocol;
use com\fenqile\fsof\provider\monitor\AppMonitor;
use com\fenqile\fsof\provider\monitor\OverloadMonitor;

abstract class BaseServer implements IServer
{
	//定义过载默认参数
	const FSOF_SWOOLE_WAITING_TIME_MS = 1500;
	const FSOF_SWOOLE_OVERLOAD_NUM_INAROW = 5;
	const FSOF_SWOOLE_LOSS_NUM_INAROW = 20;

    protected $sw;
    protected $processName = 'ProviderServer';
    protected $host = '0.0.0.0';
    protected $port = 9527;
    protected $listen;//监听ip与端口号的array
    protected $mode = SWOOLE_PROCESS;
    protected $sockType;

    protected $config = array(); //服务的配置信息
    protected $setting = array();//swoole_server配置
    protected $runPath = '/var/fsof/provider';
    protected $masterPidFile;
    protected $managerPidFile;
    protected $user = 'root';

    protected $protocol;

    protected $rootFile = '';  //deploy中root路径
    protected $deployVersion = NULL;//provider发布版本
    protected $requireFile = '';  //provider启动文件
    
	protected $serverProviders = NULL;   //服务提供者信息
    protected $appContext;		  //服务提供者存储容器

    //当发生shutdown时，将错误信息回传给client
    protected $cur_fd = -1;
    protected $cur_from_id = -1;

	protected $request = array();

    protected $start_without_registry = false;

	//app过载监控对象
	protected $overloadMonitor;

    private $logger;

	public function __construct($name = 'ProviderServer')
	{
        $this->logger = \Logger::getLogger(__CLASS__);
		$this->processName = $name;
		
        // Initialization server startup parameters
        $this->setting = array(
            'worker_num' => 4,     //PHP代码中是全异步非阻塞，worker_num配置为CPU核数的1-4倍即可。如果是同步阻塞，worker_num配置为100或者更高，具体要看每次请求处理的耗时和操作系统负载状况
        	'max_request' => 5000, //表示worker进程在处理完n次请求后结束运行,设置为0表示不自动重启。在Worker进程中需要保存连接信息的服务，需要设置为0.
            'dispatch_mode' => 3,   // 1平均分配，2按FD取摸固定分配，3抢占式分配，默认为取摸(dispatch=2)
            'task_worker_num' => 0, // task process num
        	'task_max_request' =>5000,
        	'task_ipc_mode' =>3,	//1 使用unix socket通信 , 2使用消息队列通信, 3使用消息队列通信，并设置为争抢模式,此时task/taskwait将无法指定目标进程ID
        	
        	'max_conn' => 10000,  // 设置Server最大允许维持多少个tcp连接,max_connection默认值为ulimit -n的值为1024，通过ulimit -n 65535更改最大默认值
        	'daemonize' => TRUE,    // 是否开启守护进程
        	'work_mode' => 3,		// 1 base模式，2线程模式，3进程模式				
			
        	//协议包长度检测，以保证onReceive函数接收到的数据是个完整的包,并且包的最大长度为2M
        	'open_length_check' => TRUE,
        	'package_length_offset' => 12,
        	'package_body_offset' => 16,
        	'package_length_type' => 'N',
        	'package_max_length' =>1024*1024*2,
        
			//启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
        	'heartbeat_idle_time' => 600,
        	'heartbeat_check_interval' => 60,
        
	        //TCP-Keepalive死连接检测,如果对于死链接周期不敏感或者没有实现心跳机制，可以使用操作系统提供的keepalive机制来踢掉死链接
	        'open_tcp_keepalive' => 1,          // 表示启用tcp keepalive
	        'tcp_keepidle' => 600,               // 单位秒，连接在n秒内没有数据请求，将开始对此连接进行探测
	        'tcp_keepcount' => 3,               // 探测的次数，超过次数后将close此连接。
	        'tcp_keepinterval' => 10,            // 探测的间隔时间，单位秒。
        );

        $this->setHost();
        $this->init();
	}

    abstract public function init();

	public function setRequest($request)
	{
		$this->request = $request;
	}
    
    public function setRequire($file)
    {
        $this->rootFile = $file;
        $this->requireFile = $this->rootFile;
        $this->logger->info("bootstrapFile = {$this->requireFile}");
        if (!file_exists($this->requireFile))
        {
            $this->logger->error("bootstrapFile :{$this->requireFile} is not exists");
			exit(1);
        }

		//加载appname.provider
		$appConfigPath = dirname($this->requireFile).DIRECTORY_SEPARATOR.'provider'.DIRECTORY_SEPARATOR.$this->processName.'.provider';
		if(!file_exists($appConfigPath))
		{
            $this->logger->error("provider file :{$appConfigPath} is not exists");
			exit(1);
		}
        $this->loadConfig($appConfigPath);
        $this->logger->info("provider file :{$appConfigPath}");
    }

	public function initConsumer()
	{
		//初始consumer
		$app_setting = array('app_name' => $this->processName, 'app_src' => dirname($this->requireFile));
		FSOFConsumer::init($app_setting);
	}
	
	public function setSwooleLogFile($cmd)
	{
		if($cmd == 'start' || $cmd == 'restart' || $cmd == 'extstart')
		{
            $this->logger->info($this->processName.'.deploy:{'
																."\"server\":".json_encode($this->config['server']) .','
																."\"setting\":".json_encode($this->config['setting']).'}');
            $this->logger->info('fsof.ini:{'."\"fsof_setting\":".json_encode($this->config['fsof_setting']).'}');
		}
        if(isset($this->config['server']['swoole_log_path']) && !empty($this->config['server']['swoole_log_path'])){
            $this->setting['log_file'] = $this->config['server']['swoole_log_path'];
        }else{
            $this->setting['log_file'] = '/var/fsof/provider/'.$this->processName.'_swoole.log';
        }
	}

    public function loadConfig($config = array())
    {
        if (is_string($config))
        {   
            if (! file_exists($config))
            {
                $this->logger->error("profiles {$config} can not be loaded");
				exit(1);
            }
            // Load the configuration file into an array
            $config = parse_ini_file($config, true);
        }
        
        if (is_array($config))
        {
            $this->config = array_merge($this->config, $config);
        }
        return true;
    }

    public function initRunTime($runPath)
	{
		if (!empty($runPath))
		{
			$this->runPath = $runPath;
		}

    	//记录master进程号和manager进程号
        $this->masterPidFile =  $this->runPath.DIRECTORY_SEPARATOR.$this->processName.'.master.pid';
        $this->managerPidFile = $this->runPath.DIRECTORY_SEPARATOR.$this->processName.'.manager.pid';
        FileSystemUtil::makeDir(dirname($this->masterPidFile));
        
        //获取app的服务信息
		$services = isset($this->config['service_providers']) ? $this->config['service_providers'] : array();
		if (empty($services))
		{
            $this->logger->error($this->processName.".provider's service_providers is empty");
			exit(1);
		}

		foreach($services as $interface => $serviceInfo)
		{
			$this->serverProviders[$interface] = $serviceInfo;
		}

        $this->logger->info("services list:" . json_encode($this->serverProviders));
        
        //增加application, language, environment, set, owner, group信息等信息
        $this->config["service_properties"]["application"] = $this->processName;
        $this->config["service_properties"]["language"] = "php";
        //$this->config["service_properties"]["application_version"] = $this->deployVersion;
        if (empty($this->config["service_properties"]["owner"]))
        {
            $this->config["service_properties"]["owner"]  = $this->processName;
        }

        //swoole setting
        $runSetting = isset($this->config['setting']) ? $this->config['setting'] : array();
        $this->setting = array_merge($this->setting, $runSetting);

        //过载保护配置
		//是否加载provider过载机制
		if (isset($this->setting['overload_mode']))
		{
			$this->config['fsof_setting']['overload_mode'] = $this->setting['overload_mode'];
		}
		else
		{
			$this->config['fsof_setting']['overload_mode'] = isset($this->config['fsof_setting']['overload_mode'])?$this->config['fsof_setting']['overload_mode']:true;
		}
		//加载provider过载时间
		if (isset($this->setting['waiting_time']))
		{
			$this->config['fsof_setting']['waiting_time'] = $this->setting['waiting_time'];
		}
		else
		{
			$this->config['fsof_setting']['waiting_time'] = isset($this->config['fsof_setting']['waiting_time'])?$this->config['fsof_setting']['waiting_time']:self::FSOF_SWOOLE_WAITING_TIME_MS;
		}

		//加载连续过载次数
		if (isset($this->setting['overload_number']))
		{
			$this->config['fsof_setting']['overload_number'] = $this->setting['overload_number'];
		}
		else
		{
			$this->config['fsof_setting']['overload_number'] = isset($this->config['fsof_setting']['overload_number'])?$this->config['fsof_setting']['overload_number']:self::FSOF_SWOOLE_OVERLOAD_NUM_INAROW;
		}
		//加载连续过载后，开启丢消息模式的次数
		if (isset($this->setting['loss_number']))
		{
			$this->config['fsof_setting']['loss_number'] = $this->setting['loss_number'];
		}
		else
		{
			$this->config['fsof_setting']['loss_number'] = isset($this->config['fsof_setting']['loss_number'])?$this->config['fsof_setting']['loss_number']:self::FSOF_SWOOLE_LOSS_NUM_INAROW;
		}

		if (isset($this->config["service_properties"]["p2p_mode"]))
		{
			$this->start_without_registry = $this->config['service_properties']['p2p_mode'];
		}
		else
		{
			if (isset($this->config['fsof_setting']['p2p_mode']))
			{
				$this->start_without_registry = $this->config['fsof_setting']['p2p_mode'];
			}
		}

        //main setting
        $mainSetting = isset($this->config['server']) ? $this->config['server'] : array();

        // trans listener
        if (isset($mainSetting['listen']))
        {
            $this->transListener($mainSetting['listen']);
        }
        if (isset($this->listen[0]))
        {
            $this->host = $this->listen[0]['host'] ? $this->listen[0]['host'] : $this->host;
            $this->port = $this->listen[0]['port'] ? $this->listen[0]['port'] : $this->port;
            unset($this->listen[0]);
        }
        
        // set user
        $globalSetting = isset($this->config['fsof_container_setting']) ? $this->config['fsof_container_setting'] : array();
        if (isset($globalSetting['user']))
        {
            $this->user = $globalSetting['user'];
        }   	
    }

    private function initServer() 
    {
        // Creating a swoole server resource object
        $swooleServerName = '\swoole_server';
        $this->sw = new $swooleServerName($this->host, $this->port, $this->mode, $this->sockType);
        
        //create monitor
    	$monitor = new AppMonitor($this->processName, $this->config);
        //设置appMonitor
        $monitor->setServer($this->sw);
        $this->sw->appMonitor = $monitor;

		//创建overload monitor
		$this->overloadMonitor = new OverloadMonitor($this->processName);

        //对整数进行容错处理
		$this->setting['worker_num'] = intval($this->setting['worker_num']);
        $this->setting['dispatch_mode'] = intval($this->setting['dispatch_mode']);
        $this->setting['daemonize'] = intval($this->setting['daemonize']);

        // Setting the runtime parameters
        $this->sw->set($this->setting);

        // Set Event Server callback function
        $this->sw->on('Start', array($this, 'onMasterStart'));
        $this->sw->on('ManagerStart', array($this, 'onManagerStart'));
        $this->sw->on('ManagerStop', array($this, 'onManagerStop'));
        $this->sw->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->sw->on('Connect', array($this, 'onConnect'));
        $this->sw->on('Receive', array($this, 'onReceive'));
        $this->sw->on('Close', array($this, 'onClose'));
        $this->sw->on('WorkerStop', array($this, 'onWorkerStop'));
        //$this->sw->on('timer', array($this, 'onTimer'));
        if (isset($this->setting['task_worker_num'])) 
        {
            $this->sw->on('Task', array($this, 'onTask'));
            $this->sw->on('Finish', array($this, 'onFinish'));
        }
		$this->sw->on('WorkerError', array($this, 'onWorkerError'));

        // add listener
        if (is_array($this->listen))
        {
            foreach($this->listen as $v)
            {
                if (empty($v['host']) || empty($v['port']))
                {
                    continue;
                }
                $this->sw->addlistener($v['host'], $v['port'], $this->sockType);
            }
        }
        $this->logger->info("server host:".$this->host.'|'.$this->port);
    }

    private function transListener($listen)
    {
        if(!is_array($listen))
        {
            $tmpArr = explode(":", $listen);
            $host = isset($tmpArr[1]) ? $tmpArr[0] : $this->host;
            $port = isset($tmpArr[1]) ? $tmpArr[1] : $tmpArr[0];

            $this->listen[] = array(
                'host' => $host,
                'port' => $port,
            );
            return true;
        }
        
        foreach($listen as $v)
        {
            $this->transListener($v);
        }
    }

	public function onWorkerError($server, $workerId, $workerPid, $exitCode)
	{
        $this->logger->error('Swoole onWorkerError['.'masterPid:'.$server->master_pid.'|managerPid:'.$server->manager_pid.'|workerPid:'.$workerPid.'|workerId:'.$workerId.'|exitCode:'.$exitCode.']');
	}

    public function onMasterStart($server)
    {
    	// rename master process
        Console::setProcessName($this->processName.'_master_process');
        file_put_contents($this->masterPidFile, $server->master_pid);
        file_put_contents($this->managerPidFile, $server->manager_pid);
        if ($this->user)
        {
            Console::changeUser($this->user);
        }
    }

    public function onManagerStart($server)
    {
		set_exception_handler(array($this, 'exceptionHandler'));
		set_error_handler(array($this, 'errorHandler'));

        // rename manager process
        Console::setProcessName($this->processName.'_manager_process');
        if ($this->user)
        {
            Console::changeUser($this->user);
        }
        
        //监控app启动时间
        $this->sw->appMonitor->onAppStart();
                
        //app启动后向zookeeper注册服务信息
		FSOFRegistry::instance()->setParams($this->processName, $this->config, $this->port,
            $this->serverProviders, $this->start_without_registry);
		FSOFRegistry::instance()->registerZk();
    }

    public function onManagerStop($server)
    {
		FSOFRegistry::instance()->unRegisterZk();
    }

    public function onWorkerStart($server, $workerId)
    {
        if($server->taskworker)
        {
        	// rename task process
            Console::setProcessName($this->processName.'_task_worker_process');
        }
        else
        {
        	// rename work process
            Console::setProcessName($this->processName.'_event_worker_process');
        }

        if ($this->user)
        {
            Console::changeUser($this->user);
        }
        //启动app
        //log系统初始化需要用到processName,暂时以常量方式处理
        if(!defined('FSOF_PROVIDER_APP_NAME')) define('FSOF_PROVIDER_APP_NAME', $this->processName);
        $AppLauncherPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'AppLauncher.php';
        require_once $AppLauncherPath;
        $protocol = AppLauncher::createApplication($this->requireFile);
        if (!$protocol)
        {
            $this->logger->error('the protocol class  is empty or undefined');
            throw new \Exception('[error] the protocol class  is empty or undefined');
        }
        $this->setProtocol($protocol);

		//设置appContext
        $this->appContext = new AppContext();
    	//set service statusless
		$service_properties = isset($this->config['service_properties']) ? $this->config['service_properties'] : array();
		if (isset($service_properties['stateless']))
		{
			$this->appContext->setStateless($service_properties['stateless'], $this);
        }
        else
        {
        	$this->appContext->setStateless(FALSE, $this);
        }

        $this->logger->debug("workerId [{$workerId}]  start ok");
        $this->protocol->onStart($server, $workerId);
        
        //设置全局exception，错误处理函数，放在最后，则相关异常错误信息都会被框架捕捉处理
        set_exception_handler(array($this, 'exceptionHandler'));
        set_error_handler(array($this, 'errorHandler'));
        register_shutdown_function(array($this, 'fatalHandler'));

    }

    public function onWorkerStop($server, $workerId)
    {
        $this->logger->debug("workerId [{$workerId}] will stop");
        $this->protocol->onShutdown($server, $workerId);
    }
    
    public function onConnect($server, $fd, $fromId)
    {
        $this->logger->debug("fd [{$fd}] | fromId [{$fromId}] onConnect ");
        $this->protocol->onConnect($server, $fd, $fromId);
    }

    public function onTask($server, $taskId, $fromId, $data)
    {
        $this->protocol->onTask($server, $taskId, $fromId, $data);
    }

    public function onFinish($server, $taskId, $data)
    {
        $this->protocol->onFinish($server, $taskId, $data);
    }

    public function onClose($server, $fd, $fromId)
    {
        $this->logger->debug("fd [{$fd}]| fromId [{$fromId}] onClose ");
        $this->protocol->onClose($server, $fd, $fromId);
    	$this->cur_fd= -1;
    	$this->cur_from_id = -1;
    }

    public function onTimer($server, $interval)
    {
        $this->protocol->onTimer($server, $interval);
    }

    public function onRequest($request, $response) 
    {
        $this->logger->error('FSOF Frame not support http protocol now!');
    }

    public function onReceive($server, $clientId, $fromId, $data)
    {
		$this->cur_fd = $clientId;
		$this->cur_from_id = $fromId;
		$time = 0;
		if (method_exists('swoole_server','getReceivedTime'))
		{
			$time = $this->sw->getReceivedTime()*1000000;
		}
		$reqInfo = array("inqueue_time" => $time);
		$this->protocol->onReceive($server, $clientId, $fromId, $data, $reqInfo);
    }

	public function setProtocol($protocol)
	{
        if(!($protocol instanceof IProtocol))
        {
            $this->logger->error('The protocol is not instanceof IProtocol');
            throw new \Exception('[error] The protocol is not instanceof IProtocol');
        }

		$this->protocol = $protocol;
        $this->protocol->setServer($this);
	}

    public function run($cmd = 'help') 
    {        
        switch ($cmd) 
        {
            //start
			case 'extstart':
			case 'extrestart':
				//开启p2p模式
				$this->start_without_registry = true;
            case 'start':
            case 'restart':
                $this->initServer();
                $this->start();
                break;
            default:
                echo 'Usage: app_admin.php start | stop | reload | restart | extstart | extrestart' . PHP_EOL;
                break;
        }
    }

    protected function start()
    {
       	if ($this->checkServerIsRunning()) 
       	{
            $this->logger->warn($this->processName . ": master process file " . $this->masterPidFile . " has already exists!");
            $this->logger->warn($this->processName . ": start [OK]");
          	return false;
       	}

        $this->logger->info($this->processName . ": start [OK]");
        $this->sw->start();
    }

    protected function getMasterPid() 
    {
        $pid = FALSE;
        if (file_exists($this->masterPidFile)) 
        {
            $pid = file_get_contents($this->masterPidFile);
        }
        return $pid;
    }

    protected function checkServerIsRunning() 
    {
        $pid = $this->getMasterPid();
        return $pid && $this->checkPidIsRunning($pid);
    }

    protected function checkPidIsRunning($pid) 
    {
        return posix_kill($pid, 0);
    }

    public function close($client_id)
    {
        swoole_server_close($this->sw, $client_id);
    }

    public function send($client_id, $data)
    {
        swoole_server_send($this->sw, $client_id, $data);
    }
    
    protected function setHost() 
    {
        $this->host = '0.0.0.0';
    }

	public final function errorHandler($errno, $errstr, $errfile, $errline)
	{
		$msg = sprintf("[%s:%s]|[%s:%d]",$errfile, $errline, $errstr, $errno);
        $this->logger->error($msg);
		throw new \Exception($msg, $errno);
	}
    
	public final function exceptionHandler($exception)
	{
		$exceptionHash = array(
	    	'className' => 'FSOF_Provider_Exception',
	        'message' => $exception->getMessage(),
	        'code' => $exception->getCode(),
	        'file' => $exception->getFile(),
	        'line' => $exception->getLine(),
	        'trace' => array(),
	    );
	
		$traceItems = $exception->getTrace();
	    foreach ($traceItems as $traceItem) 
	    {
	        $traceHash = array(
	            'file' => isset($traceItem['file']) ? $traceItem['file'] : 'null',
	            'line' => isset($traceItem['line']) ? $traceItem['line'] : 'null',
	            'function' => isset($traceItem['function']) ? $traceItem['function'] : 'null',
	            'args' => array(),
	        );
	
	        if (!empty($traceItem['class'])) 
	        {
	            $traceHash['class'] = $traceItem['class'];
	        }
	
	        if (!empty($traceItem['type'])) 
	        {
	            $traceHash['type'] = $traceItem['type'];
	        }
	
	        if (!empty($traceItem['args'])) 
	        {
	            foreach ($traceItem['args'] as $argsItem) 
	            {
	                $traceHash['args'][] = \var_export($argsItem, true);
	            }
	        }
	
	        $exceptionHash['trace'][] = $traceHash;
	    }

        $this->logger->error(print_r($exceptionHash, true));
	}

	public final function fatalHandler()
	{
	    $error = error_get_last();
	    if (isset($error['type']))
	    {
	        switch ($error['type'])
	        {
	            case E_ERROR :
	            case E_PARSE :
	            case E_DEPRECATED:
	            case E_CORE_ERROR :
	            case E_COMPILE_ERROR :
	                $message = $error['message'];
	                $file = $error['file'];
	                $line = $error['line'];
	                $log = "$message ($file:$line)\nFSOF_Provider_Stack trace:\n";
	                $trace = debug_backtrace();
	                
	                foreach ($trace as $i => $t)
	                {
	                    if (!isset($t['file']))
	                    {
	                        $t['file'] = 'unknown';
	                    }
	                    if (!isset($t['line']))
	                    {
	                        $t['line'] = 0;
	                    }
	                    if (!isset($t['function']))
	                    {
	                        $t['function'] = 'unknown';
	                    }
	                    $log .= "#$i {$t['file']}({$t['line']}): ";
	                    if (isset($t['object']) && is_object($t['object']))
	                    {
	                        $log .= get_class($t['object']) . '->';
	                    }
	                    $log .= "{$t['function']}()\n";
	                }
	                
	                if (isset($_SERVER['REQUEST_URI']))
	                {
	                    $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
	                }

	                //在临死之前立刻将错误信息回传给consumer,避免consumer一直等待直到超时
	                $local = FSOFSystemUtil::getServiceIP();
	                $result = "error: {$this->processName}[{$local}]:".$message."in {$file}|{$line}";
	                $this->sendFatelMessage($result,false,true);
	        }
	    }
	}

	public function sendFatelMessage($msg,$businessErr,$frameErr)
	{
        $this->logger->error('fd:'.$this->cur_fd.'|processName:'.$this->processName. ' send '.json_encode($msg));

		$response = new DubboResponse();
		$response->setSn($this->request->getSn());
        if($frameErr){
            $response->setStatus(DubboResponse::SERVICE_ERROR);
            $response->setErrorMsg($msg);
        }
        if($businessErr){
            $response->setErrorMsg($msg);
        }
		$response->setResult($msg);
		$parser = new DubboParser();
		$send_data = $parser->packResponse($response);

		$ret = $this->sw->send($this->cur_fd, $send_data, $this->cur_from_id);
		if($ret == false)
		{
			$this->sw->close($this->cur_fd);
		}
	}
        
    //当前app是否提供了满足条件的服务
    public function serviceExist($serviceName, $group, $version)
    {
		if (empty($this->serverProviders))
		{
            $this->logger->error($serviceName."|".$group."|".$version." not in serverProviders");
			return false;
		}
		foreach ($this->serverProviders as $svrName => $svrProperty)
		{
			$serviceVersion = isset($svrProperty['version'])?$svrProperty['version']:FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT;
			if(($serviceName == $svrName) && ($version == $serviceVersion) && (($group == $svrProperty["group"]) ||
				($group == FSOFConstants::FSOF_SERVICE_GROUP_DEFAULT) || ($group == FSOFConstants::FSOF_SERVICE_GROUP_ANY)))
			{
				return true;
			}
		}
        $this->logger->error($serviceName."|".$group."|".$version." not in ".json_encode($this->serverProviders));
    	return false;
    }
    
    //获取app中满足条件的服务实例，一个app提供的所有服务都以单实例的形式存于内存中
    public function getServiceInstance($serviceName, $group, $version)
    {
        $keyName = null;
		$serInstance = null;

		foreach ($this->serverProviders as $svrName => $svrProperty)
		{
			$serviceVersion = isset($svrProperty['version'])?$svrProperty['version']:FSOFConstants::FSOF_SERVICE_VERSION_DEFAULT;
			if(($serviceName == $svrName) && ($version == $serviceVersion) && (($group == $svrProperty["group"]) ||
				($group == FSOFConstants::FSOF_SERVICE_GROUP_DEFAULT) || ($group == FSOFConstants::FSOF_SERVICE_GROUP_ANY)))
			{
				$keyName = $svrProperty['service'];
				break;
			}
		}
        
    	if(null != $keyName)
    	{
	    	//配置文件中服务名格式为：com.fenqile.example.calculate.Calculate，需要把.替换成\
	        $keyName = str_replace('.', '\\', $keyName);
	        $keyName = '\\'.$keyName;//前面加上\, 拼接成全限定类名
			$serInstance = $this->appContext->getInstance($keyName);
    	}

		return $serInstance;
    }

    public function isStateless()
    {
        return $this->appContext->isStateless();
    }
        
    public function getAppMonitor()
    {
    	return $this->sw->appMonitor;
    }

	public function getOverloadMonitor()
	{
		return $this->overloadMonitor;
	}

    public function getAppConfig()
    {
    	return $this->config;
    }

	public function getAppName()
	{
		return $this->processName;
	}
}