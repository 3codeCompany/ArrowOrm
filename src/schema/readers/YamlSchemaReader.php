<?php
/**
 * Created by PhpStorm.
 * User: artur.kmera
 * Date: 13.07.2018
 * Time: 07:49
 */

namespace Arrow\ORM\Schema\Readers;


use Arrow\ORM\Exception;
use Arrow\ORM\Schema\Connection;
use Arrow\ORM\Schema\ConnectionTable;
use Arrow\ORM\Schema\Field;
use Arrow\ORM\Schema\Schema;
use Arrow\ORM\Schema\Table;
use Symfony\Component\Yaml\Yaml;

class YamlSchemaReader
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


        foreach ($files as $f) {
            //load XML file

            $data = Yaml::parseFile($f);

            $schema->setEncoding($data["encoding"]);

            foreach ($data["objects"] as $object => $tableData) {
                //disable table node from reading
                if ($tableData["disabled"] ?? false) {
                    continue;
                }
                $table = $this->readTable($schema, $object, $tableData);
                if (!isset($tableData["extensionTo"])) {
                    $schema->addTable($table);
                }
            }
        }
        /**
         * We need loaded whole schema to read f keys and extensions
         */
        foreach ($files as $f) {
            $data = Yaml::parseFile($f);

            foreach ($data["objects"] as $object => $tableData) {
                if ($tableData["disabled"] ?? false) {
                    continue;
                }
                $name = $tableData["table"];
                if (empty($name)) {
                    $name = $tableData['extensionTo'];
                }
                $table = $schema->getTableByTable($name);

                if ($tableData["extensionTo"] ?? false) {
                    foreach ($tableData["fields"] as $name => $fieldData) {
                        $field = $this->readField($table, $name, $fieldData);
                        $table->addField($field);
                    }
                }

                foreach ($tableData["connections"] ?? [] as $connName => $connData) {
                    $connection = $this->readConnection($schema, $table, $connName, $connData);
                    $table->addConnection($connection);
                }


                foreach ($tableData->children() as $tag => $_node) {
                    switch ($tag) {
                        case 'field':
                            //dodaje pola tylko jeśli to rosszeżenie
                            if ($node["extension-to"]) {
                                $field = $this->readField($_node);
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
     * @throws \Arrow\ORM\Schema\SchemaException
     */
    public function readTable(Schema $schema, $object, $data)
    {
        $table = new Table();
        $table->setClass($object);

        $table->setTableName($data['table']);
        foreach ($data["fields"] as $name => $fieldData) {
            $field = $this->readField($name, $fieldData);
            $table->addField($field);
        }
        //connections and fkeys are read later

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
    public function readField($name, $data)
    {
        $field = new Field();
        $field->setName($name);

        $field->setType($data["type"]);
        $field->setAutoincrement($data["autoIncrement"] ?? false);
        $field->setPKey($data["primaryKey"] ?? false);
        $field->setSize($data["size"] ?? false);
        $field->setRequired($data["required"] ?? false);
        $field->setDefault($data["default"] ?? false);

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
        $fKey->foreignTable = $schema->getTableByTable($node["foreignTable"]);
        $fKey->onUpdate = $node["onUpdate"];
        $fKey->onDelete = $node["onDelete"];


        $fKeyReferece = new ForeignKeyReference();
        $fKeyReferece->setLocalFieldName($node->reference[0]["local"]);
        $fKeyReferece->setForeignFieldName($node->reference[0]["foreign"]);
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
    public function readConnection(Schema $schema, Table $table, $connName, $data)
    {
        $connection = new Connection();
        $connection->useDBFKeys = $data["useDBFKeys"];

        $connection->name = $connName;
        foreach ($data["path"] as $class => $tableIn) {


            $_table = $schema->getTableByClass($tableIn["class"]);
            if (!$_table) {
                print "<pre>";
                print_r($schema);
                exit();
                throw new Exception( ["msg" => "No table found", "table" => $tableIn["class"], "connection" => $connName, "parent" => $table->getClass()]);
            }
            $additionalConditions = [];
            foreach ($tableIn["conditions"] as $condition) {
                $additionalConditions[] = ["field" => $condition["field"], "value" => $condition["value"], "condition" => $condition["condition"]];
            }
            $ct = new ConnectionTable($_table, $tableIn["localField"], $tableIn["foreignField"], $additionalConditions);

            $connection->tables[] = $ct;


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
        $index->setName($node->name);
        $index->setType(isset($node["type"]) ? $node["type"] : 'BTREE');

        foreach ($node->children() as $tag => $_node) {
            if ($tag == 'index-field') {
                $field = $table->getFieldByName($_node["name"]);
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
        return $node["class"];
    }

    public function readExtension(Table $table, $node)
    {
        return $node["class"];
    }

}