<?php
namespace Robsonvn\CouchDB\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection;
use Robsonvn\CouchDB\Connection;

class Builder extends BaseBuilder
{
    protected $collection;

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
    /**
     * @inheritdoc
     */
    public function insert(array $values)
    {
        return $this->collection->putDocument($values,$values['id']);
    }
    /**
     * @inheritdoc
     */
    public function insertGetId(array $values, $sequence = null)
    {
        return $this->collection->postDocument($values);
    }


    /**
     * @inheritdoc
     */
    public function count($columns = '*')
    {
        //TODO: add support to aggregate function
      return $this->get()->count();

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

        foreach ($this->wheres as $where) {
            $column = $where['column'];
            $$column = $where['value'];
        }

        return $this->collection->putDocument($values, $_id, $_rev);
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

    /**
    * @inheritdoc
    */
    public function truncate()
    {
        //TODO: Create a filter to delete all instead select and delete
        $results = $this->get();

        $bulkUpdater = $this->collection->createBulkUpdater();
        foreach ($results as $row) {
            $bulkUpdater->deleteDocument($row['_id'], $row['_rev']);
        }

        $response = $bulkUpdater->execute();

        return $response->status == 201;
    }

    public function get($columns = ['*'])
    {
        $wheres = $this->compileWheres();

        $results = $this->collection->find($wheres);
        $results = $results->body['docs'];

        $collections =  $this->useCollections ? new Collection($results) : $results;

        return $collections;
    }



    /**
     * Compile the where array.
     *
     * @return array
     */
    protected function compileWheres()
    {
        // The wheres to compile.
        $wheres = $this->wheres ?: [];

        // We will add all compiled wheres to this array.
        $compiled = [];

        foreach ($wheres as $i => &$where) {
            // Make sure the operator is in lowercase.
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);

                // Operator conversions
                $convert = [
                    'regexp' => 'regex',
                    'elemmatch' => 'elemMatch',
                    'geointersects' => 'geoIntersects',
                    'geowithin' => 'geoWithin',
                    'nearsphere' => 'nearSphere',
                    'maxdistance' => 'maxDistance',
                    'centersphere' => 'centerSphere',
                    'uniquedocs' => 'uniqueDocs',
                ];

                if (array_key_exists($where['operator'], $convert)) {
                    $where['operator'] = $convert[$where['operator']];
                }
            }

            // Convert id's.
          /*  if (isset($where['column']) and ($where['column'] == '_id' or ends_with($where['column'], '._id'))) {
                // Multiple values.
                if (isset($where['values'])) {
                    foreach ($where['values'] as &$value) {
                        $value = $this->convertKey($value);
                    }
                } // Single value.
                elseif (isset($where['value'])) {
                    $where['value'] = $this->convertKey($where['value']);
                }
            }*/

            // Convert DateTime values to UTCDateTime.
            if (isset($where['value'])) {
                if (is_array($where['value'])) {
                    array_walk_recursive($where['value'], function (&$item, $key) {
                        if ($item instanceof DateTime) {
                            $item = new UTCDateTime($item->getTimestamp() * 1000);
                        }
                    });
                } else {
                    if ($where['value'] instanceof DateTime) {
                        $where['value'] = new UTCDateTime($where['value']->getTimestamp() * 1000);
                    }
                }
            }

            // The next item in a "chain" of wheres devices the boolean of the
            // first item. So if we see that there are multiple wheres, we will
            // use the operator of the next where.
            if ($i == 0 and count($wheres) > 1 and $where['boolean'] == 'and') {
                $where['boolean'] = $wheres[$i + 1]['boolean'];
            }

            // We use different methods to compile different wheres.
            $method = "compileWhere{$where['type']}";
            $result = $this->{$method}($where);

            // Wrap the where with an $or operator.
            if ($where['boolean'] == 'or') {
                $result = ['$or' => [$result]];
            }

            // If there are multiple wheres, we will wrap it with $and. This is needed
            // to make nested wheres work.
            elseif (count($wheres) > 1) {
                $result = ['$and' => [$result]];
            }


            // Merge the compiled where with the others.
            $compiled = array_merge_recursive($compiled, $result);
        }


        //exit;
        return $compiled;
    }

    /**
     * @param array $where
     * @return array
     */
    protected function compileWhereBasic(array $where)
    {
        extract($where);

        // Replace like with a Regex instance.
        if ($operator == 'like') {
            $operator = '=';

            // Convert to regular expression.
            $regex = preg_replace('#(^|[^\\\])%#', '$1.*', preg_quote($value));

            // Convert like to regular expression.
            if (! starts_with($value, '%')) {
                $regex = '^'.$regex;
            }
            if (! ends_with($value, '%')) {
                $regex = $regex.'$';
            }

            $value = new Regex($regex, 'i');
        } // Manipulate regexp operations.
        elseif (in_array($operator, ['regexp', 'not regexp', 'regex', 'not regex'])) {
            // Automatically convert regular expression strings to Regex objects.
            if (! $value instanceof Regex) {
                $e = explode('/', $value);
                $flag = end($e);
                $regstr = substr($value, 1, -(strlen($flag) + 1));
                $value = new Regex($regstr, $flag);
            }

            // For inverse regexp operations, we can just use the $not operator
            // and pass it a Regex instence.
            if (starts_with($operator, 'not')) {
                $operator = 'not';
            }
        }

        if (! isset($operator) or $operator == '=') {
            $query = [$column => $value];
        } elseif (array_key_exists($operator, $this->conversion)) {
            $query = [$column => [$this->conversion[$operator] => $value]];
        } else {
            $query = [$column => ['$'.$operator => $value]];
        }

        return $query;
    }
}
