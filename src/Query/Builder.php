<?php

namespace Robsonvn\CouchDB\Query;

use Closure;
use DateTime;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Robsonvn\CouchDB\Connection;

class Builder extends BaseBuilder
{
    /**
 * The database collection.
 *
 * @var string
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
    'not ilike',
    'all',
    '&',
    '|',
    'exists',
    'type',
    'mod',
    'where',
    'size',
    'regex',
    'not regex',
    'elemmatch',
];

/**
 * Operator conversion.
 *
 * @var array
 */
protected $conversion = [
    '='  => '=',
    '!=' => '$ne',
    '<>' => '$ne',
    '<'  => '$lt',
    '<=' => '$lte',
    '>'  => '$gt',
    '>=' => '$gte',
];

    protected $useCollections;

    /**
     * {@inheritdoc}
     */
    public function __construct(Connection $connection, Processor $processor)
    {
        $this->grammar = new Grammar();
        $this->connection = $connection;
        $this->processor = $processor;
        $this->useCollections = true;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will have to detect
      // if the user is inserting a single document or an array of documents.
      $batch = true;

        foreach ($values as $value) {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (!is_array($value)) {
                $batch = false;
                break;
            }
        }

        if (!$batch) {
            $values = [$values];
        }

        return $this->collection->insertMany($values);
    }

    /**
     * {@inheritdoc}
     */
    public function insertGetId(array $values, $sequence = null)
    {
        return $this->collection->insertOne($values);
    }

    /**
     * {@inheritdoc}
     */
    public function count($columns = ['*'])
    {
        //TODO: add support to aggregate function
      return $this->get()->count();
    }

    public function newQuery()
    {
        return new self($this->connection, $this->processor);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $values, array $options = [])
    {
        return $this->performUpdate($values, $options);
    }

    protected function performUpdate($values, array $options = [])
    {
        return $this->collection->updateMany($this->compileWheres(), $values, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function from($collection)
    {
        if ($collection) {
            $this->collection = $this->connection->getCollection($collection);
        }

        return parent::from($collection);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id, $columns = ['*'])
    {
        return $this->where('_id', '=', $id)->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function truncate()
    {
        $results = $this->get();

        $bulkUpdater = $this->collection->createBulkUpdater();
        foreach ($results as $row) {
            $bulkUpdater->deleteDocument($row['_id'], $row['_rev']);
        }

        $response = $bulkUpdater->execute();

        return $response->status == 201;
    }

    protected function createIndex()
    {
        $fields = $this->getSort();
        $name = $this->resolveIndexName($fields);

        return $this->collection->createMangoIndex($fields, $name) ? $name : false;
    }

    public function useIndex(array $index)
    {
        $this->index = $index;

        return $this;
    }

    public function getSort()
    {
        $sort = isset($this->orders) ? [$this->orders] : [];

        $direction = 'asc';

      //CouchDB 2.0 currently only support a single direction for all fields
      if (count($this->orders)) {
          $direction = array_unique(array_values($this->orders));
          if (count($direction) > 1) {
              throw new \Exception('Sorts currently only support a single direction for all fields.');
          }
          list($direction) = $direction;
      }

      //always sort per doc_collection first
      array_unshift($sort, ['doc_collection'=>$direction]);

        return $sort;
    }

    public function getIndex()
    {
        //Use index defined by user otherwise guess which index use
      if (isset($this->index)) {
          return $this->index;
      }

        return ['_design/mango-indexes', $this->resolveIndexName($this->getSort())];
    }

    protected function resolveIndexName($fields)
    {
        $sort = [];
        foreach ($fields as $key => $field) {
            $sort[] = key($field).':'.current($field);
        }

        return implode('&', $sort);
    }

    /**
     * {@inheritdoc}
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_null($value) && $operator == '>=') {
            $this->wheres[] = [
          'type'    => 'Basic',
          'operator'=> '>=',
          'column'  => $column,
          'value'   => null,
          'boolean' => $boolean,

        ];

            return $this;
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Unfortunately, CouchDB require that the fields there are being ordained must be on selector clausure
     * For those who aren't being select, let's add a selector field >= null.
     */
    protected function addWhereGreaterThanNullForOrdersField()
    {
        if (is_array($this->orders)) {
            foreach ($this->orders as $field => $direction) {
                $exists = false;
                //search if exist any filter for this field
                if (is_array($this->wheres)) {
                    foreach ($this->wheres as $where) {
                        if ($where['column'] == $field) {
                            $exists = true;
                            continue;
                        }
                    }
                }
                if (!$exists) {
                    $this->where($field, '>=', null);
                }
            }
        }
    }

    public function get($columns = [], $create_index = true)
    {
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        // Drop all columns if * is present, CouchDB does not work this way.
        if (in_array('*', $this->columns)) {
            $this->columns = [];
        }

        $this->addWhereGreaterThanNullForOrdersField();
        $wheres = $this->compileWheres();

        $skip = ($this->offset) ?: 0;

        $sort = $this->getSort();

        $index = $this->getIndex();

        //TODO create driver cursor
        $limit = ($this->limit) ?: 25;

        $results = $this->collection->find($wheres, $this->columns, $sort, $limit, $skip, $index);

        if ($results->status != 200) {
            //no-index or no matching fields
            if ($results->status == 500) {
                //If use automatic index, lets create the index and try one more time
              if ($create_index) {
                  if ($this->createIndex()) {
                      return $this->get($columns, false);
                  }
              }
                throw new \Exception('QueryException no-index or no matching fields order/selector');
            }
          //TODO improve this exception
          throw new \Exception('QueryException '.$results->body['reason']);
        }

        $results = $results->body['docs'];

        $collections = $this->useCollections ? new Collection($results) : $results;

        return $collections;
    }

    /**
     * @param array $where
     *
     * @return mixed
     */
    protected function compileWhereNested(array $where)
    {
        extract($where);

        return $query->compileWheres();
    }

    protected function getDatabaseEquivalentDataType($value)
    {
        if ($value instanceof DateTime) {
            $type = 'string';
        } else {
            $type = gettype($value);
            $type = (in_array($type, ['integer', 'double']) ? 'number' : $type);
        }

        return $type;
    }

    /**
     * Compile the where array.
     *
     * @return array
     */
    protected function compileWheres()
    {
        // The wheres to compile.
        $wheres = is_array($this->wheres) ? $this->wheres : [];

        // We will add all compiled wheres to this array.
        $compiled = [];

        //CouchDB collation order considers null, false and true as less than an integer
        //and any letter as greater than an integer
        //to avoid unexpected results, let's specify what type of value we're querying
        foreach ($wheres as $where) {
            if (isset($where['operator']) && in_array($where['operator'], ['>', '>=', '<', '<='])) {
                $value_type = $this->getDatabaseEquivalentDataType($where['value']);

                if (in_array($value_type, ['boolean', 'number', 'string'])) {
                    $wheres[] = [
                'type'     => $where['type'],
                'operator' => 'type',
                'column'   => $where['column'],
                'boolean'  => 'and',
                'value'    => $value_type,
              ];
                }
            }
        }

        foreach ($wheres as $i => &$where) {

            // Make sure the operator is in lowercase.
            if (isset($where['operator'])) {
                $where['operator'] = strtolower($where['operator']);

                // Operator conversions
                $convert = [
                    'regexp'        => 'regex',
                    'elemmatch'     => 'elemMatch',
                    'geointersects' => 'geoIntersects',
                    'geowithin'     => 'geoWithin',
                    'nearsphere'    => 'nearSphere',
                    'maxdistance'   => 'maxDistance',
                    'centersphere'  => 'centerSphere',
                    'uniquedocs'    => 'uniqueDocs',
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
                            $item = $item->format('Y-m-d H:i:s.u');
                        }
                    });
                } else {
                    if ($where['value'] instanceof DateTime) {
                        $where['value'] = $where['value']->format('Y-m-d H:i:s.u');
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
     *
     * @return array
     */
    protected function compileWhereBasic(array $where)
    {
        extract($where);

        // Replace like with a Regex instance.
        if (in_array($operator, ['like', 'not like', 'ilike', 'not ilike'])) {

            // Convert to regular expression.
            $regex = preg_replace('#(^|[^\\\])%#', '$1.*', preg_quote($value));

            // Convert like to regular expression.
            if (!starts_with($value, '%')) {
                $regex = '^'.$regex;
            }
            if (!ends_with($value, '%')) {
                $regex = $regex.'$';
            }

            //add case insensitive modifier for ilike operation
            $value = (ends_with($operator, 'ilike')) ? '(?i)'.$regex : $regex;

            $operator = preg_replace('/(i|)(like)/', 'regex', $operator);
        }

        // Manipulate negative regexp operations.
        if ($operator == 'not regex') {
            $value = "^(?!$value)";
            $operator = 'regex';
        }

        if (!isset($operator) or $operator == '=') {
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
 *
 * @return mixed
 */
protected function compileWhereRaw(array $where)
{
    return $where['sql'];
}

  /**
   * @param array $where
   *
   * @return array
   */
  protected function compileWhereIn(array $where)
  {
      extract($where);

      return [$column => ['$in' => array_values($values)]];
  }

  /**
   * @param array $where
   *
   * @return array
   */
  protected function compileWhereNotIn(array $where)
  {
      extract($where);

      return [$column => ['$nin' => array_values($values)]];
  }

  /**
   * @param array $where
   *
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
   *
   * @return array
   */
  protected function compileWhereNotNull(array $where)
  {
      $where['operator'] = '>';
      $where['value'] = null;

      return $this->compileWhereBasic($where);
  }

  /**
   * @param array $where
   *
   * @return array
   */
  protected function compileWhereBetween(array $where)
  {
      extract($where);

      $value_type = $this->getDatabaseEquivalentDataType($values[0]);

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
            $column => [
              '$type'=> $value_type,
            ],
          ];
      } else {
          return [
              $column => [
                  '$gte' => $values[0],
                  '$lte' => $values[1],
                  '$type'=> $value_type,
              ],
          ];
      }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id = null)
  {
      // If an ID is passed to the method, we will set the where clause to check
      // the ID to allow developers to simply and quickly remove a single row
      // from their database without manually specifying the where clauses.
      if (!is_null($id)) {
          $this->where('_id', '=', $id);
      }

      $wheres = $this->compileWheres();

      return $this->collection->DeleteMany($wheres);
  }

  /**
   * {@inheritdoc}
   */
  public function raw($expression = null)
  {
      // Execute the closure on the mongodb collection
      if ($expression instanceof Closure) {
          return call_user_func($expression, $this->collection);
      } // Create an expression for the given value
      elseif (!is_null($expression)) {
          return new Expression($expression);
      }

      // Quick access to the mongodb collection
      return $this->collection;
  }

  /**
   * {@inheritdoc}
   */
  public function whereBetween($column, array $values, $boolean = 'and', $not = false)
  {
      $type = 'between';

      $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

      return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function orderBy($column, $direction = 'asc')
  {
      $direction = strtolower($direction);

      if (in_array($direction, ['asc', 'desc'])) {
          $this->orders[$column] = $direction;
      }

      return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function exists()
  {
      return !is_null($this->first());
  }

  /**
   * Remove one or more fields.
   *
   * @param mixed $columns
   *
   * @return int
   */
  public function drop($columns)
  {
      if (!is_array($columns)) {
          $columns = [$columns];
      }

      $fields = [];

      foreach ($columns as $column) {
          $fields[$column] = 1;
      }

      return $this->performUpdate([], ['$unset'=>$fields]);
  }

  /**
   * Append one or more values to an array.
   *
   * @param mixed $column
   * @param mixed $value
   * @param bool  $unique
   *
   * @return int
   */
  public function push($column, $value = null, $unique = false)
  {
      // Use the addToSet operator in case we only want unique items.
      $operator = $unique ? '$addToSet' : '$push';

      // Check if we are pushing multiple values.
      $batch = (is_array($value) and array_keys($value) === range(0, count($value) - 1));

      if (is_array($column)) {
          $query = [$operator => $column];
      } elseif ($batch) {
          $query = [$operator => [$column => ['$each' => $value]]];
      } else {
          $query = [$operator => [$column => $value]];
      }

      return $this->performUpdate([], $query);
  }

  /**
   * Remove one or more values from an array.
   *
   * @param mixed $column
   * @param mixed $value
   *
   * @return int
   */
  public function pull($column, $value = null)
  {
      $operator = '$pullAll';

      if (is_array($column)) {
          $query = [$operator => $column];
      } else {
          $query = [$operator => [$column => $value]];
      }

      return $this->performUpdate([], $query);
  }

  /**
   * {@inheritdoc}
   */
  public function increment($column, $amount = 1, array $extra = [], array $options = [])
  {
      $query = ['$inc' => [$column => $amount]];

      // Protect
      $this->where(function ($query) use ($column) {
          $query->where($column, 'exists', false);

          $query->orWhereNotNull($column);
      });

      return $this->performUpdate($extra, $query);
  }

  /**
   * {@inheritdoc}
   */
  public function decrement($column, $amount = 1, array $extra = [], array $options = [])
  {
      return $this->increment($column, -1 * $amount, $extra, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $parameters)
  {
      // Unset method
      if ($method == 'unset') {
          return call_user_func_array([$this, 'drop'], $parameters);
      }

      return parent::__call($method, $parameters);
  }
}
