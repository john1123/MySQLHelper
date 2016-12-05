<?php

class MySQLHelper
{
    const VALUE_TYPE_EXACT = 'Exact';
    const VALUE_TYPE_UNKNOWN = 'Unknown';
    const VALUE_TYPE_ARRAY = 'Array';
    const VALUE_TYPE_INT = 'Integer';
    const VALUE_TYPE_NUMBER = 'Float';
    const VALUE_TYPE_STRING = 'String';
    const VALUE_TYPE_NULL = 'Null';
    const VALUE_TYPE_BOOLEAN = 'Boolean';
    const VALUE_TYPE_DATETIME = 'Datetime';

    /**
     * @deprecated
     * @param $strSource
     * @return string
     */
    public static function prepareValue($strSource)
    {
        $result = $strSource;
        if (is_null($strSource)) {
            $result = 'NULL';
        } elseif (is_string($strSource)) {
            $result = "'" . str_replace("'", "''", $strSource) . "'";
        }
        return $result;
    }

    public static function value($value, $valueType = self::VALUE_TYPE_UNKNOWN, $maxLen = -1)
    {
        // value can be passed with its type as an array: array('type' => 'String', 'value' => 'myValue')
        if (is_array($value) && array_key_exists('value', $value) && array_key_exists('type', $value)) {
            $valueType = $value['type'];
            $value = $value['value'];
        }
        $result = $value;
        switch ($valueType) {
            case self::VALUE_TYPE_BOOLEAN:
                return self::parameterBoolean($result);
            case self::VALUE_TYPE_INT:
                return (int)$result;
            case self::VALUE_TYPE_NUMBER:
                return (float)$result;
            case self::VALUE_TYPE_NULL:
                return 'null';
            case self::VALUE_TYPE_EXACT:
                return $result;
            case self::VALUE_TYPE_DATETIME:
                return self::parameterDatetime($result);
            case self::VALUE_TYPE_ARRAY:
                return self::parameterArray($result);
            case self::VALUE_TYPE_STRING:
                return self::parameterString($result, $maxLen);
            case self::VALUE_TYPE_UNKNOWN:
            default:
                if (is_null($value) || @strtolower($value) == 'null') {
                    return 'null';
                } elseif (is_bool($result) || @strtolower($result) == 'true' || @strtolower($result) == 'false') {
                    return boolval($result) ? 'true' : 'false';
                } elseif (is_array($value)) {
                    return self::parameterArray($result);
                } elseif (is_int($result) || preg_match('/^[-+]?\d+$/', $value)) {
                    if (preg_match('/^[-+]?0+/', $value)) {
                        return self::parameterString($result, $maxLen);
                    }
                    return (int)$result;
                } elseif (is_float($result) || preg_match('/^[-+]?(?:\b[0-9]+(?:\.[0-9]*)?|\.[0-9]+\b)(?:[eE][-+]?[0-9]+\b)?$/', $result)) {
                    if (preg_match('/^[-+]?0+/', $value)) {
                        return self::parameterString($result, $maxLen);
                    }
                    return (float)$result;
                } elseif (is_string($value)) {
                    return self::parameterString($result, $maxLen);
                }
        }
        throw new Exception('Unknown parameter type: ' . print_r($result, true));
    }

    public static function parameterString($value, $maxLen = -1)
    {
        $result = $value;
        $result = str_replace("'", "''", $result);
        if ($maxLen > 0) {
            $result = substr($result, 0, $maxLen);
        }
        return "'" . $result . "'";
    }

    public static function parameterDatetime($value)
    {
        $time = strtotime($value);
        return date('Y-m-d H:i:s', $time);
    }

    public static function parameterArray($value = array())
    {
        $out = array();
        foreach ($value as $element) {
            $out = self::value($element);
        }
        return implode(", ", $out);
    }

    public static function parameterBoolean($value)
    {
        $out = (bool)$value;
        return $out ? 'true' : 'false';
    }

    public static function createExactParameter($value)
    {
        return array('type' => self::VALUE_TYPE_EXACT, 'value' => $value);
    }

    public static function prepareQuery($query, $values = array())
    {
        $result = $query;
        krsort($values);
        foreach ($values as $placeholder => $value) {
            $placeholder = ':' . $placeholder;
            $result = str_replace($placeholder, self::value($value), $result);
        }
        return $result;
    }

    public static function prepareQueryUpdate($table, $values = array(), $where = '')
    {
        $query = 'UPDATE `' . $table . '` SET ';
        foreach ($values as $field => $value) {
            $query .= '`' . $field . '` = ' . self::value($value) . ', ';
        }
        $query = rtrim($query, ', ');
        $query .= ' WHERE ' . $where;
        return $query;
    }

    public static function prepareQueryInsert($table, $values = array())
    {
        $arrFields = $arrValues = array();
        foreach ($values as $field => $value) {
            $arrFields[]  =  '`' . $field . '`';
            $arrValues[]  = self::value($value);
        }
        $query = 'INSERT INTO `' . $table . '` (' . implode(', ', $arrFields) . ') VALUES (' . implode(', ', $arrValues) . ')';
        return $query;
    }

    public static function prepareQueryInsertOrUpdate($table, $values = array(), $updateValues)
    {
        $arrFields = $arrValues = array();
        foreach ($values as $field => $value) {
            $field = '`' . $field . '`';
            $value = self::value($value);
            $arrFields[]  =  $field;
            $arrValues[]  = $value;
        }
        $update = '';
        if (count($updateValues) > 0) {
            foreach ($updateValues as $field => $value) {
                $update = $field . ' = ' . $value . ', ';
            }
        } else {
            for ($i = 0; $i < count($arrFields); $i++) {
                $update .= $arrFields[$i] . ' = ' . $arrValues[$i] . ', ';
            }
        }
        $update = rtrim($update, ', ');
        $query = 'INSERT INTO `' . $table . '` (' . implode(', ', $arrFields) . ') VALUES (' . implode(', ', $arrValues) . ') ON DUPLICATE KEY UPDATE ' . $update;
        return $query;
    }

    public static function uglifyQuery($query)
    {
        return preg_replace('/[ \t]+/', ' ', preg_replace('/[\r\n]+/', " ", $query));
    }
}