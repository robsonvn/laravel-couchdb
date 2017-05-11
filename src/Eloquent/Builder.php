<?php
namespace Robsonvn\CouchDB\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    public $model;

    public function insert(array $values)
    {
        return parent::insert($values);
    }


    public function insertGetId(array $values)
    {
        return parent::insertGetId($values);
    }

  /**
 * @inheritdoc
 */
  public function update(array $values, array $options = [])
  {
      return $this->query->update($this->addUpdatedAtColumn($values), $options);
  }
}
