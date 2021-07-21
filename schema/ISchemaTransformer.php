<?php
/**
 * Created by PhpStorm.
 * User: artur
 * Date: 23.05.2017
 * Time: 11:03
 */

namespace Arrow\ORM\Schema;


interface ISchemaTransformer
{
    public function transform(Schema $schema);
}