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

namespace Dubbo\Agent\Registry\Client;

use Dubbo\Agent\YMLParser;
use Swoole\Table;

class BaseClient implements ClientInterface
{
    protected $_table;

    protected $_ymlParser;

    public function __construct(YMLParser $ymlParser)
    {
        $this->_table = new Table($ymlParser->getSwooleTableSize(1024));
        $this->_table->column('provider', Table::TYPE_STRING, $ymlParser->getSwooleTableColumnSize(10240));
        $this->_table->create();
        $this->_ymlParser = $ymlParser;
    }

    public function get($serviceName)
    {
        $provider = $this->_table->get($serviceName, 'provider') ?: '';
        return json_decode($provider, true);
    }

}