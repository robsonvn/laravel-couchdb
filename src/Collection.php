<?php

namespace Robsonvn\CouchDB;

use Robsonvn\CouchDB\Helpers\Arr;

class Collection
{
    protected $connection;

    protected $collection;

  /**
   * @param Connection $connection
   * @param string     $collection
   */
  public function __construct(Connection $connection, $collection)
  {
      $this->connection = $connection;
      $this->collection = $collection;
  }

    public function __call($method, $parameters)
    {
        $parameters[0]['doc_collection'] = $this->collection;

        $result = call_user_func_array([$this->connection->getCouchDBClient(), $method], $parameters);

        return $result;
    }

    public function getName()
    {
        return $this->collection;
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

    public function findOne($where)
    {
        $response = $this->find($where, [], [], 1);

        if ($response->status != 200) {
            return;
        }

        if (count($response->body['docs']) > 0) {
            return $response->body['docs'][0];
        }
    }

    public function updateMany($selector, $values, array $options = [])
    {
        $result = $this->find($selector);

        if ($result->status == 200) {
            $documents = $result->body['docs'];

            foreach ($documents as &$document) {
                //update new values
                $document = array_merge($document, $values);

                $document = $this->applyUpdateOptions($document, $options);
            }

            $client = $this->connection->getCouchDBClient();
            $bulkUpdater = $client->createBulkUpdater();
            $bulkUpdater->updateDocuments($documents);
            $response = $bulkUpdater->execute();

            if ($response->status == 201) {
                return $response->body;
            }
        }
    }

    protected function applyUpdateOptions($document, $options)
    {
        foreach ($options as $option=> $value) {
            $option = ucfirst(str_replace('$', '', $option));
            $method = 'applyUpdateOption'.$option;
            if (method_exists($this, $method)) {
                $document = call_user_func_array([$this, $method], [$document, $value]);
            }
        }

        return $document;
    }

    protected function applyUpdateOptionInc($document, $options)
    {
        $data = new \Adbar\Dot();
        $data->setReference($document);

        foreach ($options as $key=>$value) {
            $current_value = ($data->get($key)) ?: 0;
            $data->set($key, $current_value + $value);
        }

        return $document;
    }

    protected function applyUpdateOptionUnset($document, $options)
    {
        return array_diff_key($document, $options);
    }

    protected function applyUpdateOptionAddToSet($document, $options)
    {
        return $this->applyUpdateOptionPush($document, $options, true);
    }

    protected function applyUpdateOptionPush($document, $options, $unique = false)
    {
        foreach ($options as $key=> $value) {
            if (is_array($value) && array_key_exists('$each', $value)) {
                $value = $value['$each'];
            } else {
                $value = [$value];
            }

        //If there's no value yet
        if (!array_key_exists($key, $document)) {
            $value = (array) $value;
          //apply unique treatment
          if ($unique) {
              $is_sequencial = (is_array($value) and array_keys($value) === range(0, count($value) - 1));
              $value = array_unique($value);
            //if is a sequencial array, reset array index
            if ($is_sequencial) {
                $value = array_values($value);
            }
          }
            $document[$key] = $value;
            continue;
        }

            foreach ($value as $v) {
                if (!$unique || !$this->checkIfExists($v, $document[$key])) {
                    array_push($document[$key], $v);
                }
            }
        }

        return $document;
    }

    protected function checkIfExists($new, $documents)
    {
        if (is_array($new) && array_key_exists('_id', $new)) {
            foreach ($documents as $document) {
                if (isset($document['_id']) && $document['_id'] == $new['_id']) {
                    return true;
                }
            }
        }

        return in_array($new, $documents);
    }

    protected function applyUpdateOptionPullAll($document, $options)
    {
        //cast array values into a sequencial array
      array_walk($options, function (&$value) {
          $is_sequencial = (is_array($value) and array_keys($value) === range(0, count($value) - 1));
          if (!$is_sequencial) {
              $value = [$value];
          }
      });

        return Arr::array_diff_recursive($document, $options);
    }

    public function createMangoIndex($fields, $index_name)
    {
        $response = $this->connection->getCouchDBClient()->createMangoIndex($fields, 'mango-indexes', $index_name);

        return in_array($response->status, [200, 201]);
    }
}
