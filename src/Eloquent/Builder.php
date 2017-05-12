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
  public function update(array $values, array $options = [])
  {
      return $this->query->update($this->addUpdatedAtColumn($values), $options);
  }
}
