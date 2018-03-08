<?php

namespace Arrow\ORM\DB;

use Arrow\ORM\Persistent\Criteria;
use Arrow\ORM\Schema\AbstractSynchronizer;
use Psr\Log\LoggerAwareInterface;

/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 29.10.12
 * Time: 22:03
 * To change this template use File | Settings | File Templates.
 */
interface DBInterface extends LoggerAwareInterface
{
    public function select(string $table, Criteria $criteria);

    public function insert(string $table, array $data, string $pKeyField): string;

    public function update(string $table, array $data, Criteria $criteria);

    public function delete(string $table, Criteria $criteria);

    public function getDB();

    public function getSynchronizer(): AbstractSynchronizer;

}
