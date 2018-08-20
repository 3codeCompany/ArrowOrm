<?php

namespace Arrow\ORM\Schema;

/**
 * Read schema config file
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class SchemaReader implements ISchemaReader
{

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaReader::readSchemaFromFile()
     */
    public function readSchemaFromFile($file)
    {
        if (!is_array($file)) {
            $files = [$file];
        } else {
            $files = $file;
        }

        $schema = new Schema();

        /*
         * $schema->version = (string) $sxml["version"];
            $schema->autoIncrementBuildVersion = "".$sxml["autoIncrementBuildVersion"]=="true"?true:false;
         */


        /**
         * first we have to read whole schema and search for includes
         * its important to  properly read all relations
         */
        $includeSchema = function ($file) use (&$includeSchema, &$files) {
            $sxml = simplexml_load_file($file);
            //<include path="./db-schemas/crm-schema.xml" />
            $includes = $sxml->xpath('/schema/include');
            foreach ($includes as $include) {
                $fileToInclude = dirname($file) . "/" . $include["path"];
                $files[] = $fileToInclude;
                $includeSchema($fileToInclude);
            }
        };
        foreach ($file as $f) {
            $includeSchema($f);
        }


        foreach ($files as $f) {
            //load XML file
            $sxml = simplexml_load_file($f);
            $namespace = "";
            if (isset($sxml["class-namespace"])) {
                $namespace = $sxml["class-namespace"] . "";
            }

            $tablesSet = $sxml->xpath('/schema/table');

            foreach ($tablesSet as $tableNode) {
                //disable table node from reading
                if (isset($tableNode["disabled"]) && $tableNode["disabled"] . "" == "true") {
                    continue;
                }
                $table = $this->readTable($schema, $tableNode, $namespace);
                if (!$tableNode["extension-to"]) {
                    $schema->addTable($table);
                }
            }
        }
        /**
         * We need loaded whole schema to read f keys and extensions
         */
        foreach ($files as $f) {
            $sxml = simplexml_load_file($f);
            $tablesSet = $sxml->xpath('/schema/table');

            foreach ($tablesSet as $node) {
                //disable table node from reading
                if (isset($node["disabled"]) && $node["disabled"] . "" == "true") {
                    continue;
                }
                $name = (string)$node['name'];
                if (empty($name)) {
                    $name = (string)$node['extension-to'];
                }
                $table = $schema->getTableByTable($name);
                foreach ($node->children() as $tag => $_node) {
                    switch ($tag) {
                        case 'field':
                            //dodaje pola tylko jeśli to rosszeżenie
                            if ($node["extension-to"]) {
                                $field = $this->readField($table, $_node);
                                $table->addField($field);
                            }
                            break;
                        case 'index':
                            break;
                        case 'extensions':
                            break;
                        case 'trackers':
                            break;
                        case 'connection':
                            $connection = $this->readConnection($schema, $table, $_node);
                            $table->addConnection($connection);
                            break;
                        case 'foreign-key':
                            $foreignKey = $this->readForeignKey($schema, $table, $_node);
                            $table->addForeignKey($foreignKey);
                            break;
                        default:
                            throw new SchemaException("Unknown schema element '$tag'");
                    }
                }
            }

        }

        /**
         * Extension table procedure
         */
        foreach ($schema->getTables() as $table) {
            $extension = $table->getExtension();
            if (!empty($extension)) {
                //looking for base table
                $source = $schema->getTableByTable($extension);
                $table->setTableName($source->getTableName());
                //fields exchange
                $fieldsTable = $table->getFields();
                foreach ($source->getFields() as $field) {
                    $table->addField($field);
                }
                foreach ($fieldsTable as $field) {
                    $source->addField($field);
                }
                //indexes exchange
                foreach ($source->getIndexes() as $index) {
                    $table->addIndex($index);
                }
                foreach ($table->getIndexes() as $index) {
                    $source->addIndex($index);
                }
                //foreign keys exchange
                foreach ($source->getForeignKeys() as $key) {
                    $table->addForeignKey($key);
                }
                foreach ($table->getForeignKeys() as $key) {
                    $source->addForeignKey($key);
                }
            }
        }

        return $schema;
    }

    /**
     * Reads table information and create Table object
     *
     * @param Schema
     * @param XmlElement
     *
     * @return Table $node
     */
    public function readTable(Schema $schema, $node, $namespace)
    {
        $class = (string)$node['class'];


        $table = new Table();
        $table->setClass($class);
        $table->setNamespace($namespace);
        $table->setBaseClass((string)$node['baseclass']);
        $table->setTableName((String)$node['name']);
        /*   if (isset($node['extension-to'])) {
               $table->setAsExtensionTo($node['extension-to']);
               /           if($table->getTableName() == ""){
                   //todo zmienić rand na jakiś index
                   $table->setTableName($node['extension-to'];
               }
           }*/

        foreach ($node->children() as $tag => $node) {
            switch ($tag) {
                case 'field':
                    $field = $this->readField($table, $node);
                    $table->addField($field);
                    break;
                case 'index':
                    //$field = $this->readIndex($table, $node);
                    //$table->addIndex($field);
                    break;
                case 'connection':
                    //do nothing we read connection  later
                    break;
                case 'foreign-key':
                    break;
                    //do nothing we read f keys later
                    break;
                case 'trackers':
                    foreach ($node->tracker as $el) {
                        $tracker = $this->readTracker($table, $el);
                        $table->addTracker($tracker);
                    }
                    break;

                case 'extensions':
                    foreach ($node->extension as $el) {
                        $extension = $this->readExtension($table, $el);
                        $table->addExtension($extension);
                    }
                    break;
                default:
                    throw new SchemaException("Unknow schema element '$tag'");
            }
        }

        return $table;
    }

    /**
     * Reads field information and create Field object
     *
     * @param Table
     * @param XmlElement
     *
     * @return Field
     */
    public function readField(Table $table, $node)
    {
        $field = new Field();
        $field->setName((String)$node["name"]);
        $field->setEncoding(isset($node["encoding"]) ? $node["encoding"] : false);
        //$field->setOldName((String)$node["oldName"]);
        $field->setType((String)$node["type"]);
        $field->setAutoincrement((isset($node["autoIncrement"]) && $node["autoIncrement"] . "" == "true") ? true : false);
        $field->setPKey((isset($node["primaryKey"]) && $node["primaryKey"] . "" == "true") ? true : false);
        $field->setSize((int)$node["size"]);
        $field->setRequired((isset($node["required"]) && $node["required"] . "" == "true") ? true : false);

        return $field;

    }

    /**
     * Reads foreign key information and create ForeignKey object
     * $param $schema Schema
     *
     * @param $table Table
     * @param $node XmlElement
     *
     * @return ForeignKey
     * @todo Implement
     */
    public function readForeignKey(Schema $schema, Table $table, $node)
    {
        $fKey = new ForeignKey();
        $fKey->foreignTable = $schema->getTableByTable((string)$node["foreignTable"]);
        $fKey->onUpdate = (string)$node["onUpdate"];
        $fKey->onDelete = (string)$node["onDelete"];


        $fKeyReferece = new ForeignKeyReference();
        $fKeyReferece->setLocalFieldName((string)$node->reference[0]["local"]);
        $fKeyReferece->setForeignFieldName((string)$node->reference[0]["foreign"]);
        $fKey->addReference($fKeyReferece);

        return $fKey;
    }


    /**
     * Reads foreign key information and create ForeignKey object
     * $param $schema Schema
     *
     * @param $table Table
     * @param $node XmlElement
     *
     * @return ForeignKey
     * @todo Implement
     */
    public function readConnection(Schema $schema, Table $table, $node)
    {
        $connection = new Connection();
        $connection->name = (string)$node["name"];
        foreach ($node->table as $tableIn) {
            $table = $schema->getTableByTable((string)$tableIn["name"]);
            $additionalConditions = [];
            foreach ($tableIn->condition as $condition) {
                $additionalConditions[] = ["field" => $condition["field"] . "", "value" => $condition["value"] . ""];
            }
            $ct = new ConnectionElement($table, (string)$tableIn["local"], (string)$tableIn["foreign"], $additionalConditions);

            $connection->elements[] = $ct;


        }

        return $connection;
    }

    /**
     * Reads index information and create Index object
     *
     * @param Table
     * @param XmlElement
     *
     * @return Index
     * @todo Implement
     */
    public function readIndex(Table $table, $node)
    {

        $index = new Index();
        $index->setName((string)$node->name);
        $index->setType(isset($node["type"]) ? (string)$node["type"] : 'BTREE');

        foreach ($node->children() as $tag => $_node) {
            if ($tag == 'index-field') {
                $field = $table->getFieldByName((string)$_node["name"]);
                if ($field == null) {
                    throw new SchemaException("Field declared in index not finded in table (field: '{$_node["name"]}', table: '{$table->getTableName()}')");
                } else {
                    $index->addFieldName($field->getName());
                }
            }
        }
        return $index;
    }

    public function readTracker(Table $table, $node)
    {
        return (String)$node["class"];
    }

    public function readExtension(Table $table, $node)
    {
        return (String)$node["class"];
    }


}

?>