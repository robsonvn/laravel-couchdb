<?php

namespace Robsonvn\CouchDB\Eloquent;

use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model as BaseModel;
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

    protected $attributes_unset = [];

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
        $query->where($this->getRevisionAttributeName(), '=', $this->getRevision());

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
      // If we don't have a value for 'id', we will use the CouchDB '_id' value.
      // This allows us to work with models in a more sql-like way.
      if (!$value and array_key_exists('_id', $this->attributes)) {
          $value = $this->attributes['_id'];
      }

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
          list($id, $rev) = $this->setKeysForSaveQuery($query)->update($attributes);
          $this->setAttribute($this->getRevisionAttributeName(), $rev);
          $this->attributes_unset = [];

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
      $attributes = $this->attributes;
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
          list($id, $rev) = $query->insert($attributes);
      }

      $this->setAttribute($keyName, $id);
      $this->setAttribute($this->getRevisionAttributeName(), $rev);

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

    protected function unsetAttribute($key)
    {
        if (!$key) {
            return;
        }
        array_set($this->attributes_unset, $key, true);
        array_forget($this->attributes, $key);
    }

/**
 * {@inheritdoc}
 */
protected function getAttributeFromArray($key)
{
    // Support keys in dot notation.
    if (str_contains($key, '.')) {
        return array_get($this->attributes, $key);
    }

    return parent::getAttributeFromArray($key);
}

/**
 * {@inheritdoc}
 */
public function setAttribute($key, $value)
{
    // Convert _id to ObjectID.
    if ($key == '_id' and is_string($value)) {
        $builder = $this->newBaseQueryBuilder();

        $value = $value;
    } // Support keys in dot notation.
    elseif (str_contains($key, '.')) {
        if (in_array($key, $this->getDates()) && $value) {
            $value = $this->fromDateTime($value);
        }

        array_set($this->attributes, $key, $value);

        return;
    }

    parent::setAttribute($key, $value);
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

  /**
   * {@inheritdoc}
   */
  public function isDirty($attributes = null)
  {
      return count($this->attributes_unset) > 0 ? true : parent::isDirty($attributes);
  }

    public function unset($columns)
    {
        if (!$this->exists) {
            return;
        }

        if (!is_array($columns)) {
            $columns = [$columns];
        }

        foreach ($columns as $column) {
            $this->unsetAttribute($column);
        }

        $return = $this->update($this->getAttributes());
    }
}
