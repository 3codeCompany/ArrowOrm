<?php
namespace Arrow\ORM\Schema;
/**
 * Foreign key reference schema representation
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class ForeignKeyReference implements ISchemaElement
{

    const MODE_CASCADE = 'CASCADE';
    const MODE_SET_NULL = 'SET NULL';
    const MODE_NO_ACTION = 'NO ACTION';
    const MODE_RESTRICT = 'RESTTICT';


    /**
     * LocalFieldName
     *
     * @var string
     */
    public $localName;
    /**
     * Foreign table object
     *
     * @var string
     */
    public $foreignName;


    /**
     * setss local field
     *
     * @param string $name
     */
    public function setLocalFieldName($name)
    {
        $this->localName = $name;
    }

    /**
     * Sets foreign field
     *
     * @param string $foreignTableName
     */
    public function setForeignFieldName($foreignName)
    {
        $this->foreignName = $foreignName;
    }

    /**
     * Gets local field
     *
     * @return string
     */
    public function getLocalFieldName()
    {
        return $this->localName;
    }

    /**
     * Sets foreign field
     *
     * @return string
     */
    public function getForeignFieldName()
    {
        return $this->foreignName;
    }


    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toString()
     */
    public function toString()
    {
        return "Type: ForeignKeyReference, Name: {$this->name}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toArray()
     */
    public function toArray()
    {
        return array(
            "localName" => $this->localName,
            "foreignName" => $this->foreignName
        );
    }
}

?>