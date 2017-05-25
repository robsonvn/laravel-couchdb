<?php

namespace Robsonvn\CouchDB;

use Doctrine\CouchDB\CouchDBClient;
use Illuminate\Database\Connection as BaseConnection;

class Connection extends BaseConnection
{
    /**
     * The CouchDB database handler.
     *
     * @var resource
     */
    protected $db;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = CouchDBClient::create($config);
        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
    }

    /**
     * Get the CouchDB database object.
     *
     * @return \Doctrine\CouchDB\CouchDBClient
     */
    public function getCouchDBClient()
    {
        return $this->db;
    }

    /**
     * @return string
     */
    public function getDriverName()
    {
        return 'couchdb';
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param string $collection
     *
     * @return Query\Builder
     */
    public function collection($collection)
    {
        $query = new Query\Builder($this, $this->getPostProcessor());

        return $query->from($collection);
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param string $table
     *
     * @return Query\Builder
     */
    public function table($table)
    {
        return $this->collection($table);
    }

    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }

    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar();
    }

    public function getCollection($name)
    {
        return new Collection($this, $name);
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->db, $method], $parameters);
    }
}
