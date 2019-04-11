<?php
/**
 * Created by PhpStorm.
 * User: artur.kmera
 * Date: 18.10.2018
 * Time: 15:50
 */

namespace Arrow\ORM\Schema;


class DynamicClassGenerator
{

    /**
     * @var \PDO
     */
    private $db;
    private $table;
    private $className;

    static $classNum = 0;

    public function __construct(\PDO $db, $table, $className = false)
    {
        $this->db = $db;
        $this->table = $table;
        $this->className = ($className ? $className : "DynamicClass" . self::$classNum);

        self::$classNum++;
    }


    public function createClass()
    {
        $q = "SHOW COLUMNS FROM " . $this->table;
        $coulmList = $this->db->query($q)->fetchAll(\PDO::FETCH_ASSOC);
        //print_r($coulmList);

        $table = new Table();
        $table->setTable($this->table);
        $table->setClass($this->className);
        $table->setNamespace("Arrow\ORM");
        foreach ($coulmList as $entry) {
            $field = new Field();
            $field->setName($entry["Field"]);
            $table->addField($field);
        }
        $generator = new BaseDomainClassGenerator();

        $str = $generator->generateClass($table);
        $str .= "\n\n";
        $str .= "class {$this->className} extends ORM_ORM_DYNAMIC_{$this->className}{}";


        $file = ARROW_CACHE_PATH . DIRECTORY_SEPARATOR . "db" . DIRECTORY_SEPARATOR . "ORM_DYBAMIC_CLASS_" . $this->className . ".php";
        file_put_contents($file, $str);
        require_once $file;


        return $this->className;


    }
}