<?php

namespace Arrow\ORM\DB;

use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Schema\AbstractSynchronizer;

/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 29.10.12
 * Time: 22:03
 * To change this template use File | Settings | File Templates.
 */
interface DBInferface
{
    public function select(string $table, Criteria $criteria);

    public function insert(string $table, array $data);

    public function update(string $table, array $data, Criteria $criteria);

    public function delete(string $table, Criteria $criteria);

    public function getSynchronizer(): AbstractSynchronizer;

}
