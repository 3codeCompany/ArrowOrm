<?php

namespace Arrow\ORM\Persistent;

use Arrow\ORM\Exception;
use Arrow\ORM\Extensions\BaseTracker;
use Arrow\ORM\Extensions\TreeNode;

/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:38
 * To change this template use File | Settings | File Templates.
 */
class PersistentObject extends BaseTracker implements \ArrayAccess, \JsonSerializable
{

    protected $virtualFields = [];
    protected $data = [];
    protected $parameters = [];
    protected $joinedData = [];
    protected $joinedDataMode = null;

    protected $changedData = [];
    protected $modified = false;


    public function fastDataLoad($data)
    {
        $this->data = $data;
    }

    /**
     * @param       $data
     * @param array $parameters
     */
    public function __construct($data = null, $parameters = null)
    {
        if ($data !== null) {
            $this->setValues($data);
        }
        if ($parameters !== null) {
            $this->setParameters($parameters);
        }
    }

    /**
     * @param $data array
     * @return static
     */
    public static function create($data)
    {
        $class = static::$class;
        $obj = new $class($data);
        $obj->save(true);
        $obj->modified = true;
        return $obj;
    }

    public static function createSet($data)
    {
        foreach ($data as $row) {
            static::create($row);
        }
    }

    /**
     * @return Criteria
     * @throws Exception
     */
    public static function get()
    {
        return Criteria::query(static::getClass());
    }

    /**
     * @param $data array
     * @return static
     */
    public static function createIfNotExists($data)
    {
        $criteria = Criteria::query(static::$class);
        foreach ($data as $field => $value) {
            $criteria->c($field, $value);
        }

        $obj = $criteria->findFirst();

        if ($obj == null) {
            $obj = self::create($data);
        }

        return $obj;
    }

    public static function exists($data)
    {
        $criteria = Criteria::query(static::$class);
        foreach ($data as $field => $value) {
            $criteria->c($field, $value);
        }

        $obj = $criteria->findFirst();
        return $obj ? $obj : false;
    }

    public static function findEqual($data)
    {
        $criteria = Criteria::query(static::$class);
        foreach ($data as $field => $value) {
            $criteria->c($field, $value);
        }

        return $criteria->findFirst();
    }


    public function getPKey()
    {
        return isset($this->data[static::$PKeyField]) ? $this->data[static::$PKeyField] : null;
    }

    public static function getPKeyField()
    {
        return static::$PKeyField;
    }

    public function getValue($field)
    {
        if (!array_key_exists($field, $this->data)) {
            if (!in_array($field, static::$fields)) {
                if ($this->joinedDataMode == JoinedDataSet::MODE_FLATTEN) {
                    if (strpos($field, ":") !== false) {
                        foreach ($this->joinedData as $key => $val) {
                            //todo przemyslec strategie dopasowana pola do bazy aby ograniczyc dlugosc indexow ktore trzeba podac
                            if (strpos($key, $field) !== false) {
                                return $val;
                            }
                        }
                    }
                }

                //todo for join changes
                if (array_key_exists($field, $this->data)) {
                    return $this->data[$field];
                }


                throw new Exception(array("msg" => "[PersistentObject] Field not exists " . static::$class . "['{$field}']", "class" => get_class($this), "field" => $field, 'fields' => static::getFields()));
            }

            if ($field == self::getPKField()) {
                return null;
            }
            if ($this->getPKey() == null) {
                return null;
            }
            throw new Exception(array("msg" => "[PersistentObject] Field not delivered from database  " . static::$class . "['{$field}']", "class" => get_class($this), "field" => $field));
        }

        return $this->data[$field];
    }

    public function getLoadedFields()
    {
        return array_keys($this->data);
    }

    public function getChangedData()
    {
        return $this->changedData;
    }

    public function getData()
    {
        return $this->data;
    }

    public function addVirtualField($field, $getter, $setter)
    {
        $this->virtualFields[$field] = ["getter" => $getter, "setter" => $setter];
    }

    public function setValue($field, $value, $strict = true)
    {

        if (!in_array($field, static::$fields) && $strict == true) {
            if (isset($this->virtualFields[$field])) {
                $this->virtualFields[$field]["setter"]($value);
                return;
            }
            throw new Exception(array("msg" => "[PersistentObject] Field not exists " . static::$class . "['{$field}']", "class" => get_class($this), "field" => $field));
        }
        if (isset($this->data[$field]) && $this->data[$field] !== $value) {
            $this->changedData[$field] = $this->data[$field];
            $this->data[$field] = $value;
            $this->fieldModified($this, $field, $this->changedData[$field], $value);
            foreach ($this::getTrackers() as $tracker) {
                $tracker::getTracker($this::getClass())->fieldModified($this, $field, $this->changedData[$field], $value);
            }

            $this->modified = true;
        } elseif (!isset($this->data[$field])) {
            $this->data[$field] = $value;
            $this->fieldModified($this, $field, null, $value);
            foreach ($this::getTrackers() as $tracker) {
                $tracker::getTracker($this::getClass())->fieldModified($this, $field, null, $value);
            }

            $this->modified = true;
        }

        return $this;
    }

    public function setValues($values)
    {
        foreach ($values as $key => $value) {
            //we dont save parameters
            if ($key !== "__parameters") {
                $this->setValue($key, $value);
            }
        }
        return $this;
    }

    public function isModified()
    {
        return $this->modified;
    }

    public function isSaved()
    {
        //TODO inaczej to rozwiazac
        return isset($this->data[self::getPKField()]);
    }

    public function setModified($modified)
    {
        $this->modified = $modified;
    }

    public static function getPKField()
    {
        return static::$PKeyField;
    }

    public static function getClass()
    {
        return static::$class;
    }

    public static function getExtensions()
    {
        return static::$extensions;
    }

    public static function getFields()
    {
        return static::$fields;
    }

    public static function getRequiredFields()
    {
        return static::$requiredFields;
    }

    public static function getForeignKeys()
    {
        return static::$foreignKeys;
    }

    public static function getTable()
    {
        return static::$table;
    }

    public static function getTrackers()
    {
        return static::$trackers;
    }

    public static function getConfiguration()
    {
        return array(
            "table" => static::$table,
            "fields" => static::$fields,
            "class" => static::$class,
            "trackers" => static::$trackers,
            "extensions" => static::$extensions
        );
    }

    public function getFriendlyName()
    {
        return null;
    }

    public function save($forceInsert = false)
    {
        PersistentFactory::save($this, true, $forceInsert);

        return $this;
    }

    public function delete()
    {
        if ($this instanceof TreeNode) {
            $this->_delete();
        }
        PersistentFactory::delete($this);
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
        if (in_array($offset, static::$fields)) {
            return true;
        }

        //join
        if (isset($this->data[$offset])) {
            return true;
        }

        return false;
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
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->getValue($offset);
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
        $this->setValue($offset, $value);
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
        throw new Exception("Not implemented in PersistentObject");
    }

    public static function getTracker($class)
    {
        // don't need to implement
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Tree extension
     */


    public function afterObjectSave(PersistentObject $object)
    {
        parent::afterObjectSave($object);

        if ($this instanceof TreeNode) {
            $this->updateTreeSorting();
        }

    }

    public function fieldModified(PersistentObject $object, $field, $oldValue, $newValue)
    {
        parent::fieldModified($object, $field, $oldValue, $newValue);
    }

    public function setParameter($parameter, $value)
    {
        $this->parameters[$parameter] = $value;
    }

    public function hasParameter($parameter)
    {
        return isset($this->parameters[$parameter]);
    }


    public function getParameter($parameter)
    {
        if (!$this->hasParameter($parameter)) {
            return null;
        }
        return $this->parameters[$parameter];
    }

    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
        if (!empty($parameters)) {
            $this->modified = true;
        }
        return $this;
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
        $toSerialize = $this->data;
        $parameters = $this->getParameters();
        if ($parameters) {
            $toSerialize = array_merge($toSerialize, ["__parameters" => $parameters]);
        }

        if (!empty($this->virtualFields)) {
            $array = [];
            foreach ($this->virtualFields as $field => $accessors) {
                $array[$field] = $accessors["getter"]($field, $this);
            }
            $toSerialize = array_merge($toSerialize, $array);
        }

        return $toSerialize;
    }


}
