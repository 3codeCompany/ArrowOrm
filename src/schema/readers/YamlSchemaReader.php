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
use Arrow\ORM\Schema\ConnectionElement;
use Arrow\ORM\Schema\Field;
use Arrow\ORM\Schema\Index;
use Arrow\ORM\Schema\Schema;
use Arrow\ORM\Schema\Table;
use Arrow\ORM\Schema\FieldMetaData;
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
                if (!isset($tableData["extension-to"])) {
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

                if (isset($tableData['extension-to'])) {
                    $name = $tableData['extension-to'];
                    $table = $schema->getTableByClass($name);
                } else {
                    $name = $tableData["table"] ?? null;
                    $table = $schema->getTableByTable($name);
                }


                if ($tableData["extension-to"] ?? false) {
                    foreach ($tableData["fields"] as $name => $fieldData) {
                        $field = $this->readField($name, $fieldData);
                        $table->addField($field);
                    }
                }

                foreach ($tableData["connections"] ?? [] as $connName => $connData) {
                    $connection = $this->readConnection($schema, $table, $connName, $connData);
                    $table->addConnection($connection);
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

        $table->setTableName($data['table'] ?? null);
        $table->setEncoding($data["encoding"] ?? false);

        foreach ($data["fields"] as $name => $fieldData) {
            $field = $this->readField($name, $fieldData);
            $table->addField($field);
        }

        if (isset($data["indexes"])) {
            foreach ($data["indexes"] as $name => $indexData) {
                $index = $this->readIndex($table, $name, $indexData);
                $table->addIndex($index);
            }
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
        $field->setAutoincrement($data["auto-increment"] ?? false);
        $field->setPKey($data["primary-key"] ?? false);
        $field->setSize($data["size"] ?? false);
        $field->setRequired($data["required"] ?? false);
        $field->setDefault($data["default"] ?? false);
        $field->setNullable($data["nullable"] ?? false);
        $field->setEncoding($data["encoding"] ?? false);


        if (isset($data["meta"])) {
            $metadata = new FieldMetaData();
            $metadata->setLabel($data["meta"]["label"] ?? null);
            $metadata->setOptions($data["meta"]["options"] ?? null);
            $metadata->setData($data["meta"]["data"] ?? null);
            $field->setMetaData($metadata);
        }

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
        $connection->setUseDBFKeys($data["useDBFKeys"]);

        $connection->setName($connName);

        $elements = [];
        foreach ($data["path"] as $class => $tableIn) {


            $_table = $schema->getTableByClass($tableIn["class"]);
            if (!$_table) {
                throw new Exception(["msg" => "No table found", "table" => $tableIn["class"], "connection" => $connName, "parent" => $table->getClass()]);
            }
            $additionalConditions = [];
            foreach ($tableIn["conditions"] as $condition) {
                $additionalConditions[] = ["field" => $condition["field"], "value" => $condition["value"], "condition" => $condition["condition"]];
            }
            $ct = new ConnectionElement($_table, $tableIn["localField"], $tableIn["foreignField"], $additionalConditions);

            $elements[] = $ct;

        }

        $connection->setElements($elements);

        return $connection;
    }

    /**
     * Reads index information and create Index object
     *
     * @param Table $table
     * @param $indexName
     * @param $indexData
     * @return Index
     * @throws \Arrow\ORM\Schema\SchemaException
     */
    public function readIndex(Table $table, $indexName, $indexData)
    {
        $index = new Index();
        $index->setName($indexName);
        $index->setType($indexData["type"]);


        foreach ($indexData["columns"] as $_field) {

            $field = $table->getFieldByName($_field["column"]);
            if ($field == null) {
                throw new SchemaException("Field declared in index not found in table (field: '{$field}', table: '{$table->getTableName()}')");
            } else {
                $index->addFieldName($field->getName(), $_field["size"]);
            }

        }
        return $index;
    }


}