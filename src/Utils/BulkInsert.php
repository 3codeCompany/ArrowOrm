<?php

namespace Arrow\ORM\Utils;

use Arrow\ORM\DB\DBManager;

class BulkInsert
{
    private $buffer = [];
    private $bufferSize = 500;
    private $onFlushCallback = false;
    private $insertCount = 0;

    private $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function insert($data)
    {
        $this->buffer[] = $data;
        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function flush()
    {
        DBManager::getDefaultRepository()
            ->getConnectionInterface()
            ->bulkInsert($this->model, $this->buffer);
        $this->buffer = [];
        $this->insertCount++;
        if ($this->onFlushCallback) {
            ($this->onFlushCallback)($this->insertCount * $this->bufferSize);
        }
    }

    public function setBufferSize(int $size)
    {
        $this->bufferSize = $size;
    }

    public function setOnFlussCallbac($callback)
    {
        $this->onFlushCallback = $callback;
    }
}
