<?php
/*
  +----------------------------------------------------------------------+
  | dubbo-php-framework                                                        |
  +----------------------------------------------------------------------+
  | This source file is subject to version 2.0 of the Apache license,    |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.apache.org/licenses/LICENSE-2.0.html                      |
  +----------------------------------------------------------------------+
  | Author: Jinxi Wang  <crazyxman01@gmail.com>                              |
  +----------------------------------------------------------------------+
*/

namespace Dubbo\Common\Logger;

use Dubbo\Common\YMLParser;
use Dubbo\Common\DubboException;

class LoggerSimple implements LoggerInterface
{
    const DEBUG = 1;
    const INFO = 2;
    const WARN = 3;
    const ERROR = 4;

    private $_levelTextMap = [
        'DEBUG' => self::DEBUG,
        'INFO' => self::INFO,
        'WARN' => self::WARN,
        'ERROR' => self::ERROR,
    ];
    private $_levelNumMap = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::WARN => 'WARN',
        self::ERROR => 'ERROR',
    ];
    private $_levelNum = self::INFO;
    private $_logDir = '.';
    private $_filename;

    public function __construct(YMLParser $ymlParser)
    {
        $logDir = $ymlParser->getApplicationLogDir();
        if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
            throw new DubboException("Create log directory '{$logDir}' fail");
        }
        if (!is_writable($logDir)) {
            throw new DubboException("The log directory '{$logDir}' is not writable");
        }
        $this->_logDir = $logDir;
        $this->_levelNum = $this->_levelTextMap[$ymlParser->getApplicationLoggerLevel()] ?? self::INFO;
        $this->_filename = $ymlParser->getApplicationName();
    }

    private function write($log_level, $text, $params)
    {
        static $fp, $date;
        if ($log_level >= $this->_levelNum) {
            $now_date = date('Y-m-d');
            if (is_null($date) || $now_date != $date || !is_resource($fp)) {
                $filename = $this->_filename . '_' . $now_date;
                $logfile = $this->_logDir . '/' . $filename . '.log';
                $date = $now_date;
                $fp = fopen($logfile, 'a+');
                fseek($fp, 0, SEEK_END);
            }
            if (is_resource($fp)) {
                $extra = '';
                foreach ($params as $value) {
                    $type = gettype($value);
                    switch ($type) {
                        case "object":
                            if ($value instanceof \Exception) {
                                $extraSub = str_replace("\n", " ", (string)$value);
                            } else {
                                $extraSub = json_encode(get_object_vars($value));
                            }
                            $extra .= $type . '(' . $extraSub . ')';
                            break;
                        case 'array':
                            $extra .= $type . '(' . json_encode($value) . ')';
                            break;
                        default:
                            $extra .= $type . "({$value})";
                    }
                    $extra .= ',';
                }
                $message = 'desc:' . $text . ',extra:' . $extra;
                $text = sprintf("[%s][%s]%s\n", date('Y-m-d H:i:s'), $this->_levelNumMap[$log_level], $message);
                if (flock($fp, LOCK_EX)) {
                    if (fwrite($fp, $text) === false) {
                        echo 'Failed writing to logfile: ' . $logfile . "\n";
                    }
                    flock($fp, LOCK_UN);
                } else {
                    echo 'Failed locking file to writing logfile: ' . $logfile . "\n";
                }
            }
        }
    }

    public function debug(string $text, ...$params)
    {
        $this->write(self::DEBUG, $text, $params);
    }

    public function info(string $text, ...$params)
    {
        $this->write(self::INFO, $text, $params);
    }

    public function warn(string $text, ...$params)
    {
        $this->write(self::WARN, $text, $params);
    }

    public function error(string $text, ...$params)
    {
        $this->write(self::ERROR, $text, $params);
    }
}