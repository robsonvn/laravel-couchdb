<?php

namespace Robsonvn\CouchDB\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;

use Robsonvn\CouchDB\Query\Builder as QueryBuilder;

abstract class Model extends BaseModel
{

    /**
    * The collection associated with the model.
    *
    * @var string
    */
    protected $collection;

    public $incrementing = true;

    protected $primaryKey = '_id';
    protected $revisionAttributeName = '_rev';

    protected $views = array();

    /**
    * @inheritdoc
    */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
    * @inheritdoc
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
     * @inheritdoc
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
 * @param  mixed $value
 * @return mixed
 */
  public function getIdAttribute($value = null)
  {
      // If we don't have a value for 'id', we will use the CouchDB '_id' value.
      // This allows us to work with models in a more sql-like way.
      if (! $value and array_key_exists('_id', $this->attributes)) {
          $value = $this->attributes['_id'];
      }

      return $value;
  }

  /**
   * @inheritdoc
  */
  public function getTable()
  {
      return $this->collection ?: parent::getTable();
  }

   /**
    * @inheritdoc
   */
  public function getQualifiedKeyName()
  {
      return $this->getKeyName();
  }

  /**
   * @inheritdoc
   * Perform a model update operation.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
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
      $dirty = $this->getDirty();

      if (count($dirty) > 0) {
          list($id, $rev) = $this->setKeysForSaveQuery($query)->update($attributes);
          $this->setAttribute($this->getRevisionAttributeName(), $rev);

          $this->fireModelEvent('updated', false);
      }

      return true;
  }
  /**
   * Perform a model insert operation.
   *
   * @param  \Illuminate\Database\Eloquent\Builder  $query
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
}
