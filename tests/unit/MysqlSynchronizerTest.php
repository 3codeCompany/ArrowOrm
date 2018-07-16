<?php

class MysqlSynchronizerTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /** @var \Arrow\ORM\DB\DBRepository */
    protected $repository;

    protected function _before()
    {


        /** @var  $driver */
        $driver = $this->getModule("Db")->driver->getDbh();

        /** @var  $driver */
        //$driver = $this->getModule("Db")->driver->getDbh();
        /*        $driver->query(" DROP database orm");
                $driver->query(" CREATE  database orm");*/

        \Arrow\ORM\Loader::registerAutoload();

        $mysqlInterface = new \Arrow\ORM\Connectors\Mysql\MysqlDBInterface($driver);

        $mysqlRepository = new \Arrow\ORM\DB\DBRepository(
            $mysqlInterface,
            "tests/_cache/",
            function () {
                $files[] = "tests/assets/schema/schema.yaml";
                return $files;
            }
        );

        $mysqlRepository->trackSchemaChange = true;
        $mysqlRepository->synchronizationEnabled = true;

        \Arrow\ORM\DB\DBManager::addRepository("mysql", $mysqlRepository);

        $this->repository = Arrow\ORM\DB\DBManager::getDefaultRepository();

    }

    protected function _after()
    {


    }

    // tests
    public function testSomeFeature()
    {


        $this->repository->synchronize();

        $changes = $this->repository->getMissMatches();

        codecept_debug($this->repository->getMissMatches());

        $this->assertEquals(count($this->repository->getMissMatches()), 0);



    }


}