<?php
namespace Arrow\ORM;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:30
 * To change this template use File | Settings | File Templates.
 */
interface IExtension
{
    public function schemaBeforeReadEvent(Table $table);

    public function schemaAfterReadEvent(Table $table);

    public function schemaBeforeWriteEvent(Table $table);

    public function schemaAfterWriteEvent(Table $table);

    public function getAdditionalGenerationCode(Table $table);
}
