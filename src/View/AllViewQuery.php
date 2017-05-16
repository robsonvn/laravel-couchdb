<?php
namespace Robsonvn\CouchDB\View;

class AllViewQuery
{
    public function __construct($collection)
    {
        $this->collection = $collection;
        $this->query = $collection->createViewQuery('all')->setReduce(false);
    }
    public function execute()
    {
        try {
            return $this->query->execute();
        } catch (\Doctrine\CouchDB\HTTP\HTTPException $e) {
            $this->collection->createAllDesignDocument();
            return $this->query->execute();
        }
    }

    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->query, $method], $parameters);
    }
}
