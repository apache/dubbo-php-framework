<?php
$APP_SRC_PATH = __DIR__;
$fsofApiPath = dirname(dirname(dirname($APP_SRC_PATH))).DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'FSOFApi.php';
require_once($fsofApiPath);
FSOFApi::configure('demo-consumer', $APP_SRC_PATH);

require_once('log4php/Logger.php');
Logger::configure(dirname(dirname(__FILE__)).'/config/log4php.xml');
date_default_timezone_set('PRC');

//php框架调用php提供的服务
$service = 'com.fenqile.example.DemoService';
$proxy = FSOFApi::newProxy($service, 3);
$ret = $proxy->invoke("sayHello","zhangsan");
echo "ret:$ret".PHP_EOL;
