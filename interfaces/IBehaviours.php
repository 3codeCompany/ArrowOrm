<?php namespace Arrow\ORM;
interface IBehaviour
{

    public function getAditionalFields();

    public function getAditionalIndexes();

    public function getAditionalForeignKeys();

    public function getAditionalTables();

    public function onCreate();

    public function onSave();

    public function onDelete();
}

?>