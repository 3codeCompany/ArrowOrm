<?php
namespace Arrow\ORM\Schema\Synchronizers;

use Arrow\ORM\Exception;
use Arrow\ORM\Schema\AbstractMismatch;
use Arrow\ORM\Schema\AbstractSynchronizer;
use Arrow\ORM\Schema\DatasourceMismatch;
use Arrow\ORM\Schema\Field;
use Arrow\ORM\Schema\ResolvedMismatch;
use Arrow\ORM\Schema\Schema;
use Arrow\ORM\Schema\SchemaMismatch;
use Arrow\ORM\Schema\Table;

/**
 * Mysql AbstractSynchronizer implementation
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 * @todo       comment rest
 */
class MysqlSynchronizer extends AbstractSynchronizer
{

    /**
     * @var \PDO
     */
    private $connection;

    /**
     * @param $query
     * @return \PDOStatement
     */
    private function query($query)
    {
        $statement = $this->connection->prepare($query);
        try {
            $statement->execute();
        } catch (\PDOException $ex) {
            exit("Problem executing query: <pre>" . $query . "</pre><pre>{$ex->getMessage()}</pre>");
        }
        return $statement;
    }

    private function update($query)
    {
        $statement = $this->connection->prepare($query);
        try {
            $statement->execute();
        } catch (\PDOException $ex) {
            exit("Problem executing query: <pre>" . $query . "</pre><pre>{$ex->getMessage()}</pre>");
        }

    }

    function __construct(\PDO $conn)
    {
        $this->connection = $conn;
    }


    /**
     * (non-PHPdoc)
     *
     * @see AbstractSynchronizer::getSchemaMismatches()
     * @return SchemaMismatch[]
     */
    public function getSchemaMismatches(Schema $schema)
    {
        $mismatches = array();

        $dbTables = array();
        $st = $this->query("show tables");
        while ($row = $st->fetch(\PDO::FETCH_NUM)) {
            $dbTables[] = $row[0];
        }
        $schemaTables = array();

        foreach ($schema->getTables() as $table) {
            if ($table->getExtension() != null) {
                continue;
            }
            if (!in_array($table->getTableName(), $dbTables)) {
                //$this->createTable($table,$conn);
                $mismatches[] = new SchemaMismatch($schema, $table, $table, SchemaMismatch::NOT_EXISTS);
            } else {
                $tmp = $this->checkTableFields($schema, $table);

                $mismatches = array_merge($mismatches, $tmp);

                $tmp = $this->checkIndexes($schema, $table);
                $mismatches = array_merge($mismatches, $tmp);
            }
            //$schemaTables[] = $table->table;
        }

        //iterating over ds tables ( only check if exists in schema )
        foreach ($dbTables as $table) {
            $exists = false;
            foreach ($schema->getTables() as $schemaTable) {
                if ($schemaTable->getTableName() == $table) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $mismatches[] = new  DatasourceMismatch($schema, "Database", $table, DatasourceMismatch::ELEMENT_TYPE_TABLE, DatasourceMismatch::NOT_EXISTS);
            }
        }

        return $mismatches;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AbstractSynchronizer::synchronize()
     */
    public function synchronize(Schema $schema, $mode = AbstractSynchronizer::MODE_SCHEMA_TO_DS)
    {

        $mismatches = $this->getSchemaMismatches($schema);

        $resolved = array();
        foreach ($mismatches as $mismatch) {
            //schema mismatch

            $resolved[] = $this->resolveMismatch($mismatch, $mode);
        }

        return $resolved;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AbstractSynchronizer::resolveMismatch()
     */
    public function resolveMismatch(AbstractMismatch $mismatch, $mode = AbstractSynchronizer::MODE_SCHEMA_TO_DS)
    {
        return true;
        $schema = $mismatch->schema;
        $resolvedMismatch = new ResolvedMismatch();
        $resolvedMismatch->mismatch = $mismatch;

        $sql = "";
        if ($this->getForeignKeysIgnore()) {
            $this->query("SET FOREIGN_KEY_CHECKS=0;");
        }


        if ($mismatch instanceof SchemaMismatch) {
            //table
            if ($mismatch->element instanceof Table) {
                $sql = $this->createTable($mismatch->element);
            }

            //field
            if ($mismatch->element instanceof Field) {
                if ($mismatch->type == SchemaMismatch::NOT_EXISTS) {
                    if ($mode == self::MODE_SCHEMA_TO_DS || $mode == self::MODE_ALL) {
                        //creating field on datasource
                        $sql = $this->createField($mismatch->parentElement, $mismatch->element);
                    } elseif ($mode == self::MODE_DS_TO_SCHEMA) {
                        if (!$this->isPreventRemoveActions()) {
                            //removing field from schema ( table)
                            $mismatch->parentElement->deleteField($mismatch->element);
                        } else {
                            $toRemove = "Field: {$mismatch->parentElement}.{$mismatch->element}";
                            print("Remove prevention is on, cant remove $toRemove\n");
                        }
                    }
                }
                if ($mismatch->type == SchemaMismatch::NOT_EQUALS || $mismatch->type == SchemaMismatch::INDEX_NOT_EQUALS) {
                    if ($mode == self::MODE_SCHEMA_TO_DS || $mode == self::MODE_ALL) {
                        $sql = $this->updateField($mismatch->parentElement, $mismatch->element);
                    } elseif ($mode == self::MODE_DS_TO_SCHEMA) {
                        $this->updateFieldFromDs($mismatch->parentElement, $mismatch->element);
                    }
                }
                if ($mismatch->type == SchemaMismatch::NAME_NOT_EQUALS) {
                    if ($mode == self::MODE_SCHEMA_TO_DS || $mode == self::MODE_ALL) {
                        $sql = $this->updateField($mismatch->parentElement, $mismatch->element, true);
                    } elseif ($mode == self::MODE_DS_TO_SCHEMA) {
                        $mismatch->element->setName($mismatch->element->getOldName());
                    }
                }
            }

        }

        if ($mismatch instanceof DatasourceMismatch) {

            if ($mismatch->elementType == DatasourceMismatch::ELEMENT_TYPE_TABLE) {
                if ($mode == self::MODE_SCHEMA_TO_DS) {
                    if (!$this->isPreventRemoveActions()) {
                        $sql = $this->deleteTable($mismatch->element);
                    } else {
                        $toRemove = "Table: {$mismatch->element}";
                        print("Remove prevention is on, cant remove $toRemove\n");
                    }
                } elseif ($mode == self::MODE_DS_TO_SCHEMA || $mode == self::MODE_ALL) {
                    $table = $this->createTableFromDs($mismatch->element);
                    $schema->addTable($table);
                }
            }
            if ($mismatch->elementType == DatasourceMismatch::ELEMENT_TYPE_FIELD) {
                if ($mode == self::MODE_SCHEMA_TO_DS) {
                    if (!$this->isPreventRemoveActions()) {
                        //removing field from schema ( table)
                        $sql = $this->deleteField($mismatch->parentElement, $mismatch->element);
                    } else {
                        $toRemove = "Field: {$mismatch->parentElement}.{$mismatch->element}";
                        print("Remove prevention is on, cant remove $toRemove\n");
                    }
                } elseif ($mode == self::MODE_DS_TO_SCHEMA || $mode == self::MODE_ALL) {
                    $field = $this->createFieldFromDs($mismatch->parentElement, $mismatch->element);
                    $table = $schema->getTableByTable($mismatch->parentElement);
                    $table->addField($field);
                }

            }
        }
        $resolvedMismatch->additionalData = $sql;
        $resolvedMismatch->timestamp = time();

        if (true) {
            $resolvedMismatch->success = true;
            if ($mismatch->schema->autoIncrementBuildVersion) {
                $mismatch->schema->incrementBuildVersion();
            }
        }


        $resolvedMismatch->additionalData = $sql;

        return $resolvedMismatch;

    }

    /**
     * (non-PHPdoc)
     *
     * @see  AbstractSynchronizer::databaseToSchema()
     * @todo Insert resovedmatch object and return it
     */
    public function datasourceToSchema(Datasource $ds, Schema $schema)
    {
        exit("datasourceToSchema exit");
        $conn = $ds->getConnection();
        $mismatches = $this->getSchemaMismatches($ds, $schema);

        foreach ($mismatches as $mismatch) {
            if ($mismatch->elementType == DatasourceMismatch::ELEMENT_TYPE_TABLE) {
                $table = new Table();
                //$table->class = "auto_path.Auto_".$mismatch->element;
                //$table->baseClass = "auto.Persistent";
                $table->setTableName($mismatch->element);

                $schema->addTable($table);
            }
        }

        $mismatches = $this->getSchemaMismatches($ds, $schema);

        foreach ($mismatches as $mismatch) {

            if ($mismatch->elementType == DatasourceMismatch::ELEMENT_TYPE_FIELD) {

                $field = new Field();
                $field->setName($mismatch->element);

                $statement = $this->query("SHOW COLUMNS FROM {$mismatch->parentElement} where Field='{$mismatch->element}'");

                $column = $statement->fetch();

                $field->setPKey($column["Key"] == "PRI" ? true : false);
                $field->setAutoincrement($column["Extra"] == "auto_increment" ? true : false);
                $field->setDefault($column["Default"]);
                $field->setRequired($column["Null"] == "NO" ? true : false);

                $tmp = explode("(", $column["Type"]);
                $type = $tmp[0];

                if (in_array($column["Type"], array("text", "mediumtext", "longtext"))) {

                    if ($type == "text") {
                        $size = 65535;
                    } elseif ($type == "mediumtext") {
                        $size = 16777215;
                    } elseif ($type == "longtext") {
                        $size = 4294967295;
                    }
                    $type = "LONGVARCHAR";
                } else {
                    $size = str_replace(")", "", $tmp[1]);
                }
                $field->setType($type);
                $field->setSize($size);
                $table = $schema->getTableByTable($mismatch->parentElement);
                $table->addField($field);
            }
        }
    }

    /**
     * Creating table in database
     *
     * @param Table $table
     * @return string
     */
    private function createTable(Table $table)
    {
        $sql = "CREATE TABLE {$table->getTableName()}(";
        $count = count($table->getFields());
        foreach ($table->getFields() as $index => $field) {
            $sql .= $this->getFieldCreationCode($table, $field);
            if ($index + 1 < $count) {
                $sql .= ",\n";
            }
        }
        $sql .= ")";

        $this->update($sql);
        return $sql;
    }

    private function checkTableFields(Schema $schema, Table $table)
    {

        $mismatches = array();

        $statement = $this->query("SHOW COLUMNS FROM `{$table->getTableName()}`");
        $columns = $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $tableFields = $table->getFields();

        //iterating over schema fields ( only check if exists, rest of checks  are in the next loop )
        foreach ($tableFields as $field) {
            $exists = false;

            foreach ($columns as $column) {
                if ($field->getName() == $column["Field"] || $field->getOldName() == $column["Field"]) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $mismatches[] = new SchemaMismatch($schema, $table, $field, SchemaMismatch::NOT_EXISTS);
            }
        }

        $i = 0;

        //iterating over db columns
        foreach ($columns as $column) {

            if (!isset($tableFields[$i]) || $tableFields[$i]->getName() != $column["Field"]) {
                $exists = false;
                if (isset($tableFields[$i])) {
                    //checks that field exists in db
                    foreach ($columns as $tmp) {
                        if ($tableFields[$i] == $tmp["Field"]) {
                            $exists = true;
                            break;
                        }
                    }
                }


                if ($exists) {
                    //TODO sprawdzic poprawnosc
                    $mismatches[] = new SchemaMismatch($schema, $table, $tableFields[$i], SchemaMismatch::NOT_EQUALS);
                } else {
                    //checks that field exists in schema
                    $exists = false;
                    $existsField = false;
                    foreach ($tableFields as $field) {
                        if ($field->getName() == $column["Field"]) {
                            $exists = true;
                            $existsField = $field;
                            break;
                        }
                    }
                    $existByOldName = false;
                    if (!$exists) {
                        $existsField = false;
                        foreach ($tableFields as $field) {
                            if ($field->getOldName() == $column["Field"]) {
                                $existByOldName = true;
                                $existsField = $field;
                                break;
                            }
                        }
                    }

                    if ($existByOldName) {
                        $mismatches[] = new SchemaMismatch($schema, $table, $existsField, SchemaMismatch::NAME_NOT_EQUALS);
                    } elseif ($exists) {
                        $mismatches[] = new SchemaMismatch($schema, $table, $existsField, SchemaMismatch::INDEX_NOT_EQUALS);
                    } else {
                        $mismatches[] = new DatasourceMismatch($schema, $table->getTableName(), $column["Field"], DatasourceMismatch::ELEMENT_TYPE_FIELD, SchemaMismatch::NOT_EXISTS);
                        $i--;
                    }
                }

            } else {

                if ($this->checkField($tableFields[$i], $column) == false) {
                    $mismatches[] = new SchemaMismatch($schema, $table, $tableFields[$i], SchemaMismatch::NOT_EQUALS);
                }
            }

            $i++;
        }

        return $mismatches;

    }

    private function checkField(Field $field, $column)
    {


        if ($field->isPKey() && $column["Key"] != "PRI") {
            return false;
        }
        if ($field->isAutoincrement() && $column["Extra"] != "auto_increment") {
            return false;
        }
        if ($field->getDefault() && $field->getDefault() != $column["Default"]) {
            return false;
        }
        if ($field->isRequired() && $column["Null"] != "NO") {
            return false;
        }

        $tmp = explode("(", $column["Type"]);
        $type = $tmp[0];

        $size = false;


        //longvarchar is changed for text and it not posses size
        if ($field->getSize() && $field->getType() != "LONGVARCHAR") {
            if (isset($tmp[1])) {
                $size = str_replace(")", "", $tmp[1]);
                if ($field->getSize() != $size) {
                    return false;
                }
            }
        }


        $types = array("INTEGER" => "INT", "LONGVARCHAR" => "TEXT");
        $testType = strtolower(str_replace(array_keys($types), $types, $field->getType()));


        /**
         * @todo Probably problem width columns in utf mysql creates bigger fields instead
         *       text we have medium text etc.... co we have to hack system , so fix and uncoment to check
         *       text field precysly
         */
        /*
        if($testType == "text" && $size < 65535 )
            $testType = "text";
        elseif($testType == "text" && $size < 16777215 )
            $testType = "mediumtext";
        elseif($testType == "text" && $size > 16777215 )
            $testType = "longtext";
        */
        //todo sprawdzic czemu oba pola zmieniamy na integer - powinno tylko jedno
        if ($type == "int") $type = "integer";
        if ($testType == "int") $testType = "integer";

        if ($testType == "text" && !in_array($type, array("text", "mediumtext", "longtext"))) {

            return false;
        } elseif ($testType != "text" && $testType != $type) {
            return false;
        }


        return true;
    }

    private function checkIndexes(Schema $schema, Table $table)
    {
        $mismatches = array();

        $query = "SHOW INDEXES  FROM `{$table->getTableName()}`";
        $dsindexes = $this->query($query)->fetchAll(\PDO::FETCH_ASSOC);

        //TODO sprawdzenia indexów
        return array();
        foreach ($table->getIndexes() as $index) {
            $exists = false;

            foreach ($dsindexes as $dsIndex) {


                //spawdzenie indexu z ds
                /*
                        $index = new Index();
                $index->setName($indexName);
                $index->setType($dsIndex[0]["Index_type"]);

                foreach($dsIndex as $element)
                    $index->addFieldName( $element["Column_name"] );
                */

            }
            if (!$exists) {
                $mismatches[] = new DatasourceMismatch($schema, $ds, $table, $dsIndex, DatasourceMismatch::ELEMENT_TYPE_FOREIGN_KEY, AbstractMismatch::NOT_EXISTS);
            }

        }
        //PKey is already checked in fields check
        //	if( $dsIndex["Key_name"] != "PRIMARY"){

        return $mismatches;
    }

    private function checkForeignKeys(Schema $schema, Datasource $ds, Table $table)
    {
        $mismatches = array();

        $dsFKeys = $this->getDsForeignKeysInfo($table->getTableName());

        foreach ($table->getForeignKeys() as $fKey) {
            $exists = false;
            foreach ($dsFKeys as $dsFKey) {
                if ($fKey->getName() == $dsFKey["name"]) {
                    $exists = true;

                    $equals = true;
                    if ($fKey->getLocalFieldName() != $dsFKey["local_field"]) {
                        $equals = false;
                    }
                    if ($fKey->getForeignTableName() != $dsFKey["reference_table"]) {
                        $equals = false;
                    }
                    if ($fKey->getForeignFieldName() != $dsFKey["reference_field"]) {
                        $equals = false;
                    }
                    if ($fKey->getOnDelete() != $dsFKey["on_delete"]) {
                        $equals = false;
                    }
                    if ($fKey->getOnUpdate() != $dsFKey["on_update"]) {
                        $equals = false;
                    }

                    if (!$equals) {
                        $mismatches[] = new DatasourceMismatch($schema, $ds, $table, $fKey, DatasourceMismatch::ELEMENT_TYPE_FOREIGN_KEY, AbstractMismatch::NOT_EQUALS);
                    }

                }
            }
            if (!$exists) {
                $mismatches[] = new DatasourceMismatch($schema, $ds, $table, $fKey, DatasourceMismatch::ELEMENT_TYPE_FOREIGN_KEY, AbstractMismatch::NOT_EXISTS);
            }

        }
        foreach ($dsFKeys as $dsFKey) {
            foreach ($table->getForeignKeys() as $fKey) {
                $exists = false;
                if ($fKey->getName() == $dsFKey["name"]) {
                    $exists = true;
                }
            }
            if (!$exists) {
                $mismatches[] = new SchemaMismatch($schema, $ds, $table->getTableName(), $dsFKey["name"], AbstractMismatch::NOT_EXISTS);
            }
        }

        return $mismatches;
    }


    private function deleteTable($table)
    {
        $sql = "DROP TABLE  `{$table}`";
        $this->update($sql);
        return $sql;
    }

    private function createField(Table $table, Field $field)
    {
        $fields = $table->getFields();
        $index = array_search($field, $fields);
        if ($index == 0) {
            $prev = "FIRST";
        } else {
            $prev = "AFTER `{$fields[$index - 1]->getName()}`";
        }

        $sql = $this->getFieldCreationCode($table, $field);
        $sql = "ALTER TABLE  `{$table->getTableName()}` ADD  $sql  {$prev}";
        $this->update($sql);

        return $sql;
    }

    private function updateField(Table $table, Field $field, $oldName = false)
    {
        $fields = $table->getFields();
        $index = array_search($field, $fields);
        if ($index == 0) {
            $prev = "FIRST";
        } else {
            $prev = "AFTER `{$fields[$index-1]->getName()}`";
        }

        $sql = $this->getFieldCreationCode($table, $field);
        $name = $oldName ? $field->getOldName() : $field->getName();

        $sql = "ALTER TABLE  `{$table->getTableName()}` CHANGE `{$name}`  $sql  {$prev}";

        //cannot twice set primary index in the same field
        if (strpos($sql, "PRIMARY KEY ") !== false) {
            $q = "SHOW KEYS FROM `{$table->getTableName()}` WHERE Key_name = 'PRIMARY'";
            $res = $this->query($q)->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($res as $row) {
                if ($row["Column_name"] == $field->getName())
                    $sql = str_replace("PRIMARY KEY ", "", $sql);
            }
        }
        $this->update($sql);


        return $sql;
    }

    private function deleteField($table, $field)
    {
        $sql = "ALTER TABLE `{$table}` DROP `{$field}`";
        $this->update($sql);
        return $sql;
    }


    private function translateType($type)
    {
        $types = array("INTEGER" => "INT", "LONGVARCHAR" => "TEXT");
        return str_replace(array_keys($types), $types, $type);
    }


    private function getFieldCreationCode(Table $table, Field $field)
    {
        $sql = "`{$field->getName()}` {$this->translateType($field->getType())}";

        if ($field->getSize()) {
            $sql .= "({$field->getSize()})";
        }

        if ($field->isAutoincrement()) {
            $sql .= " AUTO_INCREMENT";
        }

        if ($field->isPKey()) {
            $sql .= " PRIMARY KEY";
        }

        if ($field->isRequired()) {
            $sql .= " NOT NULL";
        }

        if ($field->getDefault()) {
            $sql .= " DEFAULT  '{$field->getDefault()}'";
        }

        return $sql;
    }

    private function createTableFromDs($tableName)
    {
        $table = new Table();
        $table->setClass("auto_path.AutoClass" . ucwords($tableName));
        $table->setBaseClass("auto.Persistent");
        $table->setTableName($tableName);

        $columns = $this->query("SHOW COLUMNS FROM `{$tableName}`");
        while ($colData = $columns->fetch()) {
            $table->addField($this->createFieldFromDs($tableName, $colData["Field"]));
        }

        $indexes = $this->query("SHOW INDEXES  FROM `{$tableName}`");
        while ($index = $indexes->fetch()) {
            //PKey is already checked in fields check
            if ($index["Key_name"] != "PRIMARY") {
                $table->addIndex($this->createIndexFromDs($tableName, $index["Key_name"]));
            }
        }

        $fKeys = $this->getDsForeignKeysInfo($tableName);

        foreach ($fKeys as $fKey) {
            $table->addForeignKey($this->createForeignKeyFromDs($tableName, $fKey["name"]));
        }

        return $table;

    }

    private function createFieldFromDs($tableName, $fieldName)
    {
        $field = new Field();
        $field->setName($fieldName);

        $column = $this->query("SHOW COLUMNS FROM {$tableName} where Field='{$fieldName}'")->fetch();

        $field->setPKey($column["Key"] == "PRI" ? true : false);
        $field->setAutoincrement($column["Extra"] == "auto_increment" ? true : false);
        $field->setDefault($column["Default"]);
        if (!$field->isPKey()) {
            $field->setRequired($column["Null"] == "NO" ? true : false);
        }

        $tmp = explode("(", $column["Type"]);
        $type = $tmp[0];

        if (in_array($column["Type"], array("text", "mediumtext", "longtext"))) {

            if ($type == "text") {
                $size = 65535;
            } elseif ($type == "mediumtext") {
                $size = 16777215;
            } elseif ($type == "longtext") {
                $size = 4294967295;
            }
            $type = "LONGVARCHAR";
        } else {
            if (isset($tmp[1])) {
                $size = str_replace(")", "", $tmp[1]);
            } else {
                $size = null;
            }
        }
        if ($type == "int") {
            $type = "integer";
        }


        $field->setType($type);
        $field->setSize($size);

        return $field;

    }


    private function createIndexFromDs($tableName, $indexName)
    {
        $dsIndex = $this->query("SHOW INDEXES FROM {$tableName} where Key_name='{$indexName}'")->fetchAll();

        $index = new Index();
        $index->setName($indexName);
        $index->setType($dsIndex[0]["Index_type"]);

        foreach ($dsIndex as $element) {
            $index->addFieldName($element["Column_name"]);
        }

        return $index;

    }

    private function getDsForeignKeysInfo($tableName)
    {
        $result = $this->query("SHOW CREATE TABLE `{$tableName}`")->fetch();
        $code = $result["Create Table"];
        $info = array();

        $tmp = explode("\n", $code);
        foreach ($tmp as $line) {
            if (strpos($line, "CONSTRAINT") !== false) {
                $line = str_replace(array("FOREIGN KEY", ""), array("FOREIGN_KEY"), $line);
                $matches = $matches2 = $matches3 = array();
                preg_match_all("/`(.+?)`/", $line, $matches);

                preg_match_all("/ON DELETE (.+?) ON/", $line, $matches2);
                preg_match_all("/ON UPDATE (.+?)$/", $line, $matches3);


                $info[] = array(
                    "name" => $matches[1][0],
                    "local_field" => $matches[1][1],
                    "reference_table" => $matches[1][2],
                    "reference_field" => $matches[1][3],
                    "on_delete" => isset($matches2[1][0]) ? $matches2[1][0] : '',
                    "on_update" => isset($matches3[1][0]) ? $matches3[1][0] : '',
                );
            }
        }
        return $info;
    }

    private function createForeignKeyFromDs($tableName, $fKeyName)
    {
        $info = $this->getDsForeignKeysInfo($tableName);
        $keyInfo = null;
        foreach ($info as $fKey) {
            if ($fKey["name"] == $fKeyName) {
                $keyInfo = $fKey;
            }
        }

        $fKey = new ForeignKey();
        $fKey->setName($keyInfo["name"]);
        $fKey->setForeignTableName($keyInfo["reference_table"]);

        $fKeyReferece = new ForeignKeyReference();
        $fKeyReferece->setLocalFieldName($keyInfo["local_field"]);
        $fKeyReferece->setForeignFieldName($keyInfo["reference_field"]);
        $fKey->addReference($fKeyReferece);

        $fKey->setOnDelete($keyInfo["on_delete"]);
        $fKey->setOnUpdate($keyInfo["on_update"]);

        return $fKey;
    }

}

?>