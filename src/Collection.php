<?php
namespace Robsonvn\CouchDB;

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
}
