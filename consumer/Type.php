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

    const adapter = [
        Type::SHORT => 'S',
        Type::INT => 'I',
        Type::LONG => 'J',
        Type::FLOAT => 'F',
        Type::DOUBLE => 'D',
        Type::BOOLEAN => 'Z',
        Type::STRING => 'Ljava/lang/String;'
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
            $std->$key = ($value instanceof Type) ? $value->value : $value;
        }
        $std_wrap = new \stdClass();
        $std_wrap->object = $std;
        $std_wrap->class = 'L'.str_replace('.', '/', $class).';';
        return $std_wrap;
    }
}