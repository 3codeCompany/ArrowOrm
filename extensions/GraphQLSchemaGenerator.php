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
use Arrow\ORM\Schema\Field;
use Arrow\ORM\Schema\ISchemaTransformer;
use Arrow\ORM\Schema\Schema;
use Arrow\ORM\Schema\Table;

class GraphQLSchemaGenerator implements ISchemaTransformer
{
    protected $target;

    protected $registred = [];
    protected $processedTables= [];


    public function __construct($targetFile)
    {
        $this->target = $targetFile;
    }


    public function transform(Schema $schema)
    {
        $this->registred = [
            User::getClass() => ["users", "user"],
            AccessGroup::getClass() => ["users", "user"],
        ];


        $str = "<?php" . PHP_EOL;
        $str .= "
        namespace ORM;
        use GraphQL\Schema;
        use GraphQL\Type\Definition\EnumType;
        use GraphQL\Type\Definition\InterfaceType;
        use GraphQL\Type\Definition\NonNull;
        use GraphQL\Type\Definition\ObjectType;
        use GraphQL\Type\Definition\ResolveInfo;
        use GraphQL\Type\Definition\Type;" . PHP_EOL;
        $str .= "class GraphQLSchema{
                public static function build(){" . PHP_EOL;
        foreach ($schema->getTables() as $table) {

            if (array_key_exists("" . $table->getClass(), $this->registred)) {

                foreach ($table->getConnections() as $conn) {
                    foreach ($conn->tables as $index => $connTable) {
                        if ($index == count($conn->tables )- 1) {
                            $str .= $this->processClass($connTable->getTable());
                        }
                    }
                }
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


    public function processClass(Table $table)
    {
        if(isset($this->processedTables[$table->getTableName()]))
            return;

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
                if ($index == count($connection->tables )- 1) {
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
                            'description' => 'id of the object',
                            'type' => Type::nonNull(Type::id())
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
                        'index' => [
                            'name' => 'index',
                            'description' => 'index',
                            'type' => Type::int()
                        ],
                        'limit' => [
                            'name' => 'limit',
                            'description' => 'limit',
                            'type' => Type::int()
                        ],
                    ],
                    'resolve' => function (\$root, \$args, \$context, \$info) {
                    
                        \$criteria =  " . (!$connection ? "\\{$className}::get();" : "\$root->_conn" . ucfirst($connection->name) . "Criteria()") . ";
                        \$criteria->setColumns(array_intersect(\\{$className}::getFields(), array_keys(\$info->getFieldSelection())));
                        
                        if(isset(\$args['index']) && isset(\$args['limit'])){
                            \$criteria->limit(\$args['index'], \$args['limit']); 
                        }
                        return \$criteria->find();
                    },
                ],
                ";
        return $str;
    }


    public function processField(Field $field)
    {
        return "'{$field->getName()}' => [
                    'type' => Type::string(),
                    'description' => '',
                ],";
    }

}