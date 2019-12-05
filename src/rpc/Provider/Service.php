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

namespace Dubbo\Provider;

use Dubbo\Common\DubboException;
use Dubbo\Common\Protocol\Dubbo\DubboProtocol;
use Dubbo\Common\Protocol\Dubbo\DubboResponse;
use Doctrine\Common\Annotations\AnnotationReader;
use Dubbo\Common\Protocol\Dubbo\DubboUrl;
use Dubbo\Common\YMLParser;
use Dubbo\Provider\Annotations\DubboClassAnnotation;
use Dubbo\Provider\Annotations\DubboMethodAnnotation;

class Service
{
    private $_ymlParser;

    private $_serviceTable;

    public function __construct(YMLParser $ymlparser)
    {
        $this->_ymlParser = $ymlparser;
    }

    public function load()
    {
        $serviceClass = $this->loadServiceClass();
        $reader = new AnnotationReader();
        $reflectionClass = new \ReflectionClass(DubboClassAnnotation::class);
        $reader->getClassAnnotation($reflectionClass, DubboClassAnnotation::class);
        $reflectionMethod = new \ReflectionClass(DubboMethodAnnotation::class);
        $reader->getClassAnnotation($reflectionMethod, DubboMethodAnnotation::class);

        $serviceTable = [];
        foreach ($serviceClass as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $reflectionClass = new \ReflectionClass($class);
            $classAnnotation = $reader->getClassAnnotation($reflectionClass, DubboClassAnnotation::class);
            if (!$classAnnotation) {
                continue;
            }
            if ($classAnnotation->serviceAlias) {
                $key = $classAnnotation->serviceAlias;
            } else {
                $key = str_replace('\\', '.', strtolower($class));
            }
            $methods = [];
            $reflectionMethods = $reflectionClass->getMethods();
            $isFoundEntrance = false;
            foreach ($reflectionMethods as $method) {
                $methodName = $method->getName();
                if ($methodName == 'dubboIngress') {
                    $isStatic = $method->isStatic();
                    if (!$isStatic) {
                        throw new DubboException(" The '{$methodName}' method in the '{$class}' class is not static");
                    }
                    $isFoundEntrance = true;
                    continue;
                }
                $methodAnnotation = $reader->getMethodAnnotation($method, DubboMethodAnnotation::class);
                if (!$methodAnnotation) {
                    continue;
                }
                $methods[] = $methodName;
            }
            if (!$isFoundEntrance) {
                throw new DubboException("No entry function 'dubboIngress' found in the '{$class}' class");
            }
            if ($methods) {
                $dubboUrl = new DubboUrl;
                $dubboUrl->setHost($this->_ymlParser->getProtocolHost());
                $dubboUrl->setPort($this->_ymlParser->getProtocolPort());
                $dubboUrl->setDubboVersion($this->_ymlParser->getDubboVersion());
                $dubboUrl->setGroup(is_null($classAnnotation->group) ? $this->_ymlParser->getProviderGroup() : $classAnnotation->group);
                $dubboUrl->setVersion(is_null($classAnnotation->version) ? $this->_ymlParser->getProviderVersion() : $classAnnotation->version);
                $dubboUrl->setService($key);
                $dubboUrl->setInterface($key);
                $dubboUrl->setMethods(implode(',', $methods));
                $dubboUrl->setApplication($this->_ymlParser->getApplicationName());
                $dubboUrl->setSerialization($this->_ymlParser->getProtocolSerialization());
                $serviceTable[$key]['class'] = $class;
                $serviceTable[$key]['dubboUrl'] = $dubboUrl;
            }
        }
        $this->_serviceTable = $serviceTable;
    }

    private function getNamespacePath()
    {
        $namespace = trim($this->_ymlParser->getServiceNamespace(), '\\');
        $autoloadPsr4 = include VENDOR_DIR . '/composer/autoload_psr4.php';

        $namespacePrefix = $namespace;
        do {
            $paths = $autoloadPsr4[$namespacePrefix . '\\'] ?? [];
            if ($paths) {
                break;
            }
            $pos = strrpos($namespacePrefix, '\\');
            $namespacePrefix = substr($namespacePrefix, 0, $pos);
        } while ($pos);
        if (!$paths) {
            throw new DubboException("'{$namespace}' namespace does not exist");
        }
        $pathSuffix = substr($namespace, strlen($namespacePrefix));
        $pathSuffix = str_replace('\\', '/', $pathSuffix);
        return [$namespacePrefix, $pathSuffix, $paths];
    }

    public function loadDubboBootstrap()
    {
        list($namespacePrefix, $pathSuffix, $paths) = $this->getNamespacePath();
        $isFoundBootstrap = false;
        foreach ($paths as $path) {
            $dubboBootstrapFile = $path . $pathSuffix . '/DubboBootstrap.php';
            if (is_file($dubboBootstrapFile)) {
                include $dubboBootstrapFile;
                $isFoundBootstrap = true;
            }
        }
        if (!$isFoundBootstrap) {
            throw new DubboException("Initialization file not found: '{$dubboBootstrapFile}'");
        }
    }

    private function loadServiceClass()
    {
        list($namespacePrefix, $pathSuffix, $paths) = $this->getNamespacePath();
        $serviceClass = [];
        foreach ($paths as $path) {
            $this->_recursiveFound($path . $pathSuffix, strlen($path), $namespacePrefix, $serviceClass);
        }
        if (!$serviceClass) {
            throw new DubboException("No service available!");
        }
        return $serviceClass;
    }

    private function _recursiveFound($path, $len, $namespacePrefix, &$result)
    {
        $lists = glob("{$path}/*");
        foreach ($lists as $val) {
            if (is_dir($val)) {
                $this->_recursiveFound($val, $len, $namespacePrefix, $result);
            }
            if (is_file($val) && (substr($val, -4) == '.php')) {
                $val = substr($val, 0, -4);
                $item = substr($val, $len);
                $class = $namespacePrefix . str_replace('/', '\\', $item);
                if (class_exists($class, true)) {
                    $result[] = $class;
                }
            }
        }
    }

    public function invoke(DubboProtocol $protocol, $decoder, $server, $fd, $reactor_id)
    {
        $serviceName = $decoder->getServiceName();
        $service = $this->_serviceTable[$serviceName] ?? '';
        if (!$service) {
            throw new DubboException("Can't find '{$serviceName}' service");
        }
        $class = $service['class'];
        $dubboUrl = $service['dubboUrl'];
        $method = $decoder->getMethod();
        if (!in_array($method, explode(',', $dubboUrl->getMethods())) || !method_exists($class, $method)) {
            throw new DubboException("Can't find '{$method}' method");
        }
        $version = $decoder->getServiceVersion();
        if ($version != '0.0.0' && $version != $dubboUrl->getVersion()) {
            throw new DubboException("Can't find '{$version}' version service");
        }
        $attachments = $decoder->getAttachments();
        if ($dubboUrl->getGroup() != ($attachments['group'] ?? '')) {
            throw new DubboException("Can't find '{$attachments['group']}' group service");
        }
        $result = call_user_func_array([$class, 'dubboIngress'], [$method, $decoder->getArguments(), $server, $fd, $reactor_id]);
        $protocol->setStatus(DubboResponse::STATUS_OK);
        $protocol->setVariablePartType(DubboResponse::RESPONSE_VALUE);
        return $protocol->packResponse($result);
    }

    public function returnHeartBeat(DubboProtocol $protocol)
    {
        $protocol->setStatus(DubboResponse::STATUS_OK);
        $protocol->setVariablePartType(DubboResponse::RESPONSE_VALUE);
        return $protocol->packResponse('');
    }

    public function returnException(DubboProtocol $protocol, $message)
    {
        $protocol->setStatus(DubboResponse::SERVICE_ERROR);
        $protocol->setVariablePartType(DubboResponse::RESPONSE_WITH_EXCEPTION);
        return $protocol->packResponse($message);
    }


    public function getServiceTable()
    {
        return $this->_serviceTable;
    }

}