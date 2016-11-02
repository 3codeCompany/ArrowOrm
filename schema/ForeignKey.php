<?php namespace Arrow\ORM;
/**
 * Foreign key schema representation
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class ForeignKey implements ISchemaElement
{

    const MODE_CASCADE = 'CASCADE';
    const MODE_SET_NULL = 'SET NULL';
    const MODE_NO_ACTION = 'NO ACTION';
    const MODE_RESTRICT = 'RESTTICT';


    /**
     * Name
     *
     * @var string
     */
    public $name;
    /**
     * Foreign table object
     *
     * @var Table
     */
    public $foreignTable;
    /**
     * On update action
     *
     * @var string
     */
    public $onUpdate;
    /**
     * On delete action
     *
     * @var string
     */
    public $onDelete;

    /**
     * References
     *
     * @var ForeignKeyReference []
     */
    public $references;

    /**
     * Foreign table name
     *
     * @var String
     */
    public $foreignTableName;


    /**
     * Sets name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Sets foreign table
     *
     * @param string $foreignTableName
     */
    public function setForeignTableName($foreignTableName)
    {
        $this->foreignTableName = $foreignTableName;
    }


    /**
     * Add reference
     *
     * @param ForeignKeyReference $reference
     */
    public function addReference(ForeignKeyReference $reference)
    {
        $this->references[] = $reference;
    }

    /**
     * Return references
     *
     * @return ForeignKeyReference []
     */
    public function getReferences()
    {
        return $this->references;
    }


    /**
     * On update
     *
     * @param string $onUpdate
     */
    public function setOnUpdate($onUpdate)
    {
        $this->onUpdate = $onUpdate;
    }

    /**
     * On delete
     *
     * @param string $onDelete
     */
    public function setOnDelete($onDelete)
    {
        $this->onDelete = $onDelete;
    }


    /**
     * Gets name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Getter for foreign table
     *
     * @return string
     */
    public function getForeignTableName()
    {
        return $this->foreignTableName;
    }


    /**
     * Getter for on update action
     *
     * @return string
     */
    public function getOnUpdate()
    {
        return $this->onUpdate;
    }

    /**
     * Getter for on delete action
     *
     * @return string
     */
    public function getOnDelete()
    {
        return $this->onDelete;
    }


    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toString()
     */
    public function toString()
    {
        return "Type: ForeignKey, Name: {$this->name}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toArray()
     */
    public function toArray()
    {
        $references = array();

        foreach ($this->references as $key => $reference) {
            $references[$key] = $reference->toArray();
        }

        return array(
            "name" => $this->name,
            "foreignTableName" => $this->foreignTableName,
            "onUpdate" => $this->onUpdate,
            "onDelete" => $this->onDelete,
            "references" => $references
        );
    }


}

?>