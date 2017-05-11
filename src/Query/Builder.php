<?php
namespace Robsonvn\CouchDB\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Robsonvn\CouchDB\Connection;

class Builder extends BaseBuilder
{

    /**
    * @inheritdoc
    */
    public function __construct(Connection $connection, Processor $processor)
    {
        $this->grammar = new Grammar;
        $this->connection = $connection;
        $this->processor = $processor;
        $this->useCollections = true;
    }

    public function insert(array $values)
    {
        $result = $this->collection->insertOne($values);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return $this->collection->postDocument($values);
    }

    public function newQuery()
    {
        return new Builder($this->connection, $this->processor);
    }
    /**
     * @inheritdoc
     */
    public function update(array $values, array $options = [])
    {
        return $this->performUpdate($values, $options);
    }

    protected function performUpdate($values, array $options = [])
    {
        //straigt update only
        foreach($this->wheres as $where){
          $_var = $where['column'];
          $$_var = $where['value'];
        }

        list($id, $rev) = $this->collection->putDocument($values, $_id, $_rev);


    }
     /**
     * @inheritdoc
     */
    public function from($collection)
    {
        if ($collection) {
            $this->collection = $this->connection->getCollection($collection);
        }

        return parent::from($collection);
    }
}
