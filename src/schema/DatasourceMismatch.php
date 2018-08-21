<?php
namespace Arrow\ORM\Schema;
/**
 * Mismatch information
 * Representation of mismatched element that exists in datasource and not exists in schema
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class DatasourceMismatch extends AbstractMismatch
{

    const ELEMENT_TYPE_TABLE = 0;
    const ELEMENT_TYPE_FIELD = 1;
    const ELEMENT_TYPE_INDEX = 2;
    const ELEMENT_TYPE_FOREIGN_KEY = 3;

    /**
     * Database for tables, table name for fields, indexes, and foreign keys
     *
     * @var string
     */
    public $parentElement;

    /**
     * Table, field, index or foreign key name
     *
     * @var string
     */
    public $element;

    /**
     * Table, field, index or foreign key basend on class const
     * ( ELEMENT_TYPE_TABLE, ELEMENT_TYPE_FIELD, ELEMENT_TYPE_INDEX, ELEMENT_TYPE_FOREIGN_KEY  )
     *
     * @var int
     */
    public $elementType;

    /**
     * Missmatch type based on AbstractMismatch const mismatches types
     *
     * @var int
     */
    public $type;


    /**
     * Constructor
     *
     * @param string $parent
     * @param string $element
     * @param int    $elementType
     * @param int    $type
     */
    public function __construct(Schema $schema, $parent, $element, $elementType, $type)
    {
        $this->schema = $schema;
        $this->parentElement = $parent;
        $this->element = $element;
        $this->elementType = $elementType;
        $this->type = $type;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AbstractMismatch::toString()
     */
    public function __toString()
    {
        $type = "";
        if ($this->type == self::NOT_EQUALS) {
            $type = "not equals";
        }
        if ($this->type == self::NOT_EXISTS) {
            $type = "not exists";
        }
        if ($this->type == self::INDEX_NOT_EQUALS) {
            $type = "index not equals";
        }
        if ($this->type == self::INDEX_NOT_EQUALS) {
            $type = "index not equals";
        }

        $elementType = "";
        if ($this->elementType == self::ELEMENT_TYPE_TABLE) {
            $elementType = "table";
        }
        if ($this->elementType == self::ELEMENT_TYPE_FIELD) {
            $elementType = "field";
        }
        if ($this->elementType == self::ELEMENT_TYPE_INDEX) {
            $elementType = "index";
        }
        if ($this->elementType == self::ELEMENT_TYPE_FOREIGN_KEY) {
            $elementType = "foreign key";
        }

        return "DB -> Schema mismatch: Parent '{$this->parentElement}', ElementType: '{$elementType}', Element '{$this->element}', type '{$type}'";
    }

    /**
     * (non-PHPdoc)
     *
     * @see AbstractMismatch::toArray()
     */
    public function toArray()
    {
        return array(
            "parentElement" => $this->parentElement,
            "element"       => $this->element,
            "elementType"   => $this->elementType,
            "type"          => $this->type
        );
    }
}

?>