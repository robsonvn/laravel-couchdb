<?php

namespace Robsonvn\CouchDB\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    protected $model;

    protected $query;

  /**
   * {@inheritdoc}
   */
  public function update(array $values, array $options = [])
  {
      // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performUpdate($this->model, $values);

            return 1;
        }

      return $this->query->update($this->addUpdatedAtColumn($values), $options);
  }

  /**
   * {@inheritdoc}
   */
  public function insert(array $values)
  {
      // Intercept operations on embedded models and delegate logic
      // to the parent relation instance.
      if ($relation = $this->model->getParentRelation()) {
          $relation->performInsert($this->model, $values);

          return true;
      }

      return parent::insert($values);
  }

  /**
   * {@inheritdoc}
   */
  public function insertGetId(array $values, $sequence = null)
  {
      // Intercept operations on embedded models and delegate logic
      // to the parent relation instance.
      if ($relation = $this->model->getParentRelation()) {
          $relation->performInsert($this->model, $values);

          return $this->model->getKey();
      }

      return parent::insertGetId($values, $sequence);
  }

  /**
   * {@inheritdoc}
   */
  public function delete()
  {
      // Intercept operations on embedded models and delegate logic
      // to the parent relation instance.
      if ($relation = $this->model->getParentRelation()) {
          $relation->performDelete($this->model);

          return $this->model->getKey();
      }

      return parent::delete();
  }
  /**
 * @inheritdoc
 */
  public function raw($expression = null)
  {
    // Get raw results from the query builder.
    $results = $this->query->raw($expression);

    if(is_array($results) and array_key_exists('_id', $results)) {
        return $this->model->newFromBuilder((array) $results);
    }

    if($results instanceof \Doctrine\CouchDB\HTTP\Response){
      return $this->model->hydrate($results->body['docs']);
    }

    return $results;
  }


    /**
     * @inheritdoc
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            $result = $this->model->update([$column => $value]);

            return $result;
        }

        return parent::increment($column, $amount, $extra);
    }

    /**
   * @inheritdoc
   */
  public function decrement($column, $amount = 1, array $extra = [])
  {
      // Intercept operations on embedded models and delegate logic
      // to the parent relation instance.
      if ($relation = $this->model->getParentRelation()) {
          $value = $this->model->{$column};

          // When doing increment and decrements, Eloquent will automatically
          // sync the original attributes. We need to change the attribute
          // temporary in order to trigger an update query.
          $this->model->{$column} = null;

          $this->model->syncOriginalAttribute($column);

          return $this->model->update([$column => $value]);
      }

      return parent::decrement($column, $amount, $extra);
  }

}
