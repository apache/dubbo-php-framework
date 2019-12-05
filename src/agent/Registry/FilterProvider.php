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

namespace Dubbo\Agent\Registry;

use Dubbo\Agent\Registry\Client\ClientInterface;

class FilterProvider
{
    private $_registry;

    public function __construct(ClientInterface $registry)
    {
        $this->_registry = $registry;
    }

    public function find_provider($data)
    {
        $result = [];
        $where = $this->filterWhere($data);
        if (!$where['service']) {
            goto _return_result;
        }
        $sw_providers = $this->_registry->get($where['service'], 'providers');
        if (!$sw_providers) {
            goto _return_result;
        }
        if (isset($where['all'])) {
            $result = $sw_providers;
            goto _return_result;
        }
        foreach ($sw_providers as $provider) {
            $provider_info = parse_url(urldecode($provider));
            $query = null;
            parse_str($provider_info['query'], $query);
            $version = $query['version'] ?? '';
            $group = $query['group'] ?? '';
            foreach ($where as $value) {
                if (in_array($value['version'], ['-', $version]) && in_array($value['group'], ['-', $group])) {
                    $result[] = $provider;
                }
            }
        }
        _return_result:
        return json_encode($result);
    }

    private function filterWhere($data)
    {
        $data = trim($data, " \t\n\r\0\x0B|");
        $data_info = explode('|', $data);
        $where['service'] = $data_info[0] ?? '';
        if (!$where['service']) {
            goto _return_result;
        }
        $filterData = array_slice($data_info, 1);
        if (!$filterData) {
            $where['all'] = 1;
            goto _return_result;
        }
        foreach ($filterData as $val) {
            list($group, $version) = explode(':', $val);
            if ($group == '-' && $version == '-') {
                $where['all'] = 1;
                break;
            } else {
                $where['query'][] = ['group' => $group, 'version' => $version];
            }
        }
        if (!isset($where['query'])) {
            $where['all'] = 1;
        } else {
            $where['query'] = array_unique($where['query']);
        }
        _return_result:
        return $where;
    }
}