<?php

use Robsonvn\CouchDB\Collection;
use Robsonvn\CouchDB\Connection;

class CollectionTest extends TestCase
{
    /*public function testExecuteMethodCall()
    {
        $return = ['foo' => 'bar'];
        $where = ['id' => new ObjectID('56f94800911dcc276b5723dd')];
        $time = 1.1;
        $queryString = 'name-collection.findOne({"id":"56f94800911dcc276b5723dd"})';

        $mongoCollection = $this->getMockBuilder(MongoCollection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mongoCollection->expects($this->once())->method('findOne')->with($where)->willReturn($return);
        $mongoCollection->expects($this->once())->method('getCollectionName')->willReturn('name-collection');

        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $connection->expects($this->once())->method('logging')->willReturn(true);
        $connection->expects($this->once())->method('getElapsedTime')->willReturn($time);
        $connection->expects($this->once())->method('logQuery')->with($queryString, [], $time);

        $collection = new Collection($connection, $mongoCollection);

        $this->assertEquals($return, $collection->findOne($where));
    }*/

    /**
     * @expectedException Exception
     */
    public function testExecuteUnknownView()
    {
        $connection = DB::connection('couchdb');

        $collection = new Collection($connection, 'unit-test-collection');

        $this->assertInstanceOf('Robsonvn\CouchDB\Collection', $collection);
        $query = $collection->createViewQuery('all');

        $this->assertInstanceOf('Doctrine\CouchDB\View\Query', $query);

      //Doctrine\CouchDB\HTTP\HTTPException
      $result = $query->execute();
    }

    /*public function testExecuteView()
    {
        $connection = DB::connection('couchdb');

      $collection = new Collection($connection, 'articles');

      $this->assertInstanceOf('Robsonvn\CouchDB\Collection',$collection);
      $query  = $collection->createViewQuery('all');

      $this->assertInstanceOf('Doctrine\CouchDB\View\Query',$query);
      $query->setReduce(false);
      $query->setIncludeDocs(true);
      $result = $query->execute();
      print_r($result);
    }*/
}
