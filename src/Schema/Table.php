<?php

namespace Arrow\ORM\Schema;

use Arrow\ORM\Exception;
use JsonSerializable;

/**
 * Table schema class
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class Table implements ISchemaElement, JsonSerializable
{
    /**
     * Class  ( slash separated )
     *
     * @var string
     */
    private $class;


    /**
     * Base class
     *
     * @var string
     */
    private $baseClass;


    /**
     * Namespace
     *
     * @var string
     */
    private $namespace;

    /**
     * Db table name
     *
     * @var string
     */
    private $table;

    /**
     * Table fields ( array )
     *
     * @var Field []
     */
    private $fields = array();

    /**
     * Foreign keys
     *
     * @var ForeignKey []
     */
    private $foreignKeys = array();


    /**
     * @var Connection[]
     */
    private $connections = [];


    /**
     * Indexes
     *
     * @var Index []
     */
    private $indexes = array();

    /**
     * Extensions
     *
     * @var IExtension []
     */
    private $extensions = array();

    /**
     * Extensions
     *
     * @var ITracker []
     */
    private $trackers = array();

    private $extensionTo = null;

    private $encoding;

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     * @return Table
     */
    public function setTable(string $table): Table
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return null
     */
    public function getExtensionTo()
    {
        return $this->extensionTo;
    }

    /**
     * @param null $extensionTo
     * @return Table
     */
    public function setExtensionTo($extensionTo)
    {
        $this->extensionTo = $extensionTo;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param mixed $encoding
     * @return Table
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
        return $this;
    }

    public function setAsExtensionTo($table)
    {
        $this->extensionTo = $table;
    }

    public function getExtension()
    {
        return $this->extensionTo;
    }

    public function setBaseClass($class)
    {
        $this->baseClass = $class;
    }

    public function getBaseClass()
    {
        return $this->baseClass;
    }

    public function setClass($class)
    {
        $this->class = $class;
    }

    public function getClassName()
    {
        $tmp = explode("\\", $this->class);
        return end($tmp);
    }

    public function getClass()
    {
        if ($this->class && $this->class[0] != "\\")
            return "\\" . $this->class;

        return $this->class;
    }


    public function setTableName($tableName)
    {
        $this->table = $tableName;
    }

    public function getTableName()
    {
        return $this->table;
    }

    public function addForeignKey(ForeignKey $foreignKey)
    {
        $this->foreignKeys[] = $foreignKey;
    }

    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    public function addIndex(Index $index)
    {
        $this->indexes[] = $index;
    }

    /**
     * Returns table indexes
     *
     * @return Index []
     */
    public function getIndexes()
    {
        return $this->indexes;
    }


    /**
     * Returns table fields
     *
     * @return Field []
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Add field to table
     *
     * @param Field $field
     *
     * @todo check that fielf already exists in schema
     */
    public function addField(Field $field)
    {
        $added = false;
        try {
            $this->getFieldByName($field->getName());
        } catch (SchemaException $ex) {
            //table not found
            $added = true;
            $this->fields[] = $field;
        }
        if (!$added) {
            throw new SchemaException("Field {$field->getName()} already exists in table '{$this->getTableName()}'");
        }

        //$this->fields[] = $field;
    }

    /**
     * Removes field from fieldlist in table
     *
     * @param Field $field
     */
    public function deleteField(Field $field)
    {
        foreach ($this->fields as $key => $_field) {
            if ($field->getName() == $_field->getName()) {
                unset($this->fields[$key]);
                return true;
            }
        }
        throw new SchemaException("Field '{$field->getName()}' not exists in table '{$this->table}'");
    }

    /**
     * Return field by given name
     *
     * @param String $name
     * @return Field
     */
    public function getFieldByName($name)
    {
        foreach ($this->fields as $field) {
            if ($field->getName() == $name) {
                return $field;
            }
        }
        throw new SchemaException("Field '{$name}' not exists in table '{$this->table}'");
    }

    /**
     * @todo implement
     */
    public function getIndexByName()
    {
    }


    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toString()
     */
    public function toString()
    {
        return "Type: Table, Class: {$this->getClass()}, Table: {$this->getTableName()}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toArray()
     */
    public function toArray()
    {
        $fields = array();

        foreach ($this->fields as $key => $field) {
            $fields[$key] = $field->toArray();
        }

        return array(
            "class" => $this->class,
            "baseClass" => $this->baseClass,
            "table" => $this->table,
            "fields" => $fields,
            "indexes" => $this->indexes,
            "foreignKeys" => $this->foreignKeys,

        );
    }

    /**
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        if (strpos($this->class, "\\") !== false) {
            $tmp = explode("\\", trim($this->getClass(), "\\"));
            unset($tmp[count($tmp) - 1]);
            return implode("\\", $tmp);
        }

        return $this->namespace;
    }

    public function addExtension($extension)
    {

        $interfaces = class_implements($extension);


        if (!in_array('Arrow\ORM\Extensions\IExtension', $interfaces)) {
            throw new Exception("Extension class '{$extension}' in table '{$this->getTableName()}' do not implements 'Arrow\\ORM\\Extensions\\IExtension'");
        }

        $this->extensions[] = $extension;
    }

    public function getExtensions()
    {
        return $this->extensions;
    }

    public function addTracker($tracker)
    {

        $interfaces = class_implements($tracker);

        if (!in_array('Arrow\ORM\Extensions\ITracker', $interfaces)) {
            throw new Exception("Tracker class '{$tracker}' in table '{$this->getTableName()}' do not implements 'Arrow\\ORM\\Extensions\\ITracker'");

        }

        $this->trackers[] = $tracker;
    }

    public function getTrackers()
    {
        return $this->trackers;
    }

    /**
     * @return Connection[]
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * @param Connection[] $connections
     * @return Table
     */
    public function addConnection($connection)
    {
        $this->connections[] = $connection;
        return $this;
    }


    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            "class" => $this->class,
            "name" => $this->getTableName(),
            "fields" => $this->fields,
            "connections" => [],
            "indexes" => [],
            "fKeys" => []
        ];
    }
}

?>
