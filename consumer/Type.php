<?php

namespace com\fenqile\fsof\consumer;

class Type
{
    const SHORT = 1;
    const INT = 2;
    const INTEGER = 2;
    const LONG = 3;
    const FLOAT = 4;
    const DOUBLE = 5;
    const STRING = 6;
    const BOOL = 7;
    const BOOLEAN = 7;
    const ARRAYLIST = 8;
    const MAP = 9;

    const adapter = [
        Type::SHORT => 'S',
        Type::INT => 'I',
        Type::LONG => 'J',
        Type::FLOAT => 'F',
        Type::DOUBLE => 'D',
        Type::BOOLEAN => 'Z',
        Type::STRING => 'Ljava/lang/String;',
        Type::ARRAYLIST => 'Ljava/util/ArrayList;',
        Type::MAP => 'Ljava/util/Map;'
    ];

    public function __construct($type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * Short type
     *
     * @param  integer $value
     * @return Type
     */
    public static function short($value)
    {
        return new self(self::SHORT, $value);
    }

    /**
     * Int type
     *
     * @param  integer $value
     * @return Type
     */
    public static function int($value)
    {
        return new self(self::INT, $value);
    }

    /**
     * Integer type
     *
     * @param  integer $value
     * @return Type
     */
    public static function integer($value)
    {
        return new self(self::INTEGER, $value);
    }

    /**
     * Long type
     *
     * @param  integer $value
     * @return Type
     */
    public static function long($value)
    {
        return new self(self::LONG, $value);
    }

    /**
     * Float type
     *
     * @param  integer $value
     * @return Type
     */
    public static function float($value)
    {
        return new self(self::FLOAT, $value);
    }

    /**
     * Double type
     *
     * @param  integer $value
     * @return Type
     */
    public static function double($value)
    {
        return new self(self::DOUBLE, $value);
    }

    /**
     * String type
     *
     * @param  string $value
     * @return Type
     */
    public static function string($value)
    {
        return new self(self::STRING, $value);
    }

    /**
     * Bool type
     *
     * @param  boolean $value
     * @return Type
     */
    public static function bool($value)
    {
        return new self(self::BOOL, $value);
    }

    /**
     * Boolean type
     *
     * @param  boolean $value
     * @return Type
     */
    public static function boolean($value)
    {
        return new self(self::BOOLEAN, $value);
    }

    /**
     * Arraylist type
     *
     * @param  arraylist $value
     * @return Type
     */
    public static function arrayList($value)
    {
        return new self(self::ARRAYLIST, $value);
    }

    /**
     * Map type
     *
     * @param  map $value
     * @return Type
     */
    public static function map($value)
    {
        return new self(self::MAP, $value);
    }

    /**
     * Object type
     *
     * @param  integer $value
     * @return UniversalObject
     */
    public static function object($class, $properties)
    {
        $std = new \stdClass;
        foreach ($properties as $key => $value)
        {
            $std->$key = ($value instanceof Type) ? self::typeTosafe($value) : $value;
        }
        $std_wrap = new \stdClass();
        $std_wrap->object = $std;
        $std_wrap->class = 'L'.str_replace('.', '/', $class).';';
        return $std_wrap;
    }

    public static function getDataForSafed($args)
    {
        foreach ($args as &$value)
        {
            if ($value instanceof \stdClass) {
                $value = $value->object;
            } elseif ($value instanceof Type) {
                $value = self::typeTosafe($value);
            }
        }
        return $args;
    }

    public static function  typeTosafe(Type $type)
    {
        switch ($type->type){
            case Type::SHORT:
            case Type::INT:
            case Type::LONG:
                $value = (int)$type->value;
                break;
            case Type::FLOAT:
            case Type::DOUBLE:
                $value = (float)$type->value;
                break;
            case Type::BOOLEAN:
                $value = (bool)$type->value;
                break;
            case Type::ARRAYLIST:
            case Type::MAP:
                $value = (array)$type->value;
                break;
            case Type::STRING:
            default:
                $value = (string)$type->value;
                break;
        }
        return $value;
    }
}