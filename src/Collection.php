<?php
namespace Robsonvn\CouchDB;

use Robsonvn\CouchDB\View\AbstractDesignDocument;
use Robsonvn\CouchDB\View\AllViewQuery;

class Collection
{
    protected $connection;

    protected $collection;

  /**
   * @param Connection      $connection
   * @param string $collection
   */
  public function __construct(Connection $connection, string $collection)
  {
      $this->connection = $connection;
      $this->collection = $collection;
  }

    public function __call($method, $parameters)
    {
        $parameters[0]['type'] = $this->collection;
        $result = call_user_func_array([$this->connection->getCouchDBClient(), $method], $parameters);
        return $result;
    }
/*
    public function checkIfExists()
    {
        $client = $this->connection->getCouchDBClient();

        $response = $client->findDocument("_design/{$this->collection}");

        return $response->status == 200;
    }


    public function createViewQuery($view_name)
    {
        return $this->connection->getCouchDBClient()->createViewQuery($this->collection, $view_name);
    }

    public function createViewAllQuery()
    {
        return new AllViewQuery($this);
    }

    public function createAllDesignDocument()
    {
        $client = $this->connection->getCouchDBClient();
        return $client->createDesignDocument($this->collection, new AbstractDesignDocument($this->collection));
    }*/

    public function drop()
    {
    }
}
