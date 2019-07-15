<?php

namespace Arrow\ORM\Utils;

use Arrow\ORM\DB\DBManager;

class BulkInsert
{
    private $buffor = [];
    private $bufforSize = 500;

    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function insert($data)
    {
        $this->buffor[] = $data;
        if(count($this->buffor) >= $this->bufforSize){
            $this->flush();
        }
    }

    public function flush(){
        DBManager::getDefaultRepository()->getConnectionInterface()->bulkInsert($this->model, $this->buffor);
        $this->buffor = [];

    }
}
