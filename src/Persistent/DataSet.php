<?php

namespace Arrow\ORM\Persistent;

/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:38
 * To change this template use File | Settings | File Templates.
 */
class DataSet implements \Iterator, \ArrayAccess, \Countable, \Serializable, \JsonSerializable
{

    const AS_OBJECT = 0;
    const AS_ARRAY = 1;

    /**
     * Dataset class
     *
     * @var String
     */
    protected $class;

    /**
     * @var \PDOStatement
     */
    protected $result;

    private $cursor = -1;
    private $valid = true;
    protected $mappedArray = array();
    protected $count = 0;
    protected $simple = false;
    protected $criteria = false;
    private $cacheEnabled = true;

    public function __construct($class, $result, $criteria, $simple = false)
    {
        $this->class = $class;
        $this->result = $result;
        if ($this->result instanceof \PDOStatement) {
            $this->count = $result->rowCount();
        } else {
            $this->count = count($result);
        }
        $this->simple = $simple;
        $this->criteria = $criteria;

        //20

        //13


    }

    /**
     * Returns query used to produce query string
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->result->queryString;
    }

    /**
     * @param int $fetchType
     *
     * @return PersistentObject|array|null
     */
    public function fetch($fetchType = self::AS_OBJECT)
    {
        $this->cursor++;

        if (empty ($this->mappedArray[$this->cursor])) {
            $row = $this->getNextRow($fetchType);
            if (empty ($row)) {
                $this->valid = false;
                return null;
            } else {
                if ($this->cacheEnabled) {
                    $this->mappedArray[$this->cursor] = $row;
                }
                return $row;
            }
        } else {
            return $this->mappedArray[$this->cursor];
        }
    }

    protected function getNextRow($fetchType)
    {
        if ($this->result instanceof \PDOStatement) {
            $row = $this->result->fetch(\PDO::FETCH_ASSOC);
        } else {
            $row = current($this->result);
            if (!empty ($row)) {
                next($this->result);
            }
        }

        if (empty ($row)) {
            return false;
        }

        //$row = $this->valuesFilter($row);

        if ($fetchType == self::AS_ARRAY || $this->simple) {
            return $row;
        }

        return $this->initiateObject($row);
    }

    /*    protected function valuesFilter($row)
        {
            array_walk(
                $row, function (&$val) {
                    $val = str_replace("NULL", null, $val);
                }
            );
            return $row;
        }*/

    /**
     * @param $data
     *
     * @return PersistentObject
     */
    protected function initiateObject($data)
    {
        /** @var $object PersistentObject */
        $object = new $this->class();
        $object->fastDataLoad($data);
        $object->afterObjectLoad($object);
        return $object;
    }

    public function setCacheEnabled($cacheEnabled)
    {
        $this->cacheEnabled = $cacheEnabled;
    }

    public function getCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setSimpleFlag($simple)
    {
        if (!empty($this->mappedArray)) {
            throw new Exception("Can't set simple flag after list initiation", 0);
        }
        $this->simple = $simple;
    }

    public function isSimple()
    {
        return $this->simple;
    }

    public function collectFieldsAsArray($field)
    {
        $tmp = array();
        while ($el = $this->fetch()) {
            $tmp[] = $el[$field];
        }
        return $tmp;
    }

    public function collectKeys()
    {
        $class = $this->class;
        return $this->collectFieldsAsArray($class::getPKField());
    }


    public function toArray($fetchType = self::AS_OBJECT)
    {
        while ($row = $this->fetch($fetchType)) {
        }
        return $this->mappedArray;
    }


    public function toPureArray()
    {
        $result = $this->toArray(self::AS_ARRAY);
        foreach($result as &$row){
            if(!is_array($row)){
                $row = $row->jsonSerialize();
            }
        }
        return $result;
    }

    public function __toString()
    {
        return "ORM Dataset, class:" . $this->class . ", objects: " . $this->count();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     * </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     *       The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        if ($offset > $this->count - 1) {
            return false;
        }
        return true;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     *                      The offset to retrieve.
     * </p>
     *
     * @return PersistentObject|Array
     */
    public function offsetGet($offset)
    {
        if (!isset($this->mappedArray[$offset])) {
            while ($this->cursor < $offset) {
                $this->next();
            }
        }
        return $this->mappedArray[$offset];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     *                      The value to set.
     * </p>
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new Exception("Not implementet");
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     * </p>
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        throw new Exception("Not implementet");
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     *
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     *       The return value is cast to an integer.
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     *
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        if ($this->cursor == -1) {
            $this->fetch();
        }


        return $this->mappedArray[$this->cursor];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     *
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->fetch();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     *
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->cursor;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     *
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     *       Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->cursor < $this->count && $this->count;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     *
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->cursor = -1;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     *
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $this->toArray();
        return serialize($this->mappedArray);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     *
     * @link http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     *                           The string representation of the object.
     * </p>
     *
     * @return mixed the original value unserialized.
     */
    public function unserialize($serialized)
    {
        $this->mappedArray = unserialize($serialized);
        $this->cursor = -1;
    }


    public function delete()
    {
        foreach ($this as $el) {
            $el->delete();
        }


    }


    public function map(callable $callback)
    {
        $tmp = [];
        foreach ($this as $el) {
            $tmp[] = $callback($el);
        }
        return $tmp;
    }


    public function reduce(callable $callback, $carry = [])
    {
        foreach ($this as $el) {
            $carry = $callback($carry, $el);
        }
        return $carry;

    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return $this->toArray();
    }


}
