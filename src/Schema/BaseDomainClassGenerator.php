<?php

namespace Arrow\ORM\Schema;

use function str_replace;

/**
 * Generator used to create domain objects base classes
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class BaseDomainClassGenerator implements ISchemaTransformer
{
    /**
     * Line separator (ending) constant
     *
     * @var string
     */
    const ls = "\n";
    /**
     * Dir whitch will be used to place generated classes
     *
     * @var string
     */
    public $targetDir = "../cache/";

    public function transform(Schema $schema)
    {
        $this->generate($schema);
    }


    /**
     * Generate all schema classes ( from tables )
     *
     * @param Schema $schema
     */
    public function generate(Schema $schema)
    {
        foreach ($schema->getTables() as $table) {
            $namespace = str_replace("\\", "_", $table->getNamespace());

            file_put_contents($this->targetDir . DIRECTORY_SEPARATOR . "ORM" . ($namespace ? "_" . $namespace : "") . "_" . $table->getClassName() . ".php", $this->generateClass($table, $schema));
        }
    }

    /**
     * Generate class code
     *
     * @param Table $table
     *
     * @return string
     */
    public function generateClass(Table $table, Schema $schema)
    {
        $namespace = str_replace("\\", "_", $table->getNamespace());
        $className = "ORM" . ($namespace ? "_" . $namespace : "") . "_{$table->getClassName()}";



        $str = "";
        $this->pl("<?php namespace Arrow\ORM;", $str);

        $criteriaMethods = "";
        $methods = [];
        foreach ($table->getFields() as $field) {
            $tmp = lcfirst(str_replace(" ", "", ucwords(str_replace("_", " ", $field->getName()))));
            if (isset($methods[$tmp])) {
                continue;
            }
            $methods[$tmp] = 1;

            $criteriaMethod = <<< EOT
                /**
                 * @return {$className}_Criteria
                 * @throws Exception
                 */
EOT;

            $this->pl($criteriaMethod, $criteriaMethods);
            $this->pl("public function _" . $tmp . "( \$value, \$condition = self::C_EQUAL ){", $criteriaMethods, 1);
            $this->pl("return \$this->c('" . $field->getName() . "', \$value, \$condition);", $criteriaMethods, 2);
            $this->pl("}", $criteriaMethods, 1);
        }

        $mapping = $schema->getClassMapping($table->getClass());
        $returnedClass = $mapping?$mapping:"\\{$table->getNamespace()}\\{$table->getClassName()}";




        $criteria = <<< EOT
use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Persistent\PersistentObject;
/**
 * Class {$className}_Criteria
 * @package Arrow\ORM
 * @method \\Arrow\\ORM\\Persistent\\DataSet|\\{$table->getNamespace()}\\{$table->getClassName()}[] find()
 * @method {$returnedClass} findByKey()
 * @method {$returnedClass} findFirst()
 */
class {$className}_Criteria extends Criteria {
    $criteriaMethods
}


EOT;
        $this->pl($criteria, $str);

        $this->pl("class $className extends PersistentObject /*{$table->getBaseClass()}*/{", $str);

        $tmpFields = array();
        $tmpFieldsRequired = array();
        $pkey = null;
        foreach ($table->getFields() as $field) {
            $tmpFields[] = $field->getName();
            if ($field->isPKey()) {
                $pkey = $field->getName();
            }
            if ($field->isRequired()) {
                $tmpFieldsRequired[] = $field->getName();
            }

        }

        foreach ($table->getFields() as $field) {
            $this->pl("const F_" . strtoupper($field->getName()) . " = '" . $field->getName() . "';", $str, 1);
        }

        $this->pl("", $str, 0);
        $this->pl("protected static \$PKeyField = '" . $pkey . "';", $str, 1);
        $this->pl("protected static \$fields = array( '" . implode("', '", $tmpFields) . "' );", $str, 1);
        if (!empty($tmpFieldsRequired)) {
            $this->pl("protected static \$requiredFields = array( '" . implode("', '", $tmpFieldsRequired) . "' );", $str, 1);
        } else {
            $this->pl("protected static \$requiredFields = array();", $str, 1);
        }

        $tmpForeignKeys = $table->getForeignKeys();
        if (!empty($tmpForeignKeys)) {
            $tmp = array();
            foreach ($tmpForeignKeys as $fKey) {
                $reference = $fKey->getReferences();
                $fNamespace = $fKey->foreignTable->getNamespace();
                $fNamespace = $fNamespace ? $fNamespace . "\\" : "";
                $tmp[] = "'" . $fNamespace . $fKey->foreignTable->getClassName() . "'=> array( '" . $reference[0]->getLocalFieldName() . "' => '" . $reference[0]->getForeignFieldName() . "' )";
            }
            $this->pl("protected static \$foreignKeys = array( " . implode(", ", $tmp) . " );", $str, 1);
        } else {
            $this->pl("protected static \$foreignKeys = array();", $str, 1);
        }

        $trackers = $table->getTrackers();
        if (!empty($trackers)) {
            $this->pl("protected static \$trackers = array( '" . implode("', '", $trackers) . "' );", $str, 1);
        } else {
            $this->pl("protected static \$trackers = array();", $str, 1);
        }
        $extensions = $table->getExtensions();
        if (!empty($extensions)) {
            $this->pl("protected static \$extensions = array( '" . implode("', '", $extensions) . "' );", $str, 1);
        } else {
            $this->pl("protected static \$extensions = array(  );", $str, 1);
        }
        $this->pl("protected static \$table = \"{$table->getTableName()}\";", $str, 1);
        $this->pl("protected static \$class = '" . ($namespace ? $table->getNamespace() . "\\" : "") . "{$table->getClassName()}';", $str, 1);


        $this->pl("protected  \$pKey = null;", $str, 1);

        $methods = [];
        foreach ($table->getFields() as $field) {
            $tmp = lcfirst(str_replace(" ", "", ucwords(str_replace("_", " ", $field->getName()))));
            if (isset($methods[$tmp])) {
                continue;
            }
            $methods[$tmp] = 1;

            $this->pl("public function _" . $tmp . "( \$newVal = false ){ if(\$newVal !== false){   \$this->setValue('" . $field->getName() . "',  \$newVal ); }  return \$this->data['" . $field->getName() . "']; }", $str, 1);
        }


        //$this->pl("}", $str);


        $criteriaMethod = <<< EOT
                /**
                 * @return {$className}_Criteria
                 * @throws Exception
                 */
                public static function get(){
                    return {$className}_Criteria::query(static::class);
                }
EOT;

        $this->pl($criteriaMethod, $str);


        foreach ($table->getConnections() as $connection) {

            foreach (["dataset", "criteria"] as $type) {

                $elements = $connection->getElements();
                $fName = "_conn_" . ucfirst($connection->getName());
                $fName = $type == "criteria" ? $fName . "Criteria" : $fName;
                $lastTable = $elements[count($elements) - 1]->getTable();
                $_namespace = str_replace("\\", "_", $lastTable->getNamespace());
                $_className = "ORM" . ($_namespace ? "_" . $_namespace : "") . "_{$lastTable->getClassName()}";

                $_className = $type == "criteria" ? $_className . "_Criteria" : $_className . "[]";
                $x = "
                /**
                 * @return {$_className}
                 */
                 ";


                $this->pl($x, $str);

                $this->pl("public function " . $fName . "(" . ($type == "criteria" ? "" : "\$columns = []") . "){ ", $str, 1);
                if (count($elements) == 1) {
                    $this->pl("\$result = [ \$this->getValue('{$elements[0]->getLocal()}') ];", $str);
                }
                $length = count($elements);
                foreach ($elements as $index => $connTable) {

                    $isLast = $index == $length - 1;
                    if (!$isLast) {
                        $nextColumn = $elements[$index + 1]->getLocal();
                        $x = "\$crit = {$connTable->getTable()->getClass()}::get()->c('{$connTable->getForeign()}', \$this->getValue('{$connTable->getLocal()}'));";
                        $this->pl($x, $str);
                        foreach ($connTable->getAdditionalConditions() as $condition) {
                            $this->pl("\$crit->c('{$condition["field"]}', '{$condition["value"]}');", $str);
                        }
                        $x = "\$result = \$crit->findAsFieldArray(  '{$nextColumn}' );";
                        $this->pl($x, $str);

                    } else {
                        $x = "\$crit = {$connTable->getTable()->getClass()}::get()->c('{$connTable->getForeign()}', \$result, Criteria::C_IN);";
                        $this->pl($x, $str);

                        foreach ($connTable->getAdditionalConditions() as $condition) {
                            $this->pl("\$crit->c('{$condition["field"]}', '{$condition["value"]}');", $str);
                        }

                        if ($type == "criteria") {
                            $x = "\$result = \$crit;";
                        } else {
                            $this->pl("if(!empty(\$columns)){ \$crit->setColumns(\$columns); }", $str);
                            $x = "\$result = \$crit->find();";
                        }

                        $this->pl($x, $str);
                    }

                }


                $this->pl("return \$result;", $str);
                $this->pl("}", $str);
            }
        }


        $this->pl("}", $str);


        return $str;
    }

    /**
     * Helper to generate line of code with ident
     *
     * @param string $line
     * @param string $string reference to string to place new line
     * @param int $ident number of tabulators before line
     */
    private function pl($line, &$string, $ident = 0)
    {
        $string .= str_repeat("\t", $ident) . $line . self::ls;
    }


}

?>
