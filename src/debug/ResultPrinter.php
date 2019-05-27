<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 05.02.14
 * Time: 07:29
 */

namespace Arrow\ORM\Debug;

use Arrow\ORM\Persistent\DataSet;
use Arrow\ORM\Persistent\PersistentObject;

class ResultPrinter {

    public static function asTable(DataSet $result){
        ob_start();
        if($result instanceof DataSet){

            print "<h3> class: <b>".$result->getClass()."</b> count: ".$result->count()."</h3>";
            print "<table class='table' >";

            if($result->count()){

            }

            $first = true;
            foreach($result as  $row){
                if($first){
                    print "<thead>";
                    foreach( $row->getData() as $column => $data){
                        print "<th>$column</th>";
                    }
                    print "</thead>";
                    $first = false;
                }
                self::objectAsRow($row);
            }

            print "</table>";
        }

        $str = ob_get_contents();
        ob_end_clean();
        return $str;

    }

    private static function objectAsRow(PersistentObject $object){
        print "<tr>";
        foreach( $object->getData() as $key => $val  ){
            print "<td>".$val."</td>";
        }

        print "</tr>";
    }

} 