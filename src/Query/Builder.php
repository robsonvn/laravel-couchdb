<?php
namespace Robsonvn\CouchDB\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection;
use Robsonvn\CouchDB\Connection;

class Builder extends BaseBuilder
{
    /**
 * The database collection.
 *
 * @var MongoCollection
 */
protected $collection;

/**
 * The column projections.
 *
 * @var array
 */
public $projections;

/**
 * The cursor timeout value.
 *
 * @var int
 */
public $timeout;

/**
 * The cursor hint value.
 *
 * @var int
 */
public $hint;

/**
 * Custom options to add to the query.
 *
 * @var array
 */
public $options = [];

/**
 * Indicate if we are executing a pagination query.
 *
 * @var bool
 */
public $paginating = false;

/**
 * All of the available clause operators.
 *
 * @var array
 */
public $operators = [
    '=',
    '<',
    '>',
    '<=',
    '>=',
    '<>',
    '!=',
    'like',
    'not like',
    'between',
    'ilike',
    '&',
    '|',
    '^',
    '<<',
    '>>',
    'rlike',
    'regexp',
    'not regexp',
    'exists',
    'type',
    'mod',
    'where',
    'all',
    'size',
    'regex',
    'text',
    'slice',
    'elemmatch',
    'geowithin',
    'geointersects',
    'near',
    'nearsphere',
    'geometry',
    'maxdistance',
    'center',
    'centersphere',
    'box',
    'polygon',
    'uniquedocs',
];

/**
 * Operator conversion.
 *
 * @var array
 */
protected $conversion = [
    '=' => '=',
    '!=' => '$ne',
    '<>' => '$ne',
    '<' => '$lt',
    '<=' => '$lte',
    '>' => '$gt',
    '>=' => '$gte',
];

/**
 * Check if we need to return Collections instead of plain arrays (laravel >= 5.3 )
 *
 * @var boolean
 */
protected $useCollections;

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
      // Since every insert gets treated like a batch insert, we will have to detect
      // if the user is inserting a single document or an array of documents.
      $batch = true;

        foreach ($values as $value) {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (! is_array($value)) {
                $batch = false;
                break;
            }
        }

        if($batch){
          return $this->collection->insertMany($values);
        }else{
          return $this->collection->insertOne($values , $values['id']);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function insertGetId(array $values, $sequence = null)
    {
        return $this->collection->insertOne($values);
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

    public function get($columns = [])
    {
        $columns = (['*'] == $columns) ? [] : $columns;

        $wheres = $this->compileWheres();

        //TODO work with projections $this->projections

        $index = null;
        $skip = ($this->offset) ? : 0;
        $sort = ($this->orders) ? : [];

        //Sorry about this, but CouchDB limit is 25 by default
        $limit = ($this->limit) ? : 999999999;


        $results = $this->collection->find($wheres, $columns,$sort, $limit, $skip, $index);

        if($results->status!=200){
          //var_dump($results);
          //exit;
          //TODO improve this exception
          throw new \Exception("Query Error");
        }

        $results = $results->body['docs'];

        $collections =  $this->useCollections ? new Collection($results) : $results;

        return $collections;
    }

    /**
     * @param array $where
     * @return mixed
     */
    protected function compileWhereNested(array $where)
    {
        extract($where);

        return $query->compileWheres();
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

            $value = $regex;
        } // Manipulate regexp operations.
        elseif (in_array($operator, ['regexp', 'not regexp', 'regex', 'not regex'])) {
            // Automatically convert regular expression strings to Regex objects.
            /*if (! $value instanceof Regex) {
                $e = explode('/', $value);
                $flag = end($e);
                $regstr = substr($value, 1, -(strlen($flag) + 1));
                $value = new Regex($regstr, $flag);
            }*/

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

    /**
 * @param array $where
 * @return mixed
 */
protected function compileWhereRaw(array $where)
{
    return $where['sql'];
}
/**
   * @param array $where
   * @return array
   */
  protected function compileWhereIn(array $where)
  {
      extract($where);

      return [$column => ['$in' => array_values($values)]];
  }

  /**
   * @param array $where
   * @return array
   */
  protected function compileWhereNotIn(array $where)
  {
      extract($where);

      return [$column => ['$nin' => array_values($values)]];
  }

  /**
   * @param array $where
   * @return array
   */
  protected function compileWhereNull(array $where)
  {
      $where['operator'] = '=';
      $where['value'] = null;

      return $this->compileWhereBasic($where);
  }

  /**
   * @param array $where
   * @return array
   */
  protected function compileWhereNotNull(array $where)
  {
      $where['operator'] = '!=';
      $where['value'] = null;

      return $this->compileWhereBasic($where);
  }

  /**
   * @param array $where
   * @return array
   */
  protected function compileWhereBetween(array $where)
  {
      extract($where);

      if ($not) {
          return [
              '$or' => [
                  [
                      $column => [
                          '$lte' => $values[0],
                      ],
                  ],
                  [
                      $column => [
                          '$gte' => $values[1],
                      ],
                  ],
              ],
          ];
      } else {
          return [
              $column => [
                  '$gte' => $values[0],
                  '$lte' => $values[1],
              ],
          ];
      }
  }
  /**
 * @inheritdoc
 */
public function delete($id = null)
{
    // If an ID is passed to the method, we will set the where clause to check
    // the ID to allow developers to simply and quickly remove a single row
    // from their database without manually specifying the where clauses.
    if (! is_null($id)) {
        $this->where('_id', '=', $id);
    }

    $wheres = $this->compileWheres();

    return $this->collection->DeleteMany($wheres);
}
}
