<?php namespace Arrow\ORM;
/**
 * Writes schema config file
 *
 * @author     Artur Kmera <artur.kmera@3code.pl>
 * @version    0.9
 * @package    ORM
 * @subpackage Schema
 * @link       http://arrowplatform.org/orm
 * @copyright  2011 Arrowplatform
 * @license    GNU LGPL
 */
class SchemaWriter implements ISchemaWriter
{

    /**
     * (non-PHPdoc)
     *
     * @see ISchemaWriter::writeSchemaToFile()
     */
    public function writeSchemaToFile(Schema $schema, $file)
    {
        // Create new SimpleXMLElement object
        $xml = new \SimpleXMLElement("<schema></schema>");
        foreach ($schema->getTables() as $table) {
            $this->writeTable($table, $xml);
        }

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());


        file_put_contents($file, $dom->saveXML());
    }

    /**
     * Writes table to node
     *
     * @param Table   $table
     * @param XMLNode $node
     */
    private function writeTable(Table $table, $node)
    {
        $tableNode = $node->addChild('table');
        $tableNode->addAttribute("name", $table->getTableName());
        $tableNode->addAttribute("class", $table->getClass());
        $tableNode->addAttribute("baseClass", $table->getBaseClass());


        $extensions = $table->getExtension();
        if (!empty($trackers)) {
            $extensionsNode = $tableNode->addChild("extensions");
            foreach ($extensions as $tracker) {
                $this->writeExtension($tracker, $extensionsNode);
            }
        }

        $trackers = $table->getTrackers();
        if (!empty($trackers)) {
            $trackersNode = $tableNode->addChild("trackers");
            foreach ($trackers as $tracker) {
                $this->writeTracker($tracker, $trackersNode);
            }
        }

        foreach ($table->getFields() as $field) {
            $this->writeField($field, $tableNode);
        }

        foreach ($table->getIndexes() as $index) {
            $this->writeIndex($index, $tableNode);
        }

        foreach ($table->getForeignKeys() as $fKey) {
            $this->writeForeignKey($fKey, $tableNode);
        }


    }

    /**
     * Writes field to node
     *
     * @param Table   $table
     * @param XMLNode $node
     */
    private function writeField(Field $field, $node)
    {
        $fieldNode = $node->addChild('field');
        $fieldNode->addAttribute("name", $field->getName());
        $fieldNode->addAttribute("type", $field->getType());

        if ($field->isPKey()) {
            $fieldNode->addAttribute("primaryKey", "true");
        }
        if ($field->isAutoincrement()) {
            $fieldNode->addAttribute("autoIncrement", "true");
        }
        if ($field->isRequired()) {
            $fieldNode->addAttribute("required", "true");
        }
        if ($field->getSize()) {
            $fieldNode->addAttribute("size", $field->getSize());
        }

        if ($field->getDefault()) {
            $fieldNode->addAttribute("default", $field->getDefault());
        }

    }

    /**
     * Writes foreign key to node
     *
     * @param ForeignKey $fKey
     * @param XMLNode    $node
     */
    private function writeForeignKey(ForeignKey $fKey, $node)
    {
        $fKeyNode = $node->addChild('foreign-key');

        $fKeyNode->addAttribute("foreignTable", $fKey->getForeignTableName());
        $fKeyNode->addAttribute("onUpdate", $fKey->onUpdate);
        $fKeyNode->addAttribute("onDelete", $fKey->onDelete);


        foreach ($fKey->getReferences() as $reference) {
            $referenceNode = $fKeyNode->addChild('reference');
            $referenceNode->addAttribute("local", $reference->getLocalFieldName());
            $referenceNode->addAttribute("foreign", $reference->getForeignFieldName());
        }


    }

    /**
     * Writes index to node
     *
     * @param Index   $index
     * @param XMLNode $node
     */
    private function writeIndex(Index $index, $node)
    {
        $indexNode = $node->addChild('index');
        $indexNode->addAttribute("name", $index->getName());
        $indexNode->addAttribute("type", $index->getType());
        foreach ($index->getFieldNames() as $field) {
            $tmp = $indexNode->addChild('index-field');
            $tmp->addAttribute("name", $field);
        }
    }

    private function writeTracker($tracker, $node)
    {
        $trackerNode = $node->addChild('tracker');
        $trackerNode->addAttribute(" class", $tracker);
    }

    private function writeExtension($extension, $node)
    {
        $extensionNode = $node->addChild('extension');
        $extensionNode->addAttribute(" class", $extension);
    }


}

?>