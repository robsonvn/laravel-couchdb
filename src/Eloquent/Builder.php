<?php
namespace Robsonvn\CouchDB\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    protected $model;

    protected $query;


  /**
 * @inheritdoc
 */
  /*public function update(array $values, array $options = [])
  {
          // Intercept operations on embedded models and delegate logic
      // to the parent relation instance.
      if ($relation = $this->model->getParentRelation()) {
          $relation->performUpdate($this->model, $values);

          return 1;
      }
      return $this->query->update($this->addUpdatedAtColumn($values), $options);
  }*/
  /**
 * @inheritdoc
 */
/*public function insert(array $values)
{
    // Intercept operations on embedded models and delegate logic
    // to the parent relation instance.
    if ($relation = $this->model->getParentRelation()) {
        $relation->performInsert($this->model, $values);

        return true;
    }

    return parent::insert($values);
}*/

/**
 * @inheritdoc
 */
/*public function insertGetId(array $values, $sequence = null)
{
    // Intercept operations on embedded models and delegate logic
    // to the parent relation instance.
    if ($relation = $this->model->getParentRelation()) {
        $relation->performInsert($this->model, $values);

        return $this->model->getKey();
    }

    return parent::insertGetId($values, $sequence);
}*/

/**
 * @inheritdoc
 */
/*public function delete()
{
    // Intercept operations on embedded models and delegate logic
    // to the parent relation instance.
    if ($relation = $this->model->getParentRelation()) {
        $relation->performDelete($this->model);

        return $this->model->getKey();
    }

    return parent::delete();
}*/
}
