<?php

namespace Robsonvn\CouchDB\Eloquent;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Robsonvn\CouchDB\Query\Builder as QueryBuilder;

abstract class Model extends BaseModel
{
    use HybridRelations, EmbedsRelations;
    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection;

    public $incrementing = true;

    /**
     * Don't let the developer change these keys.
     */
    protected $primaryKey = '_id';
    private $revisionAttributeName = '_rev';

    /**
     * The parent relation instance.
     *
     * @var Relation
     */
    protected $parentRelation;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $attributes = [])
    {
        if ($this->primaryKey !== '_id') {
            throw new \Exception('CouchDB primary key must be _id', 1);
        }
        parent::__construct($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * {@inheritdoc}
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getPostProcessor());
    }

    public function getRevisionAttributeName()
    {
        return $this->revisionAttributeName;
    }

    public function getRevision()
    {
        $attr = $this->getRevisionAttributeName();

        return $this->$attr;
    }

    /**
     * {@inheritdoc}
     */
    protected function setKeysForSaveQuery(BaseBuilder $query)
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        if ($this->getRevision()) {
            $query->where($this->getRevisionAttributeName(), '=', $this->getRevision());
        }

        return $query;
    }

  /**
   * Custom accessor for the model's id.
   *
   * @param mixed $value
   *
   * @return mixed
   */
  public function getIdAttribute($value = null)
  {
      if (!$value and array_key_exists('_id', $this->attributes)) {
          $value = $this->attributes['_id'];
      }

      return $value;
  }

    public function setIdAttribute($value = null)
    {
        $this->attributes['_id'] = $value;

        return $value;
    }

    /**
     * Set the parent relation.
     *
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getParentRelation()
    {
        return $this->parentRelation;
    }

  /**
   * {@inheritdoc}
   */
  public function getTable()
  {
      return $this->collection ?: parent::getTable();
  }

  /**
   * {@inheritdoc}
   */
  public function getQualifiedKeyName()
  {
      return $this->getKeyName();
  }

  /**
   * {@inheritdoc}
   * Perform a model update operation.
   *
   * @param \Illuminate\Database\Eloquent\Builder $query
   *
   * @return bool
   */
  protected function performUpdate(BaseBuilder $query)
  {
      // If the updating event returns false, we will cancel the update operation so
      // developers can hook Validation systems into their models and cancel this
      // operation if the model does not pass validation. Otherwise, we update.
      if ($this->fireModelEvent('updating') === false) {
          return false;
      }

      // First we need to create a fresh query instance and touch the creation and
      // update timestamp on the model which are maintained by us for developer
      // convenience. Then we will just continue saving the model instances.
      if ($this->usesTimestamps()) {
          $this->updateTimestamps();
      }

      // Once we have run the update operation, we will fire the "updated" event for
      // this model instance. This will allow developers to hook into these after
      // models are updated, giving them a chance to do any special processing.
      $attributes = $this->getAttributes();

      if ($this->isDirty()) {
          $response = $this->setKeysForSaveQuery($query)->update($attributes);

          if (count($response)) {
              $id = $response[0]['id'];
              $rev = $response[0]['rev'];
              $this->setAttribute($this->getRevisionAttributeName(), $rev);
          }

          $this->fireModelEvent('updated', false);
      }

      return true;
  }

  /**
   * Perform a model insert operation.
   *
   * @param \Illuminate\Database\Eloquent\Builder $query
   *
   * @return bool
   */
  protected function performInsert(BaseBuilder $query)
  {
      if ($this->fireModelEvent('creating') === false) {
          return false;
      }

      // First we'll need to create a fresh query instance and touch the creation and
      // update timestamps on this model, which are maintained by us for developer
      // convenience. After, we will just continue saving these model instances.
      if ($this->usesTimestamps()) {
          $this->updateTimestamps();
      }

      //If model uses softDeletes, lets force the deleted column as null
      if (method_exists($this, 'getQualifiedDeletedAtColumn')) {
          $this->setAttribute($this->getQualifiedDeletedAtColumn(), null);
      }

      // If the model has an incrementing key, we can use the "insertGetId" method on
      // the query builder, which will give us back the final inserted ID for this
      // table from the database. Not all tables have to be incrementing though.
      $attributes = $this->getAttributes();

      $keyName = $this->getKeyName();

      if ($this->getIncrementing() && !isset($attributes['id'])) {
          list($id, $rev) = $query->insertGetId($attributes, $keyName);
      }

      // If the table isn't incrementing we'll simply insert these attributes as they
      // are. These attribute arrays must contain an "id" column previously placed
      // there by the developer as the manually determined key for these models.
      else {
          if (empty($attributes)) {
              return true;
          }
          $response = $query->insert($attributes);

          if (count($response) !== 1) {
              return false;
          }

          $id = $response[0]['id'];
          $rev = $response[0]['rev'];
      }

      $this->setAttribute($keyName, $id);
      if ($rev) {
          $this->setAttribute($this->getRevisionAttributeName(), $rev);
      }

      // We will go ahead and set the exists property to true, so that it is set when
      // the created event is fired, just in case the developer tries to update it
      // during the event. This will allow them to do so and run an update here.
      $this->exists = true;

      $this->wasRecentlyCreated = true;

      $this->fireModelEvent('created', false);

      return true;
  }

/**
 * {@inheritdoc}
 */
public function getAttribute($key)
{
    if (!$key) {
        return;
    }

    // Dot notation support.
    if (str_contains($key, '.') and array_has($this->attributes, $key)) {
        return $this->getAttributeValue($key);
    }

    // This checks for embedded relation support.
    if (method_exists($this, $key) and !method_exists(self::class, $key)) {
        return $this->getRelationValue($key);
    }

    return parent::getAttribute($key);
}

/**
 * {@inheritdoc}
 */
protected function getAttributeFromArray($key)
{
    //TODO add suport to get attribute with cast using doting notation
    // Support keys in dot notation.
    if (str_contains($key, '.')) {
        return array_get($this->attributes, $key);
    }

    return parent::getAttributeFromArray($key);
}

    public function applyCastArrayRecursive($key, $value)
    {
        if (is_array($value)) {
            $is_sequencial = array_keys($value) === range(0, count($value) - 1);

            foreach ($value as $subkey=> &$item) {
                //create a dot notation for the key, ignore subkey if is a sequencial array
        $tree = $key.(($is_sequencial) ? '' : '.'.$subkey);

                $item = $this->applyCastArrayRecursive($tree, $item);
            }

            return $value;
        } else {
            return $this->applyCasts($key, $value);
        }
    }

    protected function applyCasts($key, $value)
    {
        //Date cast
    if (in_array($key, $this->getDates()) && $value) {
        $value = $this->fromDateTime($value);
    }

        return $value;
    }

/**
 * {@inheritdoc}
 */
public function setAttribute($key, $value)
{
    if (is_array($value)) {
        $value = $this->applyCastArrayRecursive($key, $value);
    }

    if (str_contains($key, '.')) {
        $value = $this->applyCasts($key, $value);
        array_set($this->attributes, $key, $value);

        return;
    } else {
        parent::setAttribute($key, $value);
    }
}

/**
 * {@inheritdoc}
 */
public function getCasts()
{
    return $this->casts;
}

/**
 * {@inheritdoc}
 */
public function attributesToArray()
{
    $attributes = parent::attributesToArray();

    // Convert dot-notation dates.
    foreach ($this->getDates() as $key) {
        if (str_contains($key, '.') and array_has($attributes, $key)) {
            array_set($attributes, $key, (string) $this->asDateTime(array_get($attributes, $key)));
        }
    }

    return $attributes;
}

  /**
   * {@inheritdoc}
   */
  protected function removeTableFromKey($key)
  {
      return $key;
  }

    public function drop($columns)
    {
        if (!$this->exists) {
            return;
        }

        if (!is_array($columns)) {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            $this->__unset($column);
        }

        return $this->newQuery()->where(
        [
          $this->getKeyName()               => $this->getKey(),
          $this->getRevisionAttributeName() => $this->getRevision(),
        ])->unset($columns);
    }

    public function fromDateTime($value)
    {
        return $this->asDateTime($value)->format(
            'Y-m-d H:i:s'
        );
    }

    protected function asDateTime($value)
    {
        if (is_string($value) && $this->isStandardCouchDBDateFormat($value)) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value);
        }

        return parent::asDateTime($value);
    }

    protected function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * Determine if the given value is a standard CouchDB date format.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function isStandardCouchDBDateFormat($value)
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})$/', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function push()
    {
        if ($parameters = func_get_args()) {
            $unique = false;

            if (count($parameters) == 3) {
                list($column, $values, $unique) = $parameters;
            } else {
                list($column, $values) = $parameters;
            }

            // Do batch push by default.
            if (!is_array($values)) {
                $values = [$values];
            }

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            $response = $query->push($column, $values, $unique);

            $this->attributes['_rev'] = $response[0]['rev'];
            $this->syncOriginalAttribute('_rev');

            return $this;
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @param string $column
     * @param mixed  $values
     *
     * @return mixed
     */
    public function pull($column, $values)
    {
        // Do batch pull by default.
        if (!is_array($values)) {
            $values = [$values];
        }

        $query = $this->setKeysForSaveQuery($this->newQuery());

        $this->pullAttributeValues($column, $values);

        $response = $query->pull($column, $values);

        $this->attributes['_rev'] = $response[0]['rev'];
        $this->syncOriginalAttribute('_rev');

        return $this;
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param string $column
     * @param array  $values
     * @param bool   $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique and in_array($value, $current)) {
                continue;
            }

            array_push($current, $value);
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param string $column
     * @param array  $values
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            $keys = array_keys($current, $value);

            foreach ($keys as $key) {
                unset($current[$key]);
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)).'_'.ltrim($this->primaryKey, '_');
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
