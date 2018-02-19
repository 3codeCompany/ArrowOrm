<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 23.05.2017
 * Time: 11:00
 */

namespace Arrow\ORM\Extensions;


use Arrow\Access\Models\AccessGroup;
use Arrow\Access\Models\User;
use Arrow\ORM\Exception;
use Arrow\ORM\Schema\Connection;
use Arrow\ORM\Schema\Field;
use Arrow\ORM\Schema\ISchemaTransformer;
use Arrow\ORM\Schema\Schema;
use Arrow\ORM\Schema\Table;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use function in_array;
use const PHP_EOL;
use function strtolower;


class OrmGraphQLSchema
{

    protected $accessPolicy = [];

    public function __construct()
    {

    }

    /**
     * @param null $accessPolicy
     */
    public function addAccessPolicy(callable $accessPolicy)
    {
        $this->accessPolicy[] = $accessPolicy;
        return $this;
    }


    protected function getCriteriaInputType()
    {
        return new InputObjectType([
            'name' => 'Criteria',
            'description' => 'Criteria',
            'fields' => [
                'findByKey' => [
                    'type' => Type::int(),

                ],
                'order' => [
                    'type' => Type::string(),

                ],
                'index' => [
                    'type' => Type::int(),

                ],
                'limit' => [
                    'type' => Type::int(),

                ],
            ]

        ]);
    }

    protected function applyAccessPolicy($criteria)
    {
        if (!empty($this->accessPolicy)) {
            foreach ($this->accessPolicy as $ac) {
                $ac($criteria);
            }
        } else {
            throw new Exception("Add access policy");
        }
    }

    protected function resolve($class, $root, $columns, $connection)
    {

        $criteria = !$connection ? $class::get() : $root->{"_conn" . ucfirst($connection) . "Criteria"}();
        $criteria->setColumns(array_intersect($class::getFields(), array_keys($columns)));

        $this->applyAccessPolicy($criteria);

        return $criteria->find();
    }
}

class GraphQLSchemaGenerator implements ISchemaTransformer
{
    protected $target;

    protected $registred = [];
    protected $processedTables = [];


    public function __construct($targetFile)
    {
        $this->target = $targetFile;
    }

    public function registerClass($class, $alias = false, $listAlias = false)
    {
        if ($class[0] != "\\") {
            $class = "\\" . $class;
        }
        $this->registred[$class] = [$alias, $listAlias];
    }


    public function transform(Schema $schema)
    {

        $str = "<?php" . PHP_EOL;
        $str .= "

        namespace ORM;
        use GraphQL\Schema;
        use GraphQL\Type\Definition\EnumType;
        use GraphQL\Type\Definition\InterfaceType;
        use GraphQL\Type\Definition\NonNull;
        use GraphQL\Type\Definition\ObjectType;
        use GraphQL\Type\Definition\InputObjectType;
        use GraphQL\Type\Definition\ResolveInfo;
        use GraphQL\Type\Definition\Type;" . PHP_EOL;
        $str .= "class GraphQLSchema extends \Arrow\ORM\Extensions\OrmGraphQLSchema{
                public  function build(){" . PHP_EOL;

        $str .= $this->addCriteriaClass();

        foreach ($schema->getTables() as $table) {


            if (array_key_exists("" . $table->getClass(), $this->registred)) {

                $str .= $this->loadConnections($table->getConnections());

                $str .= $this->processClass($table);
            }
        }
        $str .= " \$queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [";

        foreach ($schema->getTables() as $table) {
            if (isset($this->registred["" . $table->getClass()])) {

                $str .= $this->registerTable($table);

            }

        }

        $str .= "]]);";

        $str .= "  return new Schema(['query' => \$queryType]);" . PHP_EOL;
        $str .= "}}";


        file_put_contents($this->target, $str);

    }

    /**
     * @param $connections Connection[]
     * @return string
     */
    public function loadConnections($connections)
    {
        $str = "";
        foreach ($connections as $conn) {
            foreach ($conn->tables as $index => $connTable) {
                if ($index == count($conn->tables) - 1) {
                    $str .= $this->loadConnections($connTable->getTable()->getConnections());
                    $str .= $this->processClass($connTable->getTable());
                }
            }
        }
        return $str;
    }

    public function addCriteriaClass()
    {
        return "\$CriteriaType = \$this->getCriteriaInputType();" . PHP_EOL;
    }

    public function processClass(Table $table)
    {
        if (isset($this->processedTables[$table->getTableName()])) {
            return;
        }

        $this->processedTables[$table->getTableName()] = 1;

        $str = "\${$table->getTableName()}Type = new ObjectType([";
        $str .= "'name' => '{$table->getClassName()}',";
        $str .= "'description' => '{$table->getClassName()}',";
        $str .= "'fields' => [";
        foreach ($table->getFields() as $field) {
            $str .= $this->processField($field);
        }
        foreach ($table->getConnections() as $connection) {
            foreach ($connection->tables as $index => $connTable) {
                if ($index == count($connection->tables) - 1) {
                    $str .= $this->registerTable($connTable->getTable(), $connection);
                }

            }
        }

        $str .= "],";

        $str .= "]);";

        return $str;
    }


    public function registerTable(Table $table, $connection = false)
    {
        $className = trim($table->getClass(), "\\");
        $str = "";
        if ($connection == false) {
            $str .= "
             '{$table->getClassName()}' => [
                    'type' => \${$table->getTableName()}Type,
                    'args' => [
                        'id' => [
                            'name' => 'id',
                            'type' => Type::nonNull(Type::int())
                        ]
                    ],
                    'resolve' => function (\$root, \$args) {
                        return \\{$className}::get()->findByKey(\$args['id']);
                    },
                ],";
        }
        $str .= "                
             '{$table->getClassName()}s' => [
                    'type' => Type::listOf(\${$table->getTableName()}Type),
                    'args' => [
                        'criteria' => [
                            'name' => 'criteria',
                            'type' => \$CriteriaType
                        ],
                    ],
                    'resolve' => function (\$root, \$args, \$context, \$info) {
                        return \$this->resolve(
                            '\\{$className}',
                            \$root,
                            \$info->getFieldSelection(),
                            '" . ($connection ? $connection->name : '') . "'
                        );
                    },
                ],
                ";
        return $str;
    }


    public function processField(Field $field)
    {
        $intTypes = ["int", "integer"];
        $floatTypes = ["float", "double"];

        $test = strtolower($field->getType());
        if (in_array($test, $intTypes)) {
            $type = "Type::int()";
        } elseif (in_array($test, $floatTypes)) {
            $type = "Type::float()";
        } else {
            $type = "Type::string()";
        }
        return "'{$field->getName()}' => [
                    'type' => {$type}
                ],";
    }

}