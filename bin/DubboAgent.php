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

define("VENDOR_DIR", __DIR__ . '/../../../');

include VENDOR_DIR . "/autoload.php";

use Dubbo\Agent\Bootstrap;

class DubboAgent
{
    public function checkEnvironment()
    {
        if (php_sapi_name() != 'cli') {
            exit("Must be run in php cli mode! \n");
        }
        $req_extension = '';
        if (!extension_loaded('swoole')) {
            $req_extension .= 'swoole ';
        }
        if (!extension_loaded('yaml')) {
            $req_extension .= ' yaml';
        }
        if ($req_extension) {
            exit("Need {$req_extension} extension! \n");
        }
    }

    public function getOpt()
    {
        if (false) {
            help:
            $help = <<<HELP
Usage:
    php DubboManager.php [-h] [-y filename]
Options:
    -y filename            : This is a agent config file
    -h                     : Display this help message \n
HELP;
            exit($help);
        }
        $options = getopt("y:h");
        if (isset($options['h'])) {
            goto help;
        }
        $y = $options['y'] ?? '';
        if (is_file($y)) {
            try{
                $bootstrap = new Bootstrap($y);
                $bootstrap->run();
            }catch (\Exception $exception){
                exit($exception->getMessage()."\n");
            }
            return;
        }
        goto help;
    }

    public static function run()
    {
        $instance = new self();
        $instance->checkEnvironment();
        $instance->getOpt();
    }
}

DubboAgent::run();

