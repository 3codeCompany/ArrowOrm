<?php namespace Arrow\ORM\Schema;

/**
 * Index schema representation
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 * @todo       implement
 */
class Index implements ISchemaElement
{

    /**
     * Index name
     *
     * @var String
     */
    private $name;

    /**
     * Index type
     *
     * @var String
     */
    private $type;


    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Index fields
     *
     * @var string []
     */
    public $fieldsNames = array();

    /**
     * Returns table fields
     *
     * @return Field []
     */
    public function getFieldNames()
    {
        return $this->fieldsNames;
    }

    /**
     * Add field to table
     *
     * @param string $fieldName
     */
    public function addFieldName($fieldName)
    {
        $this->fieldsNames[] = $fieldName;
    }

    /**
     * Removes field from fieldlist in table
     *
     * @param string $fieldName
     */
    public function deleteFieldName($fieldName)
    {
        foreach ($this->fieldsNames as $key => $field) {
            if ($field == $fieldName) {
                unset($this->fields[$key]);
                return true;
            }
        }
        throw new SchemaException("Field '{$name}' not exists ");
    }

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toString()
     */
    public function toString()
    {
        return "Type: Index, Name: {$this->name}";
    }

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toArray()
     */
    public function toArray()
    {

        return array(
            "name" => $this->name
        );
    }
}

?>