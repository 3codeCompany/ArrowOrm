<?php

namespace Arrow\ORM\Connectors\Mysql;

use Arrow\ORM\Exception;
use Arrow\ORM\Schema\AbstractMismatch;
use Arrow\ORM\Schema\AbstractSynchronizer;
use Arrow\ORM\Schema\DatasourceMismatch;
use Arrow\ORM\Schema\Field;
use Arrow\ORM\Schema\FieldTypes;
use Arrow\ORM\Schema\Index;
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

        //SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE, COLUMN_KEY,EXTRA FROM `COLUMNS` where TABLE_SCHEMA="orm"

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
                $sql = $this->createTable($schema, $mismatch->element);
            }

            //field
            if ($mismatch->element instanceof Field) {
                if ($mismatch->type == SchemaMismatch::NOT_EXISTS) {
                    if ($mode == self::MODE_SCHEMA_TO_DS || $mode == self::MODE_ALL) {
                        //creating field on datasource
                        $sql = $this->createField($schema, $mismatch->parentElement, $mismatch->element);
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
                        $sql = $this->updateField($schema, $mismatch->parentElement, $mismatch->element);
                    } elseif ($mode == self::MODE_DS_TO_SCHEMA) {
                        $this->updateFieldFromDs($mismatch->parentElement, $mismatch->element);
                    }
                }
                if ($mismatch->type == SchemaMismatch::NAME_NOT_EQUALS) {
                    if ($mode == self::MODE_SCHEMA_TO_DS || $mode == self::MODE_ALL) {
                        $sql = $this->updateField($schema, $mismatch->parentElement, $mismatch->element, true);
                    } elseif ($mode == self::MODE_DS_TO_SCHEMA) {
                        $mismatch->element->setName($mismatch->element->getOldName());
                    }
                }
            }

            if ($mismatch->element instanceof Index) {
                if ($mismatch->type == SchemaMismatch::NOT_EXISTS) {
                    if ($mode == self::MODE_SCHEMA_TO_DS || $mode == self::MODE_ALL) {
                        //creating field on datasource
                        $sql = $this->createIndex($mismatch->parentElement, $mismatch->element);
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
     * @throws Exception
     */
    private function createTable(Schema $schema, Table $table)
    {
        $sql = "CREATE TABLE {$table->getTableName()}(";
        $count = count($table->getFields());
        foreach ($table->getFields() as $index => $field) {
            $sql .= $this->getFieldCreationCode($table, $field);
            if ($index + 1 < $count) {
                $sql .= ",\n";
            }
        }
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=" . $schema->getEncoding();

        $this->update($sql);
        return $sql;
    }

    private function checkTableFields(Schema $schema, Table $table)
    {

        $mismatches = array();

        $db = $this->query("select DATABASE()")->fetchColumn();

        $q = "
          SELECT 
            TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT, 
            IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_SET_NAME, COLLATION_NAME, 
            COLUMN_TYPE, COLUMN_KEY,EXTRA FROM information_schema.`COLUMNS` 
          where 
            TABLE_SCHEMA='{$db}'
            and TABLE_NAME='{$table->getTableName()}'
          ";


        //$statement = $this->query("SHOW COLUMNS FROM `{$table->getTableName()}`");
        //$columns = $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $columns = $this->query($q)->fetchAll(\PDO::FETCH_ASSOC);
        $tableFields = $table->getFields();

        //iterating over schema fields ( only check if exists, rest of checks  are in the next loop )
        foreach ($tableFields as $field) {
            $exists = false;

            foreach ($columns as $column) {
                if ($field->getName() == $column["COLUMN_NAME"]) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $mismatches[] = new SchemaMismatch($schema, $table, $field, SchemaMismatch::NOT_EXISTS, ["Field not exists"]);
            }
        }

        $i = 0;

        //iterating over db columns
        foreach ($columns as $column) {

            if (!isset($tableFields[$i]) || $tableFields[$i]->getName() != $column["COLUMN_NAME"]) {
                $exists = false;
                if (isset($tableFields[$i])) {
                    //checks that field exists in db
                    foreach ($columns as $tmp) {
                        if ($tableFields[$i] == $tmp["COLUMN_NAME"]) {
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
                        if ($field->getName() == $column["COLUMN_NAME"]) {
                            $exists = true;
                            $existsField = $field;
                            break;
                        }
                    }


                    if ($exists) {
                        $mismatches[] = new SchemaMismatch($schema, $table, $existsField, SchemaMismatch::INDEX_NOT_EQUALS);
                    } else {
                        $mismatches[] = new DatasourceMismatch($schema, $table->getTableName(), $column["COLUMN_NAME"], DatasourceMismatch::ELEMENT_TYPE_FIELD, SchemaMismatch::NOT_EXISTS);
                        $i--;
                    }
                }

            } else {

                $result = $this->checkField($schema, $table, $tableFields[$i], $column);
                if ($result !== true) {
                    $mismatches[] = new SchemaMismatch($schema, $table, $tableFields[$i], SchemaMismatch::NOT_EQUALS, ["info" => $result]);
                }
            }

            $i++;
        }


        return $mismatches;

    }


    private function checkField(Schema $schema, Table $table, Field $field, $column)
    {

        $pass = true;
        $info = "";


        if ($field->isPKey() && $column["COLUMN_KEY"] != "PRI") {
            $pass = false;
            $info = "Field isn't primary key";
        }
        if ($field->isAutoincrement() && $column["EXTRA"] != "auto_increment") {
            $pass = false;
            $info = "Field isn't autoincrement";
        }
        if ($field->getDefault() && $field->getDefault() != $column["COLUMN_DEFAULT"]) {
            if (!($field->getDefault() == "ORM:NOW" && $column["COLUMN_DEFAULT"] == "CURRENT_TIMESTAMP")) {
                $pass = false;
            } elseif ($field->getDefault() != "ORM:NOW") {
                $pass = false;
            }
            $info = "Field has wrong default value `{$column["COLUMN_DEFAULT"]}` instead `$field->getDefault()`";
        }

        //$field->isRequired() - to nie sprawdzenie dla bazy

        if ($field->isNullable() && $column["IS_NULLABLE"] != "YES") {
            $pass = false;
            $info = "Field should be nullable";
        }
        if (!$field->isNullable() && $column["IS_NULLABLE"] != "NO") {
            $pass = false;
            $info = "Field shouldn't be nullable";
        }


        //longvarchar is changed for text and it not posses size
        if ($field->getSize() && $field->getType() != "LONGVARCHAR") {
            $tmp = explode("(", $column["COLUMN_TYPE"]);
            if (isset($tmp[1])) {
                $size = str_replace(")", "", $tmp[1]);
                if ($field->getSize() != $size) {
                    $pass = false;
                    $info = "Field have wrong size `{$size}` instead `{$field->getSize()}``";
                }
            }
        }

        $types = array("INTEGER" => "INT", "LONGVARCHAR" => "TEXT");
        $testType = strtolower(str_replace(array_keys($types), $types, $field->getType()));

        if ($testType == "text" && !in_array($column["DATA_TYPE"], array("text", "mediumtext", "longtext"))) {
            $pass = false;
            $info = "Field have wrong type `{$column["DATA_TYPE"]}` instead `{$field->getType()}`";
        } elseif ($testType != "text" && $testType != $column["DATA_TYPE"]) {
            $pass = false;
            $info = "Field have wrong type `{$column["DATA_TYPE"]}` instead `{$field->getType()}` xxx";
        }

        if ($testType == FieldTypes::ENUM) {
            $val = "enum('" . implode("','", array_keys($field->getMetaData()->getOptions())) . "')";
            if ($val != $column["COLUMN_TYPE"]) {
                $pass = false;
                $info = "Field have wrong enum type `{$column["COLUMN_TYPE"]}` instead `{$val}`";
            }

        }

        if ($column["CHARACTER_SET_NAME"]) {
            $tmp = $column["CHARACTER_SET_NAME"] . " COLLATE " . $column["COLLATION_NAME"];

            $encoding = $schema->getEncoding();
            if ($table->getEncoding()) {
                $encoding = $table->getEncoding();
            }
            if ($field->getEncoding()) {
                $encoding = $field->getEncoding();
            }

            $encoding = "utf8 COLLATE {$encoding}";

            if (strtolower($tmp) != strtolower($encoding)) {
                $pass = false;
                $info = "Field have wrong character set type `{$tmp}` instead `{$encoding}`";
            }
        }

        if (!$pass) {
            return $info;
        }

        return $pass;
    }

    private function checkIndexes(Schema $schema, Table $table)
    {

        $mismatches = [];

        $query = "SHOW INDEXES  FROM `{$table->getTableName()}`";
        $dsIndexes = $this->query($query)->fetchAll(\PDO::FETCH_ASSOC);


        $indexColumns = [];

        foreach ($table->getIndexes() as $index) {


            $indexColumns[$index->getName()] = [];
            foreach ($dsIndexes as $dsIndex) {

                if ($index->getName() == $dsIndex["Key_name"]) {

                    $indexColumns[$index->getName()][] = $dsIndex["Column_name"];
                }
            }
            if (count($indexColumns[$index->getName()]) == 0) {
                $mismatches[] = new SchemaMismatch($schema, $table, $index, SchemaMismatch::NOT_EXISTS);
            }
            $reduced = array_reduce($index->getColumns(), function ($p, $c) {
                $p[] = $c["column"];
                return $p;
            }, []);
            $diff = array_diff($indexColumns[$index->getName()], $reduced);
            if (count($diff) > 0) {
                $mismatches[] = new SchemaMismatch($schema, $table, $index, SchemaMismatch::NOT_EQUALS);
            } else {

            }

        }


        foreach ($dsIndexes as $dsIndex) {
            if ($dsIndex["Key_name"] != "PRIMARY") {
                $exists = false;
                foreach ($table->getIndexes() as $index) {
                    if ($index->getName() == $dsIndex["Key_name"]) {
                        $exists = true;
                    }
                }
                if (!$exists) {
                    $mismatches[] = new DatasourceMismatch($schema, $table->getTableName(), $dsIndex["Key_name"], DatasourceMismatch::ELEMENT_TYPE_INDEX, SchemaMismatch::NOT_EXISTS);
                }

            }
        }

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

    private function createField(Schema $schema, Table $table, Field $field)
    {
        $fields = $table->getFields();
        $index = array_search($field, $fields);
        if ($index == 0) {
            $prev = "FIRST";
        } else {
            $prev = "AFTER `{$fields[$index - 1]->getName()}`";
        }

        $sql = $this->getFieldCreationCode($schema, $table, $field);
        $sql = "ALTER TABLE  `{$table->getTableName()}` ADD  $sql  {$prev}";
        $this->update($sql);

        return $sql;
    }

    private function updateField(Schema $schema, Table $table, Field $field, $oldName = false)
    {
        $fields = $table->getFields();
        $index = array_search($field, $fields);
        if ($index == 0) {
            $prev = "FIRST";
        } else {
            $prev = "AFTER `{$fields[$index-1]->getName()}`";
        }

        $sql = $this->getFieldCreationCode($schema, $table, $field);
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

    private function createIndex(Table $table, Index $index)
    {
        $tmp = [];
        foreach ($index->getColumns() as $col) {
            $tmp[] = "`{$col["column"]}`" . ($col["size"] ? "(${col["size"]})" : "");

        }
        $sql = "ALTER TABLE `{$table->getTableName()}` ADD " . ($index->getType() == "UNIQUE" ? "UNIQUE " : "") . " KEY `{$index->getName()}`(" . implode(",", $tmp) . ") USING " . $index->getKind();

        $this->update($sql);
        return $sql;

    }

    private function updateIndex(Table $table, Index $index)
    {
        throw new Exception("Not implemented");
    }

    private function deleteIndex(Table $table, Index $index)
    {

        throw new Exception("Not implemented");
    }


    private function translateType($type)
    {
        $types = array("INTEGER" => "INT", "LONGVARCHAR" => "TEXT");
        return str_replace(array_keys($types), $types, $type);
    }


    private function getFieldCreationCode(Schema $schema, Table $table, Field $field)
    {

        $encoding = $schema->getEncoding();
        if ($table->getEncoding()) {
            $encoding = $table->getEncoding();
        }
        if ($field->getEncoding()) {
            $encoding = $field->getEncoding();
        }

        $sql = "`{$field->getName()}` {$this->translateType($field->getType())}";

        if ($field->getType() == FieldTypes::ENUM) {
            $options = $field->getMetaData()->getOptions();
            $sql .= "('" . implode("','", array_keys($options)) . "')";
        }

        if ($field->getSize()) {
            $sql .= "({$field->getSize()})";
        }

        if (in_array(strtolower($field->getType()), ["varchar", "longvarchar", "text", "char"])) {
            $sql .= " CHARACTER SET utf8 COLLATE {$encoding}";
        }

        if (!$field->isNullable()) {
            $sql .= " NOT NULL";
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
            $d = $field->getDefault();
            $def = is_int($d) ? $d : "'{$d}'";
            if ($d == "ORM:NOW") {
                if ($field->getType() == FieldTypes::DATETIME) {
                    $def = "NOW()";
                } else if ($field->getType() == FieldTypes::DATE) {
                    $def = "CURDATE()";
                } else {
                    throw new Exception("Unknows ORM:NOW default value for `{$field->getType()}` type");
                }
            }
            $sql .= " DEFAULT  {$def}";
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