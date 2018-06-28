<?php
/**
 * Version: 1.0.0
 * Description: 示例程序,模拟server工程下的各个service
 */

require_once('log4php/Logger.php');
Logger::configure(dirname(dirname(__FILE__)).'/config/log4php.xml');
date_default_timezone_set('PRC');

class DemoServiceImpl
{
	public function sayHello($name)
	{
        \Logger::getLogger(__CLASS__)->info("Hello $name");
        return "Hello $name";
	}
}
