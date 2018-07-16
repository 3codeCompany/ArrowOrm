<?php

use Arrow\ORM\Schema\FieldTypes;

class YamlSchemaReaderTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var Arrow\ORM\Schema\Schema
     */
    protected $schema;


    protected function _before()
    {
        $reader = new \Arrow\ORM\Schema\Readers\YamlSchemaReader();
        $this->schema = $reader->readSchemaFromFile("./tests/assets/schema/schema.yaml");

    }

    protected function _after()
    {
    }


    public function testEncodingRead()
    {
        $this->assertEquals($this->schema->getEncoding(), "utf8 COLLATE utf8_unicode_ci");
    }

    public function testTablesRead()
    {
        $this->assertEquals(count($this->schema->getTables()), 2);

        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");
        $this->assertEquals($table1->getTableName(), "orm_posts");

        $table2 = $this->schema->getTableByClass("ORM\Tests\Objects\User");
        $this->assertEquals($table2->getTableName(), "orm_users");
    }

    public function testFieldsRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");
        $this->assertEquals(count($table1->getFields()), 7);

        $table2 = $this->schema->getTableByClass("ORM\Tests\Objects\User");
        //8 with extension
        $this->assertEquals(count($table2->getFields()), 8);

    }

    public function testFieldAutoincremenRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("id")->isAutoincrement(), true);
        $this->assertEquals($table1->getFieldByName("created")->isAutoincrement(), false);
    }

    public function testFieldNulable()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("date")->isNullable(), false);
        $this->assertEquals($table1->getFieldByName("content")->isNullable(), true);
    }

    public function testFieldTypeRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("id")->getType(), FieldTypes::INT);
        $this->assertEquals($table1->getFieldByName("created")->getType(), FieldTypes::DATETIME);
        $this->assertEquals($table1->getFieldByName("type")->getType(), FieldTypes::ENUM);
        $this->assertEquals($table1->getFieldByName("title")->getType(), FieldTypes::VARCHAR);
        $this->assertEquals($table1->getFieldByName("content")->getType(), FieldTypes::TEXT);
        $this->assertEquals($table1->getFieldByName("date")->getType(), FieldTypes::DATE);

    }

    public function testFieldPKeyRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("id")->isPKey(), true);
        $this->assertEquals($table1->getFieldByName("created")->isPKey(), false);
    }

    public function testFieldDefaultRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("created")->getDefault(), "ORM:NOW");
        $this->assertEquals($table1->getFieldByName("type")->getDefault(), "1");
        $this->assertEquals($table1->getFieldByName("title")->getDefault(), false);
    }


    public function testFieldSizeRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("created")->getSize(), false);
        $this->assertEquals($table1->getFieldByName("user_id")->getSize(), 11);
        $this->assertEquals($table1->getFieldByName("title")->getSize(), 500);
        $this->assertEquals($table1->getFieldByName("content")->getSize(), false);
    }

    public function testFieldRequiredRead()
    {
        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("user_id")->isRequired(), true);
        $this->assertEquals($table1->getFieldByName("content")->isRequired(), false);
    }

    public function testFieldMetaRead()
    {

        $table1 = $this->schema->getTableByClass("ORM\Tests\Objects\Post");

        $this->assertEquals($table1->getFieldByName("user_id")->getMetaData()->getLabel(), "User conn");
        $this->assertEquals($table1->getFieldByName("type")->getMetaData()->getOptions(), [
            1 => "Standard",
            2 => "Important",
            3 => "Very important",
        ]);

        $this->assertEquals($table1->getFieldByName("date")->getMetaData(), null);

    }

    public function testTableConnectionsRead()
    {
        $table = $this->schema->getTableByClass("ORM\Tests\Objects\Post");
        $connections = $table->getConnections();
        $this->assertEquals(count($connections), 1);


        $this->assertEquals($connections[0]->getName(), "user");
        $this->assertEquals($connections[0]->isUsingDBFKeys(), false);


        $this->assertEquals(count($connections[0]->getElements()), 1);

        $element = $connections[0]->getElements()[0];

        $this->assertEquals($element->getTable()->getClass(), "\ORM\Tests\Objects\User");
        $this->assertEquals($element->getLocal(), "user_id");
        $this->assertEquals($element->getForeign(), "id");


        $this->assertEquals($element->getAdditionalConditions(), [[
            "field" => "id", "value" => 5, "condition" => "=="
        ]]);


    }

    public function testIndexesRead()
    {
        $table = $this->schema->getTableByClass("ORM\Tests\Objects\Post");
        $indexes = $table->getIndexes();

        $this->assertEquals(count($indexes), 1);
        $this->assertEquals($indexes[0]->getName(), "test");
        $this->assertEquals($indexes[0]->getColumns(), [
            ["column" => "type", "size" => "80"],
            ["column" => "title", "size" => "40"]
        ]);
        $this->assertEquals($indexes[0]->getType(), "index");
    }


    public function testExtensionTo()
    {
        $table = $this->schema->getTableByClass("ORM\Tests\Objects\User");

        $this->assertEquals(count($table->getFields()), 8);

    }

}