<?php

namespace Robsonvn\CouchDB;

class Collection
{
    protected $connection;

    protected $collection;

  /**
   * @param Connection $connection
   * @param string     $collection
   */
  public function __construct(Connection $connection, string $collection)
  {
      $this->connection = $connection;
      $this->collection = $collection;
  }

    public function __call($method, $parameters)
    {
        $parameters[0]['doc_collection'] = $this->collection;
        //echo "\n $method \n ".json_encode($parameters,JSON_PRETTY_PRINT);
        $result = call_user_func_array([$this->connection->getCouchDBClient(), $method], $parameters);

        return $result;
    }

    public function deleteMany($where)
    {
        $deleted = 0;
        $client = $this->connection->getCouchDBClient();

        $where['doc_collection'] = $this->collection;

        $result = $client->find($where, ['_id', '_rev']);

        if ($result->status == 200) {
            $bulkUpdater = $client->createBulkUpdater();

            foreach ($result->body['docs'] as $doc) {
                $bulkUpdater->deleteDocument($doc['_id'], $doc['_rev']);
            }
            $result = $bulkUpdater->execute();

            if ($result->status == 201) {
                $deleted = count($result->body);
            }
        }

        return $deleted;
    }

    public function insertMany($values)
    {
        //Force doc_collection
        foreach ($values as &$value) {
            $value['doc_collection'] = $this->collection;
        }

        $client = $this->connection->getCouchDBClient();
        $bulkUpdater = $client->createBulkUpdater();
        $bulkUpdater->updateDocuments($values);
        $response = $bulkUpdater->execute();

        return $response->body;
    }

    public function insertOne($values, $id = null)
    {
        if ($id) {
            $response = $this->putDocument($values, $id);
        } else {
            $response = $this->postDocument($values);
        }

        return $response;
    }


    public function findOne($where){
      $response = $this->find($where,[],[],1);

      if($response->status != 200){
        return;
      }

      if(count($response->body['docs'])>0){
        return $response->body['docs'][0];
      }
    }
}
