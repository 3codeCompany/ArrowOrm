<?php namespace Arrow\ORM;
/**
 * Container for tables
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class Schema
{

    /**
     * Database schema version
     *
     * @var string
     */
    public $version;

    /**
     * If set to true auto changing build version to +1 on every resolved mismatch
     *
     * @var string
     */
    public $autoIncrementBuildVersion = false;

    /**
     * Enter description here ...
     *
     * @var Table []
     */
    private $tables = array();

    public function getTables()
    {
        return $this->tables;
    }

    /**
     * Return table by class name
     *
     * @param string $class
     *
     * @return Table
     * @todo implement
     */
    public function getTableByClass($class)
    {

    }

    /**
     * Add table to schema
     *
     * @param Table $table
     */
    public function addTable(Table $table)
    {
        $added = false;
        try {
            $this->getTableByTable($table->getTableName());
        } catch (SchemaException $ex) {
            //table not found
            $added = true;
            $this->tables[] = $table;
        }
        if (!$added) {
            throw new SchemaException("Table '{$table->getTableName()}' already exists in schema");
        }
    }

    /**
     * Return table by table name
     *
     * @param string $tableName
     *
     * @return Table
     * @throws SchemaException Table not found
     */
    public function getTableByTable($tableName)
    {
        foreach ($this->tables as $table) {
            if ($table->getTableName() == $tableName) {
                return $table;
            }
        }
        throw new SchemaException("Table '{$tableName}' no exists");
    }

    /**
     * Increments schema Build version
     */
    public function incrementBuildVersion()
    {
        $tmp = explode(".", $this->version);
        if (isset($tmp[3])) {
            $tmp[3]++;
            $v = implode(".", $tmp);
        } elseif (count($tmp) == 2) {
            $v = $tmp . ".1";
        } elseif (count($tmp) == 1) {
            $v = $tmp . ".0.1";
        }

        $this->version = $v;

    }

}

?>