<?php
namespace Arrow\ORM\Schema\Behaviours\Extensions;
/**
 * Created by JetBrains PhpStorm.
 * User: artur
 * Date: 18.08.12
 * Time: 10:29
 * To change this template use File | Settings | File Templates.
 */
class Tree implements IExtension
{

    public function schemaBeforeReadEvent(Table $table)
    {
        // TODO: Implement schemaBeforeReadEvent() method.
    }

    public function schemaAfterReadEvent(Table $table)
    {
        // TODO: Implement schemaAfterReadEvent() method.
    }

    public function getAdditionalGenerationCode(Table $table)
    {
        return ":)";
    }

    public function schemaBeforeWriteEvent(Table $table)
    {
        // TODO: Implement schemaBeforeWriteEvent() method.
    }

    public function schemaAfterWriteEvent(Table $table)
    {
        // TODO: Implement schemaAfterWriteEvent() method.
    }

    public function afterEvent(Table $table)
    {
        // TODO: Implement afterEvent() method.
    }
}
