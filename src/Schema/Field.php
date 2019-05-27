<?php
namespace Arrow\ORM\Schema;

use Arrow\ORM\Exception;
use Codeception\Test\Metadata;
use JsonSerializable;

/**
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 *
 * @date 2011-07-18
 */
class Field implements ISchemaElement, JsonSerializable
{


    /**
     * Is primary key
     *
     * @var bool
     */
    private $pKey = false;

    /**
     * Field name
     *
     * @var string
     */
    private $name;

    

    /**
     * Field type
     *
     * @todo change to constans value
     * @var string
     */
    private $type;

    /**
     * Field default value
     *
     * @var bool
     */
    private $default = false;

    /**
     * Is field value required
     *
     * @var bool
     */
    private $required = false;

    /**
     * Size of field
     *
     * @var int
     */
    private $size;

    /**
     * Is field autoincremented
     *
     * @var bool
     */
    private $autoincrement = false;


    private $nullable = false;

    /**
     * @var FieldMetaData
     */
    private $metaData = null;


    private $encoding;

    /**
     * @return mixed
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param mixed $encoding
     */
    public function setEncoding($encoding) : Field
    {
        $this->encoding = $encoding;
        return $this;
    }



    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toString()
     */
    public function toString()
    {
        return "Type: Field, Name: {$this->name}";
    }

    public function isPKey()
    {
        return $this->pKey;
    }

    public function setPKey($val)
    {
        $this->pKey = $val;
    }

    public function isAutoincrement()
    {
        return $this->autoincrement;
    }

    public function setAutoincrement($val)
    {
        $this->autoincrement = $val;
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function setRequired($val)
    {
        $this->required = $val;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }


    public function rename($newName)
    {
        $this->setOldName($this->getName());
        $this->setName($newName);
    }


    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {

        $allowedTypes = [
            "varchar",
            "longvarchar",
            "text",
            "tinyint",
            "smallint",
            "mediumint",
            "bigint",
            "int",
            "date",
            "datetime",
            "timestamp",
            "enum",
            "varbinary",
            "double",
            "float",
            "char",
        ];

        if(!in_array(strtolower($type), $allowedTypes)){
            throw new Exception("Type `{$type}` is not supported" );
        }



        $this->type = $type;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($default)
    {
        $this->default = $default;
    }

    public function getSize()
    {
        if (!$this->size && $this->type == "VARCHAR") {
            return 255;
        }

        return $this->size;
    }

    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaElement::toArray()
     */
    public function toArray()
    {

        $data = array(
            "name" => $this->name,
            "oldName" => $this->oldName,
            "type" => $this->type,
            "size" => $this->size
        );

        if ($this->pKey) {
            $data["pKey"] = $this->pKey;
        }

        if ($this->default) {
            $data["dafault"] = $this->default;
        }

        if ($this->required) {
            $data["required"] = $this->required;
        }

        if ($this->autoincrement) {
            $data["autoincrement"] = $this->autoincrement;
        }

        return $data;
    }

    public function getMetaData()
    {
        return $this->metaData;

    }

    /**
     * @param mixed $metaData
     */
    public function setMetaData($metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @param bool $nullable
     */
    public function setNullable(bool $nullable): void
    {
        $this->nullable = $nullable;
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
            "pKey" => $this->pKey,
            "name" => $this->name,
            "type" => $this->type,
            "size" => $this->size,
            "required" => $this->required,
            "dafault" => $this->default,
            "autoincrement" => $this->autoincrement,
        ];
    }
}

?>
