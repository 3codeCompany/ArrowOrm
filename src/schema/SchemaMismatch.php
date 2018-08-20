<?php
namespace Arrow\ORM\Schema;
/**
 * Mismatch information
 * Representation of mismatched element that exists in  schema and not exists in datasource
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class SchemaMismatch extends AbstractMismatch
{

    /**
     * Parent to element (Schema, Table )
     *
     * @var ISchemaElement
     */
    public $parentElement;

    /**
     * Mismatched element (Table, Field, Index , Foreignkey )
     *
     * @var ISchemaElement
     */
    public $element;

    /**
     * Missmatch type based on AbstractMismatch const mismatches types
     *
     * @var int
     */
    public $type;

    /**
     * Additional data
     *
     * @var array
     */
    public $data;


    /**
     * Constructor
     *
     * @param ISchemaElement $parent
     * @param ISchemaElement $element
     * @param int            $type From AbstractMismatch type
     */
    public function __construct(Schema $schema, ISchemaElement $parent, ISchemaElement $element, $type, $data)
    {
        $this->schema = $schema;
        $this->parentElement = $parent;
        $this->element = $element;
        $this->type = $type;
        $this->data = $data;
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
        if ($this->type == self::NAME_NOT_EQUALS) {
            $type = "name not equals";
        }

        return "SchemaMismatch: Parent '{$this->parentElement->toString()}', Element '{$this->element->toString()}', type '{$type}' Data: ". implode( ", ", $this->data);
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
            "type"          => $this->type
        );
    }
}

?>