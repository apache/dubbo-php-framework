<?php

namespace com\fenqile\fsof\consumer;

use com\fenqile\fsof\consumer\ConsumerException;
use Icecave\Collections\Collection;

class Type
{
    /*
    const SHORT = 1;
    const INT = 2;
    const INTEGER = 2;
    const LONG = 3;
    const FLOAT = 4;
    const DOUBLE = 5;
    const STRING = 6;
    const BOOL = 7;
    const BOOLEAN = 7;
    const MAP = 8;
    */
    const ARRAYLIST = 9;
    const DEFAULT_TYPE = 10;

    const adapter = [
        /*
        Type::SHORT => 'S',
        Type::INT => 'I',
        Type::LONG => 'J',
        Type::FLOAT => 'F',
        Type::DOUBLE => 'D',
        Type::BOOLEAN => 'Z',
        Type::STRING => 'Ljava/lang/String;',
        Type::MAP => 'Ljava/util/Map;',
        */
        Type::ARRAYLIST => 'Ljava/util/ArrayList;',
        Type::DEFAULT_TYPE => 'Ljava/lang/Object;'
    ];

    private function __construct()
    {
    }

    /**
     *
     * @param integer $value
     * @return UniversalObject
     */
    public static function object($class, $properties)
    {
        $typeObj = new self();
        $typeObj->className = $class;
        $std = new \stdClass;
        foreach ($properties as $key => $value) {
            $std->$key = $value;
        }
        $typeObj->object = $std;
        return $typeObj;
    }

    /**
     *
     * @param mixed $arg
     * @return string
     * @throws ConsumerException
     */
    public static function argToType($arg)
    {
        $type = gettype($arg);
        switch ($type) {
            case 'integer':
            case 'boolean':
            case 'double':
            case 'string':
            case 'NULL':
                return self::adapter[Type::DEFAULT_TYPE];
            case 'array':
                if (Collection::isSequential($arg)) {
                    return self::adapter[Type::ARRAYLIST];
                } else {
                    return self::adapter[Type::DEFAULT_TYPE];
                }
            case 'object':
                if ($arg instanceof Type) {
                    $className = $arg->className;
                } else {
                    $className = get_class($arg);
                }
                return 'L' . str_replace(['.', '\\'], '/', $className) . ';';
            default:
                throw new ConsumerException("Handler for type {$type} not implemented");
        }
    }
    /**
     *
     * @param int $arg
     * @return int
     */

    /*
    private static function numToType($value)
    {
        if (-32768 <= $value && $value <= 32767) {
            return Type::SHORT;
        } elseif (-2147483648 <= $value && $value <= 2147483647) {
            return Type::INT;
        }
        return Type::LONG;
    }
    */

}